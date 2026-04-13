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

        $messages = $this->service->getMessages($conversationId);

        json_response(array_map(
            fn(MessageResponse $m) => $m->toArray(),
            $messages
        ));
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
