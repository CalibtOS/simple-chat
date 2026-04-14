<?php
declare(strict_types=1);

/**
 * Service: business logic for messages.
 *
 * Rules owned here:
 * - Conversation resolution (fall back to oldest, then create default).
 * - Bot auto-reply after every user message.
 * - Fetching, editing, deleting messages.
 *
 * Authorization (membership, ownership) is the controller's responsibility —
 * this service only performs the actual data operations.
 */
final class MessageService
{
    public function __construct(
        private readonly mysqli                 $db,            // used only for transaction control
        private readonly MessageRepository      $messages,
        private readonly ConversationRepository $conversations,
        private readonly BotRepository          $bots,
    ) {}

    /**
     * Determine which conversation to use for a request.
     *
     * Priority:
     *   1. Use $requestedId if valid and provided.
     *   2. Fall back to the user's oldest conversation.
     *   3. Create a new conversation with the Ahmed bot as a last resort.
     *
     * @throws RuntimeException if the default bot doesn't exist (migration missing).
     */
    public function resolveConversation(int $requestedId, int $userId): int
    {
        if ($requestedId > 0) {
            return $requestedId;
        }

        $existing = $this->conversations->getOldestForUser($userId);
        if ($existing !== null) {
            return $existing;
        }

        // No conversations yet: create the default one.
        $bot = $this->bots->findBySlug('ahmed');
        if (!$bot instanceof Bot) {
            throw new RuntimeException('Default bot not found. Run migrations.');
        }

        $convId = $this->conversations->create("Chat with {$bot->name}", $userId, $bot->id);
        $this->conversations->ensureMembership($userId, [$convId]);
        return $convId;
    }

    /**
     * Return a page of messages, newest-last, with a has_more flag.
     *
     * $beforeId = 0  → latest page (most recent $limit messages)
     * $beforeId > 0  → the page of messages older than that ID ("load more")
     *
     * @return array{messages: MessageResponse[], has_more: bool}
     */
    public function getPage(int $conversationId, int $limit, int $beforeId = 0): array
    {
        return $this->messages->getPage($conversationId, $limit, $beforeId);
    }

    /**
     * Return all messages newer than $afterId (used for polling).
     *
     * @return MessageResponse[]
     */
    public function getNewMessages(int $conversationId, int $afterId): array
    {
        return $this->messages->getAfter($conversationId, $afterId);
    }

    /**
     * Mark all unread messages from other users as read for the current user.
     * Called whenever the recipient fetches a conversation.
     */
    public function markAsRead(int $conversationId, int $readerUserId): void
    {
        $this->messages->markAsRead($conversationId, $readerUserId);
    }

    /**
     * Return the highest message ID sent by the current user that has been
     * read by the other party.  Included in every poll response so the sender
     * can update tick colours without re-fetching the full message list.
     */
    public function getReadThrough(int $conversationId, int $senderUserId): ?int
    {
        return $this->messages->getReadThrough($conversationId, $senderUserId);
    }

    /**
     * Find a single message entity by ID.
     * Returns null if not found (controller handles the 404).
     */
    public function findMessage(int $messageId): ?Message
    {
        return $this->messages->findById($messageId);
    }

    /**
     * Insert a user message and trigger the bot auto-reply — wrapped in a transaction.
     *
     * A transaction means: both inserts succeed together, or neither does.
     * If the bot reply insert fails for any reason, the user message is also
     * rolled back, leaving the conversation in a clean state.
     *
     * @throws RuntimeException if the database operation fails.
     */
    public function send(int $conversationId, int $userId, string $userName, string $text): void
    {
        $this->db->begin_transaction();

        try {
            $this->messages->insert($conversationId, $userId, $userName, $text);

            $bot = $this->bots->findByConversationId($conversationId);
            if ($bot instanceof Bot) {
                $this->messages->insertBotReply($conversationId, $bot->name, $bot->replyTemplate);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw new RuntimeException('Failed to send message.', 0, $e);
        }
    }

    /**
     * Update the text of a message.
     * Returns true if the update affected a row, false otherwise.
     */
    public function editMessage(int $messageId, int $userId, string $newText): bool
    {
        return $this->messages->update($messageId, $userId, $newText);
    }

    /**
     * Delete a message.
     * Returns true if the delete affected a row, false otherwise.
     */
    public function deleteMessage(int $messageId, int $userId): bool
    {
        return $this->messages->delete($messageId, $userId);
    }
}
