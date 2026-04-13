<?php

final class JsonMiddleware
{
    public function __invoke(Request $request, callable $next): void
    {
        if (strpos($request->path, 'api/') === 0) {
            header('Content-Type: application/json');
        }
        $next($request);
    }
}

