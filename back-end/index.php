<?php
declare(strict_types=1);

// Capture any stray output (PHP warnings, notices from display_errors=On)
// so they don't corrupt our JSON responses.  json_response() calls ob_end_clean()
// before sending, which discards everything buffered up to that point.
ob_start();

// ─── Bootstrap ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';

// ─── HTTP layer ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/http/Request.php';
require_once __DIR__ . '/http/Pipeline.php';

// ─── Middleware ───────────────────────────────────────────────────────────────
require_once __DIR__ . '/middleware/JsonMiddleware.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';

// ─── Entities ─────────────────────────────────────────────────────────────────
require_once __DIR__ . '/entities/User.php';
require_once __DIR__ . '/entities/Bot.php';
require_once __DIR__ . '/entities/Message.php';

// ─── DTOs ─────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/dto/MeResponse.php';
require_once __DIR__ . '/dto/ConversationResponse.php';
require_once __DIR__ . '/dto/MessageResponse.php';
require_once __DIR__ . '/dto/SettingsResponse.php';
require_once __DIR__ . '/dto/UserResponse.php';

// ─── Core utilities ───────────────────────────────────────────────────────────
require_once __DIR__ . '/core/Mailer.php';
require_once __DIR__ . '/core/Totp.php';

// ─── Repositories ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/repositories/UserRepository.php';
require_once __DIR__ . '/repositories/BotRepository.php';
require_once __DIR__ . '/repositories/ConversationRepository.php';
require_once __DIR__ . '/repositories/MessageRepository.php';
require_once __DIR__ . '/repositories/TypingRepository.php';

// ─── Services ─────────────────────────────────────────────────────────────────
require_once __DIR__ . '/services/MeService.php';
require_once __DIR__ . '/services/AuthService.php';
require_once __DIR__ . '/services/ConversationService.php';
require_once __DIR__ . '/services/MessageService.php';
require_once __DIR__ . '/services/SettingsService.php';
require_once __DIR__ . '/services/PasswordResetService.php';
require_once __DIR__ . '/services/TwoFactorService.php';

// ─── Controllers ──────────────────────────────────────────────────────────────
require_once __DIR__ . '/controllers/MeController.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/ConversationController.php';
require_once __DIR__ . '/controllers/MessageController.php';
require_once __DIR__ . '/controllers/SettingsController.php';
require_once __DIR__ . '/controllers/TwoFactorController.php';
require_once __DIR__ . '/controllers/TypingController.php';

// ─── Route helpers ────────────────────────────────────────────────────────────

function routeNotFound(string $path): never
{
    http_response_code(404);
    if (strpos($path, 'api/') === 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not Found']);
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not Found: {$path}";
    exit;
}

/**
 * Match an incoming method+path against the route table.
 * Supports {param} placeholders in patterns.
 *
 * @param  array<int, array<string, mixed>> $routes
 * @return array{route: array<string, mixed>, params: array<string, string>}|null
 */
function matchRoute(string $method, string $path, array $routes): ?array
{
    $pathParts = explode('/', trim($path, '/'));

    foreach ($routes as $route) {
        if (strtoupper($route['method']) !== strtoupper($method)) {
            continue;
        }

        $patternParts = explode('/', trim($route['pattern'], '/'));

        if (count($patternParts) !== count($pathParts)) {
            continue;
        }

        $params  = [];
        $matched = true;

        foreach ($patternParts as $idx => $part) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $part, $m)) {
                $params[$m[1]] = $pathParts[$idx];
                continue;
            }
            if ($part !== $pathParts[$idx]) {
                $matched = false;
                break;
            }
        }

        if ($matched) {
            return ['route' => $route, 'params' => $params];
        }
    }

    return null;
}

// ─── Resolve request path ─────────────────────────────────────────────────────

$uriPath     = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$projectName = basename(dirname(__DIR__));
$prefix      = '/' . $projectName . '/';

$relativePath = $uriPath;
if ($relativePath === '/' . $projectName) {
    $relativePath = '';
} elseif (strpos($relativePath, $prefix) === 0) {
    $relativePath = substr($relativePath, strlen($prefix));
}
$relativePath = ltrim($relativePath, '/');

