-- Migration 002 — Settings, password reset, 2FA, and user-to-user chat
-- Run this once in phpMyAdmin (or any MySQL client) against your simple_chat database.
-- Safe to inspect — it only adds columns and an index; no data is deleted.

-- ─── 1. Add columns to the users table ──────────────────────────────────────
--
-- reset_token          : a random 64-char hex string emailed to the user when
--                        they request a password reset. NULL means no active request.
-- reset_token_expires  : the token becomes invalid after this timestamp.
--                        We'll set it to NOW() + 15 minutes when we generate it.
-- two_factor_secret    : the TOTP secret key (base32) generated once per user
--                        when they enable 2FA. NULL means 2FA is not set up.
-- two_factor_enabled   : 0 = 2FA off, 1 = 2FA on.
--                        A user can have a secret stored but 2FA still off
--                        (during the setup/confirm flow).

ALTER TABLE users
  ADD COLUMN reset_token         VARCHAR(64)  NULL DEFAULT NULL,
  ADD COLUMN reset_token_expires DATETIME     NULL DEFAULT NULL,
  ADD COLUMN two_factor_secret   VARCHAR(32)  NULL DEFAULT NULL,
  ADD COLUMN two_factor_enabled  TINYINT(1)   NOT NULL DEFAULT 0;

-- ─── 2. Make bot_id nullable on the conversations table ──────────────────────
--
-- Today every conversation row MUST reference a bot (NOT NULL constraint).
-- Making it nullable lets us store user-to-user conversations:
--   bot_id = <some id>  → conversation with a bot
--   bot_id = NULL       → conversation between two real users
--
-- The conversation_members join table already tracks who is in each conversation,
-- so no new table is needed for user-to-user chat.

ALTER TABLE conversations
  MODIFY COLUMN bot_id INT NULL DEFAULT NULL;

-- ─── 3. Add an index on reset_token ─────────────────────────────────────────
--
-- When a user clicks the reset link we look up the token in the DB.
-- Without an index MySQL would scan every row. With it, the lookup is instant.

CREATE INDEX idx_users_reset_token ON users (reset_token);
