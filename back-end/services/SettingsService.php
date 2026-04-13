<?php
declare(strict_types=1);

/**
 * Service: business logic for user profile settings.
 *
 * Rules owned here:
 * - Name and email must not be empty.
 * - Email must be a valid format.
 * - Email must not be taken by another user.
 *
 * The controller handles HTTP (reading the request, sending the response).
 * This service only validates rules and applies the update.
 */
final class SettingsService
{
    public function __construct(
        private readonly UserRepository $users,
    ) {}

    /**
     * Validate and apply a profile update for the given user.
     *
     * Returns a SettingsResponse DTO on success.
     * Throws InvalidArgumentException with a human-readable message on validation failure.
     *
     * @throws InvalidArgumentException
     */
    public function update(int $userId, string $name, string $email): SettingsResponse
    {
        $name  = trim($name);
        $email = trim($email);

        if ($name === '') {
            throw new InvalidArgumentException('Name is required.');
        }
        if ($email === '') {
            throw new InvalidArgumentException('Email is required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email address.');
        }

        // Allow the user to keep their own email, but block if another account uses it.
        if ($this->users->emailTakenByOther($email, $userId)) {
            throw new InvalidArgumentException('That email is already in use by another account.');
        }

        $this->users->updateProfile($userId, $name, $email);

        // Fetch the updated record from DB and return it as a DTO.
        $updated = $this->users->findById($userId);

        return new SettingsResponse(
            $updated->id,
            $updated->name,
            $updated->email,
        );
    }
}
