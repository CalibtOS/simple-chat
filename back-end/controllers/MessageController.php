<?php
declare(strict_types=1);

/**
 * Controller: all message-related endpoints.
 *
 * Endpoints handled:
 *   GET  /api/messages                        → index()
 *   GET  /api/conversations/{id}/messages     → index()
 *   POST   /api/send                  → send()
 *   PATCH  /api/messages/{id}        → edit()
 *   DELETE /api/messages/{id}        → delete()
 *
 * Responsibility:
 * - Read and validate raw request input.
 * - Perform authorization checks (membership, ownership).
 * - Delegate business logic to MessageService.
 * - Serialize results and send JSON responses.
 */
final class MessageController
{
    public function __construct(
        private readonly MessageService         $service,
        private readonly ConversationRepository $conversations,
        private readonly UserRepository         $users,
    ) {}

    // -------------------------------------------------------------------------
    // GET /api/messages  |  GET /api/conversations/{id}/messages
    // -------------------------------------------------------------------------

    public function index(Request $request): void
    {
        $user = $this->users->getCurrentFromSession();

        // Route param {id} takes priority over query string conversation_id.
        $requestedId = (int) ($request->routeParams['id'] ?? $request->query['conversation_id'] ?? 0);

        try {
            $conversationId = $this->service->resolveConversation($requestedId, $user->id);
        } catch (RuntimeException $e) {
            json_response(['error' => $e->getMessage()], 500);
        }

        if (!$this->conversations->isMember($conversationId, $user->id)) {
            json_response(['error' => 'Forbidden'], 403);
        }

        // Mark messages from other users as read — this is what turns the
        // sender's ticks blue.  Only touches rows with read_at IS NULL so
        // it's cheap to call on every fetch (no-op when nothing is unread).
        $this->service->markAsRead($conversationId, $user->id);

        // Pagination parameters from the query string.
        // after_id  → polling: "give me only messages newer than this ID"
        // before_id → load-more: "give me the page of messages older than this ID"
        // Neither   → initial load: "give me the latest page"
        $afterId  = (int) ($request->query['after_id']  ?? 0);
        $beforeId = (int) ($request->query['before_id'] ?? 0);
        $limit    = 20;

        // read_through: the highest ID of the *current user's own* messages
        // that the other party has already read.  The client uses this to
        // colour existing tick marks blue without re-fetching every message.
        $readThrough = $this->service->getReadThrough($conversationId, $user->id);

        if ($afterId > 0) {
            // Polling path — return only new messages, no has_more needed.
            $newMessages = $this->service->getNewMessages($conversationId, $afterId);
            app_log('info', 'messages_index', [
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'mode' => 'after',
                'after_id' => $afterId,
                'count' => count($newMessages),
            ]);
            json_response([
                'conversation_id' => $conversationId,
                'messages'     => array_map(fn(MessageResponse $m) => $m->toArray(), $newMessages),
                'has_more'     => false,
                'read_through' => $readThrough,
            ]);
        } else {
            // Initial load or "load more" — return a page with a has_more flag.
            $page = $this->service->getPage($conversationId, $limit, $beforeId);
            app_log('info', 'messages_index', [
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'mode' => $beforeId > 0 ? 'before' : 'initial',
                'before_id' => $beforeId,
                'count' => count($page['messages']),
                'has_more' => (bool) $page['has_more'],
            ]);
            json_response([
                'conversation_id' => $conversationId,
                'messages'     => array_map(fn(MessageResponse $m) => $m->toArray(), $page['messages']),
                'has_more'     => $page['has_more'],
                'read_through' => $readThrough,
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // POST /api/send
    // -------------------------------------------------------------------------

    public function send(Request $request): void
    {
        $user    = $this->users->getCurrentFromSession();
        $text    = trim((string) ($request->body['message']         ?? ''));
        $convId  = (int)          ($request->body['conversation_id'] ?? 0);

        if ($text === '') {
            json_response(['error' => 'Message is required'], 400);
        }

        try {
            $conversationId = $this->service->resolveConversation($convId, $user->id);
        } catch (RuntimeException $e) {
            json_response(['error' => $e->getMessage()], 500);
        }

        if (!$this->conversations->isMember($conversationId, $user->id)) {
            json_response(['error' => 'Forbidden'], 403);
        }

        $this->service->send($conversationId, $user->id, $user->name, $text);

        json_response(['success' => true, 'conversation_id' => $conversationId]);
    }

    // -------------------------------------------------------------------------
    // PATCH /api/messages/{id}
    // -------------------------------------------------------------------------

    public function edit(Request $request): void
    {
        $user      = $this->users->getCurrentFromSession();
        $messageId = (int) ($request->routeParams['id'] ?? 0);   // comes from the URL
        $newText   = trim((string) ($request->body['message'] ?? ''));

        if ($messageId <= 0) {
            json_response(['error' => 'Invalid message id'], 400);
        }
        if ($newText === '') {
            json_response(['error' => 'Message text is required'], 400);
        }

        $message = $this->service->findMessage($messageId);
        if (!$message instanceof Message) {
            json_response(['error' => 'Message not found'], 404);
        }

        // Ownership check: only the author can edit.
        if ($message->userId === null || $message->userId !== $user->id) {
            json_response(['error' => 'You can only edit your own messages'], 403);
        }

        // Membership check: user must still belong to the conversation.
        if (!$this->conversations->isMember($message->conversationId, $user->id)) {
            json_response(['error' => 'Forbidden'], 403);
        }

        if (!$this->service->editMessage($messageId, $user->id, $newText)) {
            json_response(['error' => 'Update failed'], 500);
        }

        json_response(['success' => true, 'id' => $messageId]);
    }

    // -------------------------------------------------------------------------
    // DELETE /api/messages/{id}
    // -------------------------------------------------------------------------

    public function delete(Request $request): void
    {
        $user      = $this->users->getCurrentFromSession();
        $messageId = (int) ($request->routeParams['id'] ?? 0);   // comes from the URL

        if ($messageId <= 0) {
            json_response(['error' => 'Invalid message id'], 400);
        }

        $message = $this->service->findMessage($messageId);
        if (!$message instanceof Message) {
            json_response(['error' => 'Message not found'], 404);
        }

        // Ownership check.
        if ($message->userId === null || $message->userId !== $user->id) {
            json_response(['error' => 'You can only delete your own messages'], 403);
        }

        // Membership check.
        if (!$this->conversations->isMember($message->conversationId, $user->id)) {
            json_response(['error' => 'Forbidden'], 403);
        }

        if (!$this->service->deleteMessage($messageId, $user->id)) {
            json_response(['error' => 'Delete failed'], 500);
        }

        json_response(['success' => true, 'id' => $messageId]);
    }
}
