<?php
declare(strict_types=1);

/**
 * Controller: GET /api/conversations
 *
 * Responsibility: receive the request, delegate to the service, serialize
 * the resulting DTOs to JSON.  No SQL, no business rules here.
 */
final class ConversationController
{
    public function __construct(
        private readonly ConversationService $service,
        private readonly UserRepository      $users,
    ) {}

    // -------------------------------------------------------------------------
    // GET /api/conversations  (kept for backward compatibility)
    // -------------------------------------------------------------------------

    public function index(Request $request): void
    {
        $items = $this->service->getForCurrentUser();

        if ($items === []) {
            json_response(['error' => 'Could not load conversations. Bot seed may have failed.'], 500);
        }

        json_response(array_map(
            fn(ConversationResponse $c) => $c->toArray(),
            $items
        ));
    }

    // -------------------------------------------------------------------------
    // GET /api/users
    // Returns all registered users except the logged-in user, along with
    // their existing DM conversation_id (null if no DM started yet).
    // -------------------------------------------------------------------------

    public function users(Request $request): void
    {
        $items = $this->service->getUserList();

        json_response(array_map(
            fn(UserResponse $u) => $u->toArray(),
            $items
        ));
    }

    // -------------------------------------------------------------------------
    // POST /api/conversations
    // Body: { "user_id": 5 }
    // Finds or creates the DM conversation between the current user and user 5.
    // Returns: { "conversation_id": 12 }
    // -------------------------------------------------------------------------

    public function create(Request $request): void
    {
        $otherUserId = (int) ($request->body['user_id'] ?? 0);

        if ($otherUserId <= 0) {
            json_response(['error' => 'user_id is required'], 400);
        }

        // Make sure the target user actually exists.
        $otherUser = $this->users->findById($otherUserId);
        if ($otherUser === null) {
            json_response(['error' => 'User not found'], 404);
        }

        $conversationId = $this->service->getOrCreateWithUser($otherUserId);

        json_response(['conversation_id' => $conversationId]);
    }
}
