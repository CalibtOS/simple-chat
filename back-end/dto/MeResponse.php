<?php
declare(strict_types=1);

/**
 * DTO: shaped data for the GET /api/me response.
 * It is a safe subset of the User entity — no password hash, no internal fields.
 */
final class MeResponse
{
    public function __construct(
        public readonly bool    $loggedIn,
        public readonly ?int    $id,
        public readonly ?string $name,
        public readonly ?string $email,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'loggedIn' => $this->loggedIn,
            'id'       => $this->id,
            'name'     => $this->name,
            'email'    => $this->email,
        ];
    }
}
