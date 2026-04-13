<?php
declare(strict_types=1);

final class Bot
{
    public function __construct(
        public readonly int    $id,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $replyTemplate,
    ) {}
}
