<?php

final class Pipeline
{
    /**
     * @param array<int, callable(Request, callable): void> $middlewares
     * @param callable(Request): void $destination
     */
    public static function run(Request $request, array $middlewares, callable $destination): void
    {
        $next = $destination;

        for ($i = count($middlewares) - 1; $i >= 0; $i--) {
            $middleware = $middlewares[$i];
            $prev = $next;
            $next = static function (Request $req) use ($middleware, $prev): void {
                $middleware($req, $prev);
            };
        }

        $next($request);
    }
}

