<?php
declare(strict_types=1);

/**
 * Service: business logic for the 2FA setup and login-verification flows.
 *
 * Setup flow (settings page):
 *   1. setup()  — generates a new secret, stores it (enabled = 0 still)
 *   2. enable() — user proves they scanned it by entering a valid code → enabled = 1
 *   3. disable() — wipes secret + sets enabled = 0
 *
 * Login flow (two-factor.html):
 *   4. verifyLogin() — checks the code, completes the session
 */
final class TwoFactorService
{
    public function __construct(
        private readonly UserRepository $users,
    ) {}

    /**
     * Generate a new TOTP secret for the user and store it in the DB.
     * 2FA is NOT yet enabled — the user must call enable() to activate it.
     *
     * Returns an array with the data the front-end needs to show the setup UI.
     *
     * @return array{secret: string, otpauth_url: string}
     */
    public function setup(int $userId, string $userEmail): array
    {
        $secret = Totp::generateSecret();
        $this->users->setTwoFactorSecret($userId, $secret);

        return [
            'secret'      => $secret,
            'otpauth_url' => Totp::otpauthUrl($secret, $userEmail),
        ];
    }

    /**
     * Activate 2FA after the user enters a valid code to prove their app is set up.
     *
     * @throws InvalidArgumentException if no secret is stored, or the code is wrong
     */
    public function enable(int $userId, string $code): void
    {
        $info = $this->users->getTwoFactorInfo($userId);

        if ($info['secret'] === null) {
            throw new InvalidArgumentException('No 2FA secret found. Please start setup again.');
        }

        if (!Totp::verify($info['secret'], $code)) {
            throw new InvalidArgumentException('Invalid code. Make sure your authenticator app is set up correctly.');
        }

        $this->users->enableTwoFactor($userId);
    }

    /**
     * Turn off 2FA and wipe the secret.
     */
    public function disable(int $userId): void
    {
        $this->users->disableTwoFactor($userId);
    }

    /**
     * Verify the TOTP code during login (the second factor).
     * Checks $_SESSION['pending_2fa_user_id'], validates the code,
     * then promotes the session to fully authenticated.
     *
     * @throws InvalidArgumentException if the pending session is missing or the code is wrong
     */
    public function verifyLogin(string $code): User
    {
        $pendingId = $_SESSION['pending_2fa_user_id'] ?? null;

        if (!$pendingId) {
            throw new InvalidArgumentException('No pending authentication. Please log in again.');
        }

        $user = $this->users->findById((int) $pendingId);
        if ($user === null) {
            throw new InvalidArgumentException('User not found.');
        }

        $info = $this->users->getTwoFactorInfo($user->id);

        if (!$info['enabled'] || $info['secret'] === null) {
            throw new InvalidArgumentException('2FA is not set up for this account.');
        }

        if (!Totp::verify($info['secret'], $code)) {
            throw new InvalidArgumentException('Invalid code. Please try again.');
        }

        // Promote to full session.
        unset($_SESSION['pending_2fa_user_id']);
        $_SESSION['user_id']    = $user->id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_name']  = $user->name;

        return $user;
    }
}
