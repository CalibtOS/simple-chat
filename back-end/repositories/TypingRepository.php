<?php
declare(strict_types=1);

/**
 * Repository: all database access for the `typing_indicators` table.
 */
final class TypingRepository
{
    public function __construct(private readonly mysqli $db) {}

    /**
     * Record (or refresh) a typing heartbeat for a user in a conversation.
     *
     * INSERT ... ON DUPLICATE KEY UPDATE is an "upsert":
     *   - First heartbeat  → inserts a new row.
     *   - Every heartbeat after that → updates updated_at in the existing row.
     * The composite PRIMARY KEY (user_id, conversation_id) guarantees there is
     * never more than one row per user per conversation.
     */
    public function upsert(int $userId, int $conversationId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO typing_indicators (user_id, conversation_id, updated_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE updated_at = NOW()'
        );
        $stmt->bind_param('ii', $userId, $conversationId);
        $stmt->execute();
    }

    /**
     * Return the names of users who are currently typing in a conversation.
     *
     * "Currently typing" means their last heartbeat was within 5 seconds.
     * We exclude the requesting user — you don't need to see yourself typing.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function getActive(int $conversationId, int $excludeUserId): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.name
             FROM typing_indicators t
             JOIN users u ON u.id = t.user_id
             WHERE t.conversation_id = ?
               AND t.user_id        != ?
               AND t.updated_at     >= DATE_SUB(NOW(), INTERVAL 5 SECOND)'
        );
        $stmt->bind_param('ii', $conversationId, $excludeUserId);
        $stmt->execute();
        $result = $stmt->get_result();

        $typers = [];
        while ($row = $result->fetch_assoc()) {
            $typers[] = ['id' => (int) $row['id'], 'name' => (string) $row['name']];
        }
        return $typers;
    }
}
