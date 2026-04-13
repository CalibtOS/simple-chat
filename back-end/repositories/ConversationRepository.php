<?php
declare(strict_types=1);

/**
 * Repository: all database access for the `conversations` and `conversation_members` tables.
 */
final class ConversationRepository
{
    public function __construct(private readonly mysqli $db) {}

    /**
     * Return a map of bot_id => conversation_id for conversations
     * that already exist for the given user and bot IDs.
     *
     * @param  int[] $botIds
     * @return array<int, int>
     */
    public function getExistingForUser(int $userId, array $botIds): array
    {
        $list   = implode(',', array_map('intval', $botIds));
        $result = $this->db->query(
            "SELECT bot_id, id FROM conversations
             WHERE created_by_user_id = $userId AND bot_id IN ($list)"
        );
        $map = [];
        while ($row = $result->fetch_assoc()) {
            $map[(int) $row['bot_id']] = (int) $row['id'];
        }
        return $map;
    }

    /**
     * Create a new conversation and return its ID.
     */
    public function create(string $title, int $userId, int $botId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO conversations (title, created_by_user_id, bot_id, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->bind_param('sii', $title, $userId, $botId);
        $stmt->execute();
        return (int) $this->db->insert_id;
    }

    /**
     * Ensure the user is a member of each conversation in the list.
     * Uses INSERT IGNORE so duplicate membership rows are skipped silently.
     *
     * @param int[] $conversationIds
     */
    public function ensureMembership(int $userId, array $conversationIds): void
    {
        $values = implode(',', array_map(
            fn(int $cid) => "($cid, $userId, NOW())",
            $conversationIds
        ));
        $this->db->query(
            "INSERT IGNORE INTO conversation_members (conversation_id, user_id, created_at) VALUES $values"
        );
    }

    /**
     * Check whether a user is a member of a conversation.
     */
    public function isMember(int $conversationId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM conversation_members
             WHERE conversation_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->bind_param('ii', $conversationId, $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() !== null;
    }

    /**
     * Return the ID of the user's oldest conversation, or null if none exist.
     */
    public function getOldestForUser(int $userId): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT c.id FROM conversations c
             JOIN conversation_members cm ON cm.conversation_id = c.id
             WHERE cm.user_id = ?
             ORDER BY c.created_at ASC LIMIT 1'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (int) $row['id'] : null;
    }

    /**
     * Find an existing user-to-user conversation between two users.
     * Returns the conversation ID, or null if none exists yet.
     *
     * How it works:
     *   We join conversation_members twice — once for userA, once for userB.
     *   The only row that satisfies both joins on the same conversation_id
     *   is a conversation both users are in.  bot_id IS NULL means it is
     *   a user-to-user conversation, not a bot conversation.
     */
    public function findBetweenUsers(int $userA, int $userB): ?int
    {
        $stmt = $this->db->prepare(
            'SELECT c.id
             FROM conversations c
             JOIN conversation_members m1 ON m1.conversation_id = c.id AND m1.user_id = ?
             JOIN conversation_members m2 ON m2.conversation_id = c.id AND m2.user_id = ?
             WHERE c.bot_id IS NULL
             LIMIT 1'
        );
        $stmt->bind_param('ii', $userA, $userB);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (int) $row['id'] : null;
    }

    /**
     * Create a new user-to-user conversation and add both users as members.
     * Wrapped in a transaction: either both inserts succeed or neither does.
     */
    public function createUserConversation(int $userA, int $userB): int
    {
        $this->db->begin_transaction();
        try {
            // bot_id is NULL — this is a user-to-user conversation.
            $stmt = $this->db->prepare(
                'INSERT INTO conversations (title, created_by_user_id, bot_id, created_at)
                 VALUES (?, ?, NULL, NOW())'
            );
            $title = 'Direct Message';
            $stmt->bind_param('si', $title, $userA);
            $stmt->execute();
            $convId = (int) $this->db->insert_id;

            // Add both users as members in one INSERT.
            $this->db->query(
                "INSERT INTO conversation_members (conversation_id, user_id, created_at)
                 VALUES ($convId, $userA, NOW()), ($convId, $userB, NOW())"
            );

            $this->db->commit();
            return $convId;
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Return all other users with their DM conversation info (if one exists).
     *
     * The LEFT JOIN subquery finds any user-to-user conversation where
     * the current user is a member.  The outer query matches each other
     * user to that conversation via the second member slot.
     *
     * Result is sorted by last message time descending so active
     * conversations appear first, then alphabetically.
     *
     * @return UserResponse[]
     */
    public function getUsersWithConversations(int $currentUserId): array
    {
        // Rewritten to avoid correlated subqueries referencing a derived table,
        // which older MySQL/MariaDB versions (XAMPP) do not support.
        // Instead we use a separate LEFT JOIN for the latest message per conversation.
        $stmt = $this->db->prepare(
            "SELECT
                 u.id,
                 u.name,
                 conv.id           AS conversation_id,
                 lm.message        AS last_message,
                 lm.created_at     AS last_message_at
             FROM users u
             LEFT JOIN (
                 SELECT c.id, cm2.user_id AS other_uid
                 FROM conversations c
                 JOIN conversation_members cm1
                      ON cm1.conversation_id = c.id AND cm1.user_id = ?
                 JOIN conversation_members cm2
                      ON cm2.conversation_id = c.id AND cm2.user_id != ?
                 WHERE c.bot_id IS NULL
             ) conv ON conv.other_uid = u.id
             LEFT JOIN (
                 SELECT m.conversation_id, m.message, m.created_at
                 FROM messages m
                 INNER JOIN (
                     SELECT conversation_id, MAX(created_at) AS max_at
                     FROM messages
                     GROUP BY conversation_id
                 ) latest ON latest.conversation_id = m.conversation_id
                          AND m.created_at = latest.max_at
             ) lm ON lm.conversation_id = conv.id
             WHERE u.id != ?
             ORDER BY lm.created_at DESC, u.name ASC"
        );
        $stmt->bind_param('iii', $currentUserId, $currentUserId, $currentUserId);
        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = new UserResponse(
                (int) $row['id'],
                (string) $row['name'],
                $row['conversation_id'] !== null ? (int) $row['conversation_id'] : null,
                $row['last_message'],
                $row['last_message_at'],
            );
        }
        return $items;
    }

    /**
     * Return all conversations for a user with joined bot and last-message info,
     * already shaped into ConversationResponse DTOs.
     *
     * @return ConversationResponse[]
     */
    public function getConversationsWithDetails(int $userId): array
    {
        $result = $this->db->query(
            "SELECT
                 c.id,
                 c.title,
                 c.created_at,
                 b.slug  AS bot_slug,
                 b.name  AS bot_name,
                 (SELECT m.message   FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message,
                 (SELECT m.created_at FROM messages m WHERE m.conversation_id = c.id ORDER BY m.created_at DESC LIMIT 1) AS last_message_at
             FROM conversations c
             JOIN conversation_members cm ON cm.conversation_id = c.id
             JOIN bots b ON b.id = c.bot_id
             WHERE cm.user_id = $userId
               AND b.slug IN ('ahmed','islam','ferhat')
             ORDER BY FIELD(b.slug, 'ahmed','islam','ferhat')"
        );

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = new ConversationResponse(
                (int) $row['id'],
                (string) $row['title'],
                (string) $row['bot_slug'],
                (string) $row['bot_name'],
                $row['last_message'],
                $row['last_message_at'],
                (string) $row['created_at'],
            );
        }
        return $items;
    }
}
