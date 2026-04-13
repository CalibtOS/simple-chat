<?php
declare(strict_types=1);

/**
 * Repository: all database access for the `users` table.
 *
 * Key rules:
 * - Never returns a password hash to callers (except findByEmailForAuth, used only by AuthService).
 * - Always returns User entities or primitives — never raw arrays to the service layer.
 * - Uses prepared statements for every query that involves user input.
 */
final class UserRepository
{
    public function __construct(private readonly mysqli $db) {}

    /**
     * Fetch a user by primary key.
     * Returns null if not found.
     */
    public function findById(int $id): ?User
    {
        $stmt = $this->db->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) {
            return null;
        }
        return new User((int) $row['id'], (string) $row['name'], (string) $row['email']);
    }

    /**
     * Used exclusively by AuthService for login credential verification.
     * Returns the raw row including password_hash — never expose this outside AuthService.
     *
     * @return array<string, mixed>|null
     */
    public function findByEmailForAuth(string $email): ?array
    {
        $stmt = $this->db->prepare('SELECT id, name, email, password_hash FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * Check whether an email is already taken.
     */
    public function existsByEmail(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() !== null;
    }

    /**
     * Insert a new user. Returns the new user's ID.
     */
    public function create(string $email, string $passwordHash, string $name): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO users (email, password_hash, name, created_at) VALUES (?, ?, ?, NOW())'
        );
        $stmt->bind_param('sss', $email, $passwordHash, $name);
        $stmt->execute();
        return (int) $this->db->insert_id;
    }

    /**
     * Get the current user from the active session, then fetch fresh data from the DB.
     * The session only carries user_id as a key — actual data always comes from the database.
     */
    public function getCurrentFromSession(): ?User
    {
        if (!isLoggedIn()) {
            return null;
        }
        return $this->findById((int) $_SESSION['user_id']);
    }

    /**
     * Find a user by email (safe — no password hash returned).
     */
    public function findByEmail(string $email): ?User
    {
        $stmt = $this->db->prepare('SELECT id, name, email FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) return null;
        return new User((int) $row['id'], (string) $row['name'], (string) $row['email']);
    }

    /**
     * Store a password-reset token and its expiry for a user.
     */
    public function setResetToken(int $userId, string $token, string $expiresAt): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?'
        );
        $stmt->bind_param('ssi', $token, $expiresAt, $userId);
        $stmt->execute();
    }

    /**
     * Look up a user by a valid (non-expired) reset token.
     * Returns null if the token doesn't exist or is expired.
     */
    public function findByResetToken(string $token): ?User
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, email FROM users
             WHERE reset_token = ? AND reset_token_expires > NOW() LIMIT 1'
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row) return null;
        return new User((int) $row['id'], (string) $row['name'], (string) $row['email']);
    }

    /**
     * Update a user's password hash (used after a successful reset).
     */
    public function updatePassword(int $userId, string $passwordHash): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $stmt->bind_param('si', $passwordHash, $userId);
        $stmt->execute();
    }

    /**
     * Nullify the reset token after it has been used.
     * A token is single-use — once the password is changed it must be cleared.
     */
    public function clearResetToken(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET reset_token = NULL, reset_token_expires = NULL WHERE id = ?'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
    }

    // ─── 2FA helpers ─────────────────────────────────────────────────────────────

    /**
     * Read the 2FA fields for a user without loading the full entity.
     *
     * @return array{enabled: bool, secret: string|null}
     */
    public function getTwoFactorInfo(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT two_factor_enabled, two_factor_secret FROM users WHERE id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return [
            'enabled' => (bool) ($row['two_factor_enabled'] ?? false),
            'secret'  => $row['two_factor_secret'] ?? null,
        ];
    }

    /** Store the TOTP secret (but do NOT yet set enabled = 1 — that requires code verification). */
    public function setTwoFactorSecret(int $userId, string $secret): void
    {
        $stmt = $this->db->prepare('UPDATE users SET two_factor_secret = ? WHERE id = ?');
        $stmt->bind_param('si', $secret, $userId);
        $stmt->execute();
    }

    /** Mark 2FA as active after the user has verified their first code. */
    public function enableTwoFactor(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE users SET two_factor_enabled = 1 WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
    }

    /** Turn 2FA off and wipe the secret. */
    public function disableTwoFactor(int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = ?'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
    }

    /**
     * Check whether an email is already used by a *different* user.
     * We need this on profile update: if the user submits their own existing email
     * we must NOT reject it — only reject if someone else owns it.
     */
    public function emailTakenByOther(string $email, int $currentUserId): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1');
        $stmt->bind_param('si', $email, $currentUserId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() !== null;
    }

    /**
     * Update a user's name and email, then refresh the session so the UI
     * shows the new name immediately without requiring a re-login.
     */
    public function updateProfile(int $id, string $name, string $email): void
    {
        $stmt = $this->db->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?');
        $stmt->bind_param('ssi', $name, $email, $id);
        $stmt->execute();

        // Keep the session name in sync — the sidebar reads it on page load.
        if (isset($_SESSION['user_name'])) {
            $_SESSION['user_name'] = $name;
        }
    }
}
