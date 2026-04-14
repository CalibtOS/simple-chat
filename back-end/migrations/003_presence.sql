-- Migration 003 — Online / last seen presence
-- Run this once in phpMyAdmin against your `chat` database.
-- Safe: only adds one nullable column. No existing data is changed.

-- last_seen_at records the most recent moment the user made an authenticated
-- request (any API call that requires login).  NULL means the user has never
-- made a request since this migration was applied (treated as "offline").
--
-- We deliberately use NULL rather than a default timestamp so we can
-- distinguish "never seen" from "seen a long time ago".

ALTER TABLE users
  ADD COLUMN last_seen_at DATETIME NULL DEFAULT NULL;

-- Index lets the presence query ( WHERE last_seen_at >= NOW() - 60s ) skip a
-- full table scan when the users table grows large.
CREATE INDEX idx_users_last_seen ON users (last_seen_at);