// Root → redirect to login.
if ($relativePath === '' || $relativePath === 'index.php' || $relativePath === 'back-end/index.php') {
    header('Location: front-end/html/login.html');
    exit;
}

// ─── Build Request object ─────────────────────────────────────────────────────
// PHP only populates $_POST for form-encoded bodies.
// Any request sent with Content-Type: application/json needs manual parsing.
// Form-based POSTs (e.g. /api/send uses FormData) still go through $_POST.

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$body   = $_POST;

$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
if (str_contains($contentType, 'application/json')) {
    // JSON body — applies to PATCH /api/settings, POST /api/conversations, etc.
    $raw = (string) file_get_contents('php://input');
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $body = $decoded;
        }
    }
} elseif (in_array($method, ['PATCH', 'PUT', 'DELETE'], true)) {
    // Non-JSON raw body fallback for PATCH/PUT/DELETE without explicit Content-Type.
    $raw = (string) file_get_contents('php://input');
    if ($raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $body = $decoded;
        }
    }
}

$request = new Request($method, $relativePath, $_GET, $body);

// ─── Route table ──────────────────────────────────────────────────────────────
// Each entry: method, pattern, target ('Class@method'), middleware keys.

$routes = [
    // Auth — no middleware (user not yet logged in)
    ['method' => 'POST', 'pattern' => 'auth/login',           'target' => 'AuthController@login',          'middleware' => []],
    ['method' => 'POST', 'pattern' => 'auth/register',        'target' => 'AuthController@register',       'middleware' => []],
    ['method' => 'GET',  'pattern' => 'auth/logout',          'target' => 'AuthController@logout',         'middleware' => []],
    ['method' => 'POST', 'pattern' => 'api/auth/forgot-password', 'target' => 'AuthController@forgotPassword', 'middleware' => ['json']],
    ['method' => 'POST', 'pattern' => 'api/auth/reset-password',  'target' => 'AuthController@resetPassword',  'middleware' => ['json']],
    ['method' => 'POST', 'pattern' => 'api/auth/2fa-verify',      'target' => 'TwoFactorController@verifyLogin','middleware' => ['json']],

    // 2FA management (requires full login)
    ['method' => 'GET',    'pattern' => 'api/2fa/status', 'target' => 'TwoFactorController@status',  'middleware' => ['json', 'auth']],
    ['method' => 'GET',    'pattern' => 'api/2fa/setup',  'target' => 'TwoFactorController@setup',   'middleware' => ['json', 'auth']],
    ['method' => 'POST',   'pattern' => 'api/2fa/enable', 'target' => 'TwoFactorController@enable',  'middleware' => ['json', 'auth']],
    ['method' => 'DELETE', 'pattern' => 'api/2fa',        'target' => 'TwoFactorController@disable', 'middleware' => ['json', 'auth']],

    // Current user — json only, no auth (returns {loggedIn:false} for guests)
    ['method' => 'GET',  'pattern' => 'api/me',        'target' => 'MeController',            'middleware' => ['json']],

    // Settings
    ['method' => 'GET',   'pattern' => 'api/settings', 'target' => 'SettingsController@show',   'middleware' => ['json', 'auth']],
    ['method' => 'PATCH', 'pattern' => 'api/settings', 'target' => 'SettingsController@update', 'middleware' => ['json', 'auth']],

    // Users list (for sidebar)
    ['method' => 'GET',  'pattern' => 'api/users',         'target' => 'ConversationController@users',  'middleware' => ['json', 'auth']],

    // Conversations
    ['method' => 'GET',  'pattern' => 'api/conversations',  'target' => 'ConversationController@index',  'middleware' => ['json', 'auth']],
    ['method' => 'POST', 'pattern' => 'api/conversations',  'target' => 'ConversationController@create', 'middleware' => ['json', 'auth']],

    // Typing indicators
    ['method' => 'POST', 'pattern' => 'api/typing', 'target' => 'TypingController@start', 'middleware' => ['json', 'auth']],
    ['method' => 'GET',  'pattern' => 'api/typing', 'target' => 'TypingController@poll',  'middleware' => ['json', 'auth']],

    // Messages
    ['method' => 'GET',  'pattern' => 'api/messages',                     'target' => 'MessageController@index',  'middleware' => ['json', 'auth']],
    ['method' => 'GET',  'pattern' => 'api/conversations/{id}/messages',   'target' => 'MessageController@index',  'middleware' => ['json', 'auth']],
    ['method' => 'POST',   'pattern' => 'api/send',              'target' => 'MessageController@send',   'middleware' => ['json', 'auth']],
    ['method' => 'PATCH',  'pattern' => 'api/messages/{id}',    'target' => 'MessageController@edit',   'middleware' => ['json', 'auth']],
    ['method' => 'DELETE', 'pattern' => 'api/messages/{id}',    'target' => 'MessageController@delete', 'middleware' => ['json', 'auth']],
];

