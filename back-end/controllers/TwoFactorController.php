<?php
declare(strict_types=1);

/**
 * Controller: 2FA endpoints.
 *
 *   GET    /api/2fa/setup    → setup()       — generate secret, return otpauth URL
 *   POST   /api/2fa/enable   → enable()      — verify first code, activate 2FA
 *   DELETE /api/2fa          → disable()     — turn off 2FA
 *   POST   /api/auth/2fa-verify → verifyLogin() — complete login after entering code
 */
final class TwoFactorController
{
    public function __construct(
        private readonly TwoFactorService $service,
        private readonly UserRepository   $users,
    ) {}

    // ─── GET /api/2fa/setup ───────────────────────────────────────────────────

    public function setup(Request $request): void
    {
        $user   = $this->users->getCurrentFromSession();
        $result = $this->service->setup($user->id, $user->email);

        json_response([
            'secret'      => $result['secret'],
            'otpauth_url' => $result['otpauth_url'],
        ]);
    }

    // ─── POST /api/2fa/enable   { code } ─────────────────────────────────────

    public function enable(Request $request): void
    {
        $user = $this->users->getCurrentFromSession();
        $code = (string) ($request->body['code'] ?? '');

        try {
            $this->service->enable($user->id, $code);
        } catch (InvalidArgumentException $e) {
            json_response(['error' => $e->getMessage()], 422);
        }

        json_response(['success' => true]);
    }

    // ─── DELETE /api/2fa ──────────────────────────────────────────────────────

    public function disable(Request $request): void
    {
        $user = $this->users->getCurrentFromSession();
        $this->service->disable($user->id);
        json_response(['success' => true]);
    }

    // ─── POST /api/auth/2fa-verify   { code } ────────────────────────────────
    //
    // Called from the two-factor.html page.
    // The user is NOT yet fully logged in — only $_SESSION['pending_2fa_user_id']
    // is set.  On success this endpoint promotes them to a full session.

    public function verifyLogin(Request $request): void
    {
        $code = (string) ($request->body['code'] ?? '');

        try {
            $this->service->verifyLogin($code);
        } catch (InvalidArgumentException $e) {
            json_response(['error' => $e->getMessage()], 422);
        }

        json_response(['success' => true, 'redirect' => '../front-end/html/chat.html']);
    }

    // ─── GET /api/2fa/status ─────────────────────────────────────────────────
    // Returns whether 2FA is currently enabled for the logged-in user.

    public function status(Request $request): void
    {
        $user = $this->users->getCurrentFromSession();
        $info = $this->users->getTwoFactorInfo($user->id);
        json_response(['enabled' => $info['enabled']]);
    }
}
