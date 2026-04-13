<?php
declare(strict_types=1);

/**
 * DTO: a single user as returned by GET /api/users.
 *
 * Includes the conversation_id for the DM between the current user and this
 * user — null if no conversation exists yet.
 * The front-end uses this to show the last-message preview and to skip the
 * POST /api/conversations call when a conversation already exists.
 */
final class UserResponse
{
    public function __construct(
        public readonly int     $id,
        public readonly string  $name,
        public readonly ?int    $conversationId,
        public readonly ?string $lastMessage,
        public readonly ?string $lastMessageAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'conversation_id' => $this->conversationId,
            'last_message'    => $this->lastMessage,
            'last_message_at' => $this->lastMessageAt,
        ];
    }
}
