<?php
declare(strict_types=1);

/**
 * Repository: all database access for the `bots` table.
 */
final class BotRepository
{
    public function __construct(private readonly mysqli $db) {}

    /**
     * Seed the three default bots if they don't exist yet.
     * Uses INSERT IGNORE so running this multiple times is safe.
     */
    public function seedDefaultBots(): void
    {
        $this->db->query(
            "INSERT IGNORE INTO bots (slug, name, reply_template) VALUES
             ('ahmed',  'Ahmed',  'Hey! Thanks for your message. Talk to me more!'),
             ('islam',  'Islam',  'Hey! I am Islam. Tell me more about your day!'),
             ('ferhat', 'Ferhat', 'Hey! I am Ferhat. What are you working on today?')"
        );
    }

    /**
     * Return the three default bots in display order.
     *
     * @return Bot[]
     */
    public function getDefaultBots(): array
    {
        $result = $this->db->query(
            "SELECT id, slug, name, reply_template FROM bots
             WHERE slug IN ('ahmed','islam','ferhat')
             ORDER BY FIELD(slug, 'ahmed', 'islam', 'ferhat')"
        );
        $bots = [];
        while ($row = $result->fetch_assoc()) {
            $bots[] = new Bot((int) $row['id'], $row['slug'], $row['name'], $row['reply_template']);
        }
        return $bots;
    }

    /**
     * Find a single bot by its slug (e.g. 'ahmed').
     */
    public function findBySlug(string $slug): ?Bot
    {
        $stmt = $this->db->prepare(
            'SELECT id, slug, name, reply_template FROM bots WHERE slug = ? LIMIT 1'
        );
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            return null;
        }
        return new Bot((int) $row['id'], $row['slug'], $row['name'], $row['reply_template']);
    }

    /**
     * Find the bot linked to a specific conversation.
     */
    public function findByConversationId(int $conversationId): ?Bot
    {
        $stmt = $this->db->prepare(
            'SELECT b.id, b.slug, b.name, b.reply_template
             FROM conversations c
             JOIN bots b ON b.id = c.bot_id
             WHERE c.id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $conversationId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            return null;
        }
        return new Bot((int) $row['id'], $row['slug'], $row['name'], $row['reply_template']);
    }
}
