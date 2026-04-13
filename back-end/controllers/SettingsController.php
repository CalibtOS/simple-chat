<?php
declare(strict_types=1);

/**
 * Controller: settings endpoints.
 *
 * Endpoints handled:
 *   GET   /api/settings   → show()   — return current name + email for the form
 *   PATCH /api/settings   → update() — apply name / email changes
 */
final class SettingsController
{
    public function __construct(
        private readonly SettingsService $service,
        private readonly UserRepository  $users,
    ) {}

    // -------------------------------------------------------------------------
    // GET /api/settings
    // -------------------------------------------------------------------------

    public function show(Request $request): void
    {
        $user = $this->users->getCurrentFromSession();

        json_response([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
        ]);
    }

    // -------------------------------------------------------------------------
    // PATCH /api/settings
    // -------------------------------------------------------------------------

    public function update(Request $request): void
    {
        $user  = $this->users->getCurrentFromSession();
        $name  = (string) ($request->body['name']  ?? '');
        $email = (string) ($request->body['email'] ?? '');

        try {
            $result = $this->service->update($user->id, $name, $email);
        } catch (InvalidArgumentException $e) {
            json_response(['error' => $e->getMessage()], 422);
        }

        json_response($result->toArray());
    }
}
