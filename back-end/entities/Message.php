<?php
declare(strict_types=1);

final class Message
{
    public function __construct(
        public readonly int     $id,
        public readonly int     $conversationId,
        public readonly ?int    $userId,   // null means the message was sent by a bot
        public readonly string  $name,
        public readonly string  $message,
        public readonly string  $createdAt,
    ) {}
}
