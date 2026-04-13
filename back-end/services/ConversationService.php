<?php
declare(strict_types=1);

/**
 * Service: business logic for the conversations feature.
 *
 * Rules owned here:
 * - Each user must have exactly one conversation per default bot.
 * - Bots are seeded on first access.
 * - Membership is always guaranteed before returning conversation list.
 */
final class ConversationService
{
    public function __construct(
        private readonly ConversationRepository $conversations,
        private readonly BotRepository          $bots,
        private readonly UserRepository         $users,
    ) {}

    /**
     * Return the conversation list for the currently logged-in user.
     * Creates missing conversations and ensures membership as a side-effect.
     *
     * @return ConversationResponse[]
     */
    public function getForCurrentUser(): array
    {
        $user   = $this->users->getCurrentFromSession();
        $userId = $user->id;

        // Guarantee bots exist.
        $this->bots->seedDefaultBots();
        $allBots = $this->bots->getDefaultBots();

        if (count($allBots) !== 3) {
            // If seed failed for any reason the response will be empty.
            // The controller can decide to return a 500.
            return [];
        }

        $botIds   = array_map(fn(Bot $b) => $b->id, $allBots);
        $existing = $this->conversations->getExistingForUser($userId, $botIds);

        // Create any conversation that doesn't exist yet.
        foreach ($allBots as $bot) {
            if (!isset($existing[$bot->id])) {
                $convId          = $this->conversations->create("Chat with {$bot->name}", $userId, $bot->id);
                $existing[$bot->id] = $convId;
            }
        }

        // Ensure the user is a member of every conversation.
        $this->conversations->ensureMembership($userId, array_values($existing));

        return $this->conversations->getConversationsWithDetails($userId);
    }

    /**
     * Return all users (except the current user) with their DM conversation info.
     * This powers the user sidebar.
     *
     * @return UserResponse[]
     */
    public function getUserList(): array
    {
        $user = $this->users->getCurrentFromSession();
        return $this->conversations->getUsersWithConversations($user->id);
    }

    /**
     * Find the existing DM conversation between the current user and $otherUserId,
     * or create a new one if none exists.
     *
     * This is the "find or create" pattern:
     *   1. Check if a conversation already exists → reuse it.
     *   2. If not → create it, add both users as members.
     *
     * Returns the conversation ID.
     */
    public function getOrCreateWithUser(int $otherUserId): int
    {
        $currentUser = $this->users->getCurrentFromSession();

        $existing = $this->conversations->findBetweenUsers($currentUser->id, $otherUserId);
        if ($existing !== null) {
            return $existing;
        }

        return $this->conversations->createUserConversation($currentUser->id, $otherUserId);
    }
}
