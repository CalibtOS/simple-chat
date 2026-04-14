<?php
declare(strict_types=1);

/**
 * Repository: all database access for the `messages` table.
 * Uses prepared statements for every query that involves external input.
 */
final class MessageRepository
{
    private ?bool $readAtColumnExists = null;

    public function __construct(private readonly mysqli $db) {}

    private function hasReadAtColumn(): bool
    {
        if ($this->readAtColumnExists !== null) {
            return $this->readAtColumnExists;
        }

        $result = $this->db->query("SHOW COLUMNS FROM messages LIKE 'read_at'");
        $this->readAtColumnExists = $result instanceof mysqli_result && $result->num_rows > 0;
        return $this->readAtColumnExists;
    }

    /**
     * Return a page of messages for a conversation, ordered oldest-first.
     *
     * How pagination works here:
     *   - We ask for $limit + 1 rows.  If we get $limit + 1 back, there are
     *     older messages the user hasn't seen yet (has_more = true) and we
     *     discard the extra row.  This avoids a separate COUNT query.
     *   - We order by id DESC so MySQL uses the primary-key index and stops
     *     as soon as it has enough rows.  Then we reverse the array so the
     *     caller always gets oldest-first order.
     *   - $beforeId > 0 means "only messages with id < $beforeId" — this is
     *     cursor-based pagination, which stays fast no matter how far back
     *     you scroll (unlike OFFSET, which scans and discards more rows each
     *     time).
     *
     * @return array{messages: MessageResponse[], has_more: bool}
     */
    public function getPage(int $conversationId, int $limit, int $beforeId = 0): array
    {
        $fetch = $limit + 1;   // one extra to detect whether more rows exist
        $readAtSelect = $this->hasReadAtColumn() ? 'read_at' : 'NULL AS read_at';

        if ($beforeId > 0) {
            $stmt = $this->db->prepare(
                "SELECT id, user_id, name, message, created_at, {$readAtSelect}
                 FROM messages
                 WHERE conversation_id = ? AND id < ?
                 ORDER BY id DESC
                 LIMIT ?"
            );
            $stmt->bind_param('iii', $conversationId, $beforeId, $fetch);
        } else {
            $stmt = $this->db->prepare(
                "SELECT id, user_id, name, message, created_at, {$readAtSelect}
                 FROM messages
                 WHERE conversation_id = ?
                 ORDER BY id DESC
                 LIMIT ?"
            );
            $stmt->bind_param('ii', $conversationId, $fetch);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = new MessageResponse(
                (int) $row['id'],
                $row['user_id'] !== null ? (int) $row['user_id'] : null,
                (string) $row['name'],
                (string) $row['message'],
                (string) $row['created_at'],
                $row['read_at'] !== null ? (string) $row['read_at'] : null,
            );
        }

        // Did we get the extra row? If so, there are more older messages.
        // With DESC ordering, the extra row is the *oldest* one at the end.
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        // Results are newest-first; reverse so the caller gets oldest-first.
        return ['messages' => array_reverse($rows), 'has_more' => $hasMore];
    }

    /**
     * Return all messages newer than $afterId (for polling).
     *
     * Returns only the rows the client hasn't seen yet — keeps polling cheap.
     *
     * @return MessageResponse[]
     */
    public function getAfter(int $conversationId, int $afterId): array
    {
        $readAtSelect = $this->hasReadAtColumn() ? 'read_at' : 'NULL AS read_at';
        $stmt = $this->db->prepare(
            "SELECT id, user_id, name, message, created_at, {$readAtSelect}
             FROM messages
             WHERE conversation_id = ? AND id > ?
             ORDER BY id ASC"
        );
        $stmt->bind_param('ii', $conversationId, $afterId);
        $stmt->execute();
        $result = $stmt->get_result();

        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = new MessageResponse(
                (int) $row['id'],
                $row['user_id'] !== null ? (int) $row['user_id'] : null,
                (string) $row['name'],
                (string) $row['message'],
                (string) $row['created_at'],
                $row['read_at'] !== null ? (string) $row['read_at'] : null,
            );
        }
        return $messages;
    }

    /**
     * Stamp read_at = NOW() on all messages from other users that the current
     * user hasn't read yet.  This is what turns the sender's ticks blue.
     *
     * Only touches rows with read_at IS NULL — safe to call on every fetch.
     */
    public function markAsRead(int $conversationId, int $readerUserId): void
    {
        if (!$this->hasReadAtColumn()) {
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE messages
             SET read_at = NOW()
             WHERE conversation_id = ?
               AND user_id        != ?
               AND read_at        IS NULL'
        );
        $stmt->bind_param('ii', $conversationId, $readerUserId);
        $stmt->execute();
    }

    /**
     * Return the highest message ID sent by $senderUserId in $conversationId
     * that has already been read (read_at IS NOT NULL).
     *
     * The sender's client uses this to know which of their outgoing messages
     * can now show blue ticks — all messages with id <= returned value.
     * Returns null if none of the sender's messages have been read yet.
     */
    public function getReadThrough(int $conversationId, int $senderUserId): ?int
    {
        if (!$this->hasReadAtColumn()) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT MAX(id) AS read_through
             FROM messages
             WHERE conversation_id = ?
               AND user_id         = ?
               AND read_at        IS NOT NULL'
        );
        $stmt->bind_param('ii', $conversationId, $senderUserId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row['read_through'] !== null ? (int) $row['read_through'] : null;
    }

    /**
     * Find a single message by its ID and return it as a Message entity.
     * Returns null if not found.
     */
    public function findById(int $id): ?Message
    {
        $stmt = $this->db->prepare(
            'SELECT id, conversation_id, user_id, name, message, created_at
             FROM messages WHERE id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            return null;
        }
        return new Message(
            (int) $row['id'],
            (int) $row['conversation_id'],
            $row['user_id'] !== null ? (int) $row['user_id'] : null,
            (string) $row['name'],
            (string) $row['message'],
            (string) $row['created_at'],
        );
    }

    /**
     * Insert a user message. Returns the new message's ID.
     */
    public function insert(int $conversationId, int $userId, string $name, string $text): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO messages (conversation_id, user_id, name, message, created_at)
             VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->bind_param('iiss', $conversationId, $userId, $name, $text);
        $stmt->execute();
        return (int) $this->db->insert_id;
    }

    /**
     * Insert a bot reply (user_id is NULL to indicate it came from a bot).
     */
    public function insertBotReply(int $conversationId, string $botName, string $reply): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO messages (conversation_id, user_id, name, message, created_at)
             VALUES (?, NULL, ?, ?, NOW())'
        );
        $stmt->bind_param('iss', $conversationId, $botName, $reply);
        $stmt->execute();
    }

    /**
     * Update the text of a message the user owns.
     * Returns true if a row was actually updated, false if nothing matched.
     */
    public function update(int $messageId, int $userId, string $newText): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE messages SET message = ? WHERE id = ? AND user_id = ?'
        );
        $stmt->bind_param('sii', $newText, $messageId, $userId);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }

    /**
     * Delete a message the user owns.
     * Returns true if a row was actually deleted, false if nothing matched.
     */
    public function delete(int $messageId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM messages WHERE id = ? AND user_id = ?'
        );
        $stmt->bind_param('ii', $messageId, $userId);
        $stmt->execute();
        return $stmt->affected_rows > 0;
    }
}