// ─── Match route ──────────────────────────────────────────────────────────────

$matched = matchRoute($request->method, $request->path, $routes);
if (!$matched) {
    routeNotFound($request->path);
}

$route                = $matched['route'];
$request->routeParams = $matched['params'];

// ─── Build middleware stack ───────────────────────────────────────────────────

$middlewareMap = [
    'json' => new JsonMiddleware(),
    'auth' => new AuthMiddleware($conn),
];

$middlewares = [];
foreach ($route['middleware'] as $name) {
    if (isset($middlewareMap[$name])) {
        $middlewares[] = $middlewareMap[$name];
    }
}

// ─── Dispatch ─────────────────────────────────────────────────────────────────

$destination = static function (Request $req) use ($route, $conn): void {
    // Repositories — all share the same mysqli connection for this request.
    $userRepo = new UserRepository($conn);
    $botRepo  = new BotRepository($conn);
    $convRepo = new ConversationRepository($conn);
    $msgRepo  = new MessageRepository($conn);

    // Parse 'Class@method' — method defaults to '__invoke' when not specified.
    [$class, $method] = explode('@', $route['target'] . '@__invoke');

    switch ($class) {
        case 'MeController':
            (new MeController(new MeService($userRepo)))($req);
            break;

        case 'AuthController':
            $ctrl = new AuthController(
                new AuthService($userRepo),
                new PasswordResetService($userRepo),
                $userRepo,
            );
            $ctrl->$method($req);
            break;

        case 'TwoFactorController':
            $ctrl = new TwoFactorController(
                new TwoFactorService($userRepo),
                $userRepo,
            );
            $ctrl->$method($req);
            break;

        case 'ConversationController':
            $ctrl = new ConversationController(
                new ConversationService($convRepo, $botRepo, $userRepo),
                $userRepo,
            );
            $ctrl->$method($req);
            break;

        case 'MessageController':
            $ctrl = new MessageController(
                new MessageService($conn, $msgRepo, $convRepo, $botRepo),
                $convRepo,
                $userRepo,
            );
            $ctrl->$method($req);
            break;

        case 'TypingController':
            $ctrl = new TypingController(
                new TypingRepository($conn),
                $convRepo,
                $userRepo,
            );
            $ctrl->$method($req);
            break;

        case 'SettingsController':
            $ctrl = new SettingsController(
                new SettingsService($userRepo),
                $userRepo,
            );
            $ctrl->$method($req);
            break;

        default:
            routeNotFound($req->path);
    }
};

// ─── Error boundary ───────────────────────────────────────────────────────────
// Wraps the entire pipeline so any unexpected crash (DB failure, null reference,
// type error — anything) returns a clean JSON 500 instead of a raw PHP error page.
// The real error is written to the log; the browser only gets a safe generic message.

try {
    Pipeline::run($request, $middlewares, $destination);
} catch (Throwable $e) {
    app_log('error', 'Unhandled exception', [
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
        'path'    => $request->path,
        'method'  => $request->method,
    ]);
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'An unexpected error occurred.']);
}
exit;

