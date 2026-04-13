<?php
declare(strict_types=1);

/**
 * Repository: all database access for the `messages` table.
 * Uses prepared statements for every query that involves external input.
 */
final class MessageRepository
{
    public function __construct(private readonly mysqli $db) {}

    /**
     * Return all messages for a conversation as MessageResponse DTOs,
     * ordered oldest-first (for display in chat).
     *
     * @return MessageResponse[]
     */
    public function getByConversation(int $conversationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, user_id, name, message, created_at
             FROM messages
             WHERE conversation_id = ?
             ORDER BY created_at ASC'
        );
        $stmt->bind_param('i', $conversationId);
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
            );
        }
        return $messages;
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
