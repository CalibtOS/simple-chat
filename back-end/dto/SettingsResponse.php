<?php
declare(strict_types=1);

/**
 * DTO: shaped data returned after a successful PATCH /api/settings.
 * Contains only the fields the front-end needs to refresh the UI.
 */
final class SettingsResponse
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly string $email,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'success' => true,
            'id'      => $this->id,
            'name'    => $this->name,
            'email'   => $this->email,
        ];
    }
}
