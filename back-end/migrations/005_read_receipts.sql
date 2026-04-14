-- Migration 005 — Read receipts
-- Run once in phpMyAdmin against the `chat` database.

-- read_at is NULL until the recipient fetches the conversation.
-- Once the recipient loads or polls the conversation, all unread messages
-- from the other user are stamped with the current timestamp.
-- The sender sees the tick turn blue on their next 5-second poll.

ALTER TABLE messages ADD COLUMN read_at DATETIME NULL DEFAULT NULL;

-- Index speeds up the markAsRead UPDATE (conversation + unread filter)
-- and the getReadThrough MAX(id) query.
CREATE INDEX idx_messages_read ON messages (conversation_id, user_id, read_at);
