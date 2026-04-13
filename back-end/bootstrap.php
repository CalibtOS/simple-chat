<?php
/**
 * Application bootstrap for the back-end layer.
 *
 * - Loads configuration and database connection
 * - Starts / shares the session & auth helpers
 * - Provides a small helper for JSON API responses
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/core/Logger.php';

function app_logger(): Logger
{
    static $logger = null;
    if ($logger instanceof Logger) {
        return $logger;
    }
    $logsDir = dirname(__DIR__) . '/storage/logs';
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0777, true);
    }
    $logger = new Logger($logsDir . '/app.log');
    return $logger;
}

/**
 * @param array<string, mixed> $context
 */
function app_log(string $level, string $message, array $context = []): void
{
    app_logger()->log($level, $message, $context);
}

/**
 * Send a JSON response and terminate the request.
 */
function json_response(array $data, int $statusCode = 200): void
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    app_log('info', 'json_response', [
        'status' => $statusCode,
        'path' => $path,
    ]);
    // Discard any stray output (PHP warnings/notices from display_errors=On)
    // that accumulated in the output buffer started at the top of index.php.
    if (ob_get_level() > 0) {
        $stray = ob_get_clean();
        if ($stray !== '' && $stray !== false) {
            app_log('warning', 'Stray output discarded before JSON response', ['output' => $stray]);
        }
    }
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

