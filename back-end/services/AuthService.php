<?php
declare(strict_types=1);

/**
 * Service: business logic for login and registration.
 *
 * Owns the rules for:
 * - Credential verification (login)
 * - Validation and uniqueness checks (register)
 * - Password hashing
 *
 * Never touches HTTP — no headers, no redirects, no json_response().
 */
final class AuthService
{
    public function __construct(private readonly UserRepository $users) {}

    /**
     * Verify credentials and return the matching User entity on success,
     * or null if email/password don't match.
     */
    public function login(string $email, string $password): ?User
    {
        $row = $this->users->findByEmailForAuth($email);

        if (!$row || !password_verify($password, (string) $row['password_hash'])) {
            return null;
        }

        return new User((int) $row['id'], (string) $row['name'], (string) $row['email']);
    }

    /**
     * Register a new user.
     *
     * Returns the new user's ID on success, or an error message string on failure.
     * The controller decides what HTTP action to take with the result.
     *
     * @return int|string  new user ID, or an error message
     */
    public function register(string $email, string $password, string $name): int|string
    {
        if ($this->users->existsByEmail($email)) {
            return 'This email is already registered.';
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        return $this->users->create($email, $hash, $name);
    }
}
