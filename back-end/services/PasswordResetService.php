<?php
declare(strict_types=1);

/**
 * Service: business logic for the password-reset flow.
 *
 * Two steps:
 *   1. requestReset()  — user submits email → token created, email sent
 *   2. resetPassword() — user submits token + new password → password updated
 *
 * Security notes:
 * - We always respond with the same success message regardless of whether
 *   the email exists.  This prevents user-enumeration attacks.
 * - Tokens are 32 random bytes (64 hex chars) — not guessable.
 * - Tokens expire after 15 minutes.
 * - Tokens are single-use: cleared immediately after a successful reset.
 */
final class PasswordResetService
{
    private const TOKEN_TTL_MINUTES = 15;

    public function __construct(
        private readonly UserRepository $users,
    ) {}

    /**
     * Generate a reset token for the given email and send it by email.
     *
     * Always returns true (even if the email doesn't exist) so callers
     * can't determine whether an account is registered.
     */
    public function requestReset(string $email): true
    {
        $user = $this->users->findByEmail($email);

        if ($user !== null) {
            // 32 random bytes → 64-char hex string, URL-safe without encoding.
            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_TTL_MINUTES * 60);

            $this->users->setResetToken($user->id, $token, $expiresAt);

            // Build the reset URL.  The front-end page reads ?token= from the query string.
            $resetUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                . '/simple-chat/front-end/html/reset-password.html?token=' . $token;

            $body = <<<TEXT
            Hi {$user->name},

            Someone requested a password reset for your Chatty account.

            Click the link below to set a new password (valid for 15 minutes):

            {$resetUrl}

            If you did not request this, you can safely ignore this email.

            — Chatty
            TEXT;

            Mailer::send($user->email, 'Reset your Chatty password', $body);
        }

        return true;
    }

    /**
     * Validate a reset token and update the password.
     *
     * Returns true on success.
     * Throws InvalidArgumentException on validation failure.
     *
     * @throws InvalidArgumentException
     */
    public function resetPassword(string $token, string $password, string $confirm): true
    {
        if ($password === '') {
            throw new InvalidArgumentException('Password is required.');
        }
        if (strlen($password) < 6) {
            throw new InvalidArgumentException('Password must be at least 6 characters.');
        }
        if ($password !== $confirm) {
            throw new InvalidArgumentException('Passwords do not match.');
        }

        $user = $this->users->findByResetToken($token);
        if ($user === null) {
            throw new InvalidArgumentException('This reset link is invalid or has expired.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->users->updatePassword($user->id, $hash);
        $this->users->clearResetToken($user->id);   // single-use

        return true;
    }
}
