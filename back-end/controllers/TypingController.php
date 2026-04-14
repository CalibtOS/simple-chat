<?php
declare(strict_types=1);

/**
 * Controller: typing-indicator endpoints.
 *
 *   POST /api/typing   → start()  called on every keystroke heartbeat
 *   GET  /api/typing   → poll()   called every 2 s to see who is typing
 */
final class TypingController
{
    public function __construct(
        private readonly TypingRepository       $typing,
        private readonly ConversationRepository $conversations,
        private readonly UserRepository         $users,
    ) {}

    // -------------------------------------------------------------------------
    // POST /api/typing
    // Body: { conversation_id: 5 }
    // -------------------------------------------------------------------------

    public function start(Request $request): void
    {
        $user   = $this->users->getCurrentFromSession();
        $convId = (int) ($request->body['conversation_id'] ?? 0);

        if ($convId <= 0) {
            json_response(['error' => 'conversation_id is required'], 400);
        }

        if (!$this->conversations->isMember($convId, $user->id)) {
            json_response(['error' => 'Forbidden'], 403);
        }

        $this->typing->upsert($user->id, $convId);
        json_response(['ok' => true]);
    }

    // -------------------------------------------------------------------------
    // GET /api/typing?conversation_id=5
    // -------------------------------------------------------------------------

    public function poll(Request $request): void
    {
        $user   = $this->users->getCurrentFromSession();
        $convId = (int) ($request->query['conversation_id'] ?? 0);

        if ($convId <= 0) {
            json_response(['typing' => []]);
        }

        if (!$this->conversations->isMember($convId, $user->id)) {
            json_response(['error' => 'Forbidden'], 403);
        }

        $typers = $this->typing->getActive($convId, $user->id);
        json_response(['typing' => $typers]);
    }
}
