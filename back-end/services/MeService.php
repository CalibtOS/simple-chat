<?php
declare(strict_types=1);

/**
 * Service: business logic for the "who am I?" endpoint.
 *
 * Rule: if the user is logged in return their profile; otherwise return a
 * guest response.  This decision belongs here, not in the controller.
 */
final class MeService
{
    public function __construct(private readonly UserRepository $users) {}

    /**
     * Build the MeResponse DTO from fresh database data.
     * The session only provides the user_id key; the repository fetches real data.
     */
    public function getMePayload(): MeResponse
    {
        $user = $this->users->getCurrentFromSession();

        if ($user instanceof User) {
            return new MeResponse(true, $user->id, $user->name, $user->email);
        }

        return new MeResponse(false, null, null, null);
    }
}
