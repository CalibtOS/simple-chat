<?php
declare(strict_types=1);

/**
 * Controller: auth endpoints (login, register, logout).
 *
 * These endpoints are form-based (not JSON APIs), so they respond with
 * HTTP redirects rather than JSON bodies.
 *
 * Responsibility:
 * - Read and validate raw POST input.
 * - Call AuthService with clean values.
 * - Translate the service result into a redirect or error redirect.
 * - Set session variables on successful login.
 */
final class AuthController
{
    public function __construct(
        private readonly AuthService          $service,
        private readonly PasswordResetService $reset,
        private readonly UserRepository       $users,
    ) {}

    public function login(Request $request): void
    {
        // If already logged in, skip straight to the chat.
        if (isLoggedIn()) {
            header('Location: ../front-end/html/chat.html');
            exit;
        }

        $email    = trim((string) ($request->body['email']    ?? ''));
        $password =       (string) ($request->body['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->redirectWithError('../front-end/html/login.html', 'Email and password are required.');
        }

        $user = $this->service->login($email, $password);

        if (!$user instanceof User) {
            $this->redirectWithError('../front-end/html/login.html', 'Invalid email or password.');
        }

        // If the user has 2FA enabled, put them in a "pending" state and send
        // them to the 2FA verification page instead of completing the login.
        $twoFa = $this->users->getTwoFactorInfo($user->id);
        if ($twoFa['enabled']) {
            $_SESSION['pending_2fa_user_id'] = $user->id;
            header('Location: ../front-end/html/two-factor.html');
            exit;
        }

        // Persist only the ID in the session — data always comes from the DB.
        $_SESSION['user_id']    = $user->id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_name']  = $user->name;

        header('Location: ../front-end/html/chat.html');
        exit;
    }

    public function register(Request $request): void
    {
        $email    = trim((string) ($request->body['email']            ?? ''));
        $password =       (string) ($request->body['password']         ?? '');
        $confirm  =       (string) ($request->body['password_confirm'] ?? '');
        $name     = trim((string) ($request->body['name']             ?? ''));

        $error = $this->validateRegistration($email, $password, $confirm, $name);

        if ($error !== null) {
            $this->redirectWithError('../front-end/html/register.html', $error);
        }

        $result = $this->service->register($email, $password, $name);

        if (is_string($result)) {
            // AuthService returned an error message (e.g. duplicate email).
            $this->redirectWithError('../front-end/html/register.html', $result);
        }

        header('Location: ../front-end/html/login.html?registered=1');
        exit;
    }

    public function logout(Request $request): void
    {
        $_SESSION = [];
        session_destroy();
        header('Location: ../front-end/html/login.html');
        exit;
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/forgot-password   { email }
    // -------------------------------------------------------------------------

    public function forgotPassword(Request $request): void
    {
        $email = trim((string) ($request->body['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['error' => 'A valid email address is required.'], 422);
        }

        $this->reset->requestReset($email);

        // Always return the same response — don't reveal whether the email exists.
        json_response(['message' => 'If that email is registered you will receive a reset link shortly.']);
    }

    // -------------------------------------------------------------------------
    // POST /api/auth/reset-password   { token, password, password_confirm }
    // -------------------------------------------------------------------------

    public function resetPassword(Request $request): void
    {
        $token    = (string) ($request->body['token']            ?? '');
        $password = (string) ($request->body['password']         ?? '');
        $confirm  = (string) ($request->body['password_confirm'] ?? '');

        try {
            $this->reset->resetPassword($token, $password, $confirm);
        } catch (InvalidArgumentException $e) {
            json_response(['error' => $e->getMessage()], 422);
        }

        json_response(['message' => 'Password updated. You can now log in.']);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function validateRegistration(
        string $email,
        string $password,
        string $confirm,
        string $name,
    ): ?string {
        if ($email === '' || $password === '' || $name === '') {
            return 'All fields are required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email address.';
        }
        if (strlen($password) < 6) {
            return 'Password must be at least 6 characters.';
        }
        if ($password !== $confirm) {
            return 'Passwords do not match.';
        }
        return null;
    }

    /** Redirect to a URL with an ?error= query param, then exit. */
    private function redirectWithError(string $url, string $message): never
    {
        header('Location: ' . $url . '?error=' . urlencode($message));
        exit;
    }
}
