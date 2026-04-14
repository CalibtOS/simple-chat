<?php

final class AuthMiddleware
{
    public function __construct(private readonly mysqli $db) {}

    public function __invoke(Request $request, callable $next): void
    {
        if (!isLoggedIn()) {
            json_response(['error' => 'You must be logged in'], 401);
        }

        // Stamp last_seen_at on every authenticated request.
        // This is how presence works: the front-end polls every 5 seconds, so
        // any open tab keeps this timestamp fresh.  Once the tab closes the
        // polling stops, and after 60 seconds the user appears offline.
        $userId = (int) $_SESSION['user_id'];
        $stmt   = $this->db->prepare('UPDATE users SET last_seen_at = NOW() WHERE id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();

        $next($request);
    }
}

