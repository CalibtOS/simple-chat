<?php

final class AuthMiddleware
{
    public function __invoke(Request $request, callable $next): void
    {
        if (!isLoggedIn()) {
            json_response(['error' => 'You must be logged in'], 401);
        }
        $next($request);
    }
}

