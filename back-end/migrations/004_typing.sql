-- Migration 004 — Typing indicators
-- Run once in phpMyAdmin against the `chat` database.

-- Each row means "this user is currently typing in this conversation".
-- updated_at is refreshed on every keystroke heartbeat.
-- A row is considered "live" only if updated_at is within the last 5 seconds
-- — there is no explicit delete; old rows simply become stale and are ignored.
--
-- The composite PRIMARY KEY on (user_id, conversation_id) means each user can
-- only have one row per conversation, so INSERT ... ON DUPLICATE KEY UPDATE
-- acts as a clean upsert: create the row the first time, refresh it after that.

CREATE TABLE typing_indicators (
    user_id         INT      NOT NULL,
    conversation_id INT      NOT NULL,
    updated_at      DATETIME NOT NULL,
    PRIMARY KEY (user_id, conversation_id)
);
