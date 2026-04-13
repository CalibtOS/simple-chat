<?php
declare(strict_types=1);

/**
 * DTO: shaped data for a single item in the GET /api/conversations response.
 * Combines conversation fields with joined bot info and last-message metadata.
 */
final class ConversationResponse
{
    public function __construct(
        public readonly int     $id,
        public readonly string  $title,
        public readonly string  $botSlug,
        public readonly string  $botName,
        public readonly ?string $lastMessage,
        public readonly ?string $lastMessageAt,
        public readonly string  $createdAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'title'           => $this->title,
            'bot'             => ['slug' => $this->botSlug, 'name' => $this->botName],
            'last_message'    => $this->lastMessage,
            'last_message_at' => $this->lastMessageAt,
            'created_at'      => $this->createdAt,
        ];
    }
}
