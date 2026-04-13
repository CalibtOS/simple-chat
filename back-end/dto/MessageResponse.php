<?php
declare(strict_types=1);

/**
 * DTO: shaped data for a single item in the GET /api/messages response.
 * Safe to send to the browser — no internal fields exposed.
 */
final class MessageResponse
{
    public function __construct(
        public readonly int     $id,
        public readonly ?int    $userId,   // null = bot message
        public readonly string  $name,
        public readonly string  $message,
        public readonly string  $createdAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'user_id'    => $this->userId,
            'name'       => $this->name,
            'message'    => $this->message,
            'created_at' => $this->createdAt,
        ];
    }
}
