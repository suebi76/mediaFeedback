<?php

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('V2_ROOT', __DIR__);
define('V2_APP', V2_ROOT . '/app');
define('V2_DATA', V2_ROOT . '/data');
define('V2_CONFIG', V2_DATA . '/config.php');
define('V2_MIGRATIONS', V2_ROOT . '/migrations');
define('V2_TEMPLATES', V2_ROOT . '/templates');

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/v2/index.php'));
$requestPath = str_replace('\\', '/', (string) (parse_url($_SERVER['REQUEST_URI'] ?? $scriptName, PHP_URL_PATH) ?: $scriptName));
$baseDir = dirname($scriptName);
if ($baseDir === DIRECTORY_SEPARATOR || $baseDir === '.') {
    $baseDir = '';
}
$baseDir = str_replace('\\', '/', $baseDir);
define('V2_BASE_URL', $scriptName);
define('V2_WEB_ROOT', $baseDir);

require_once V2_ROOT . '/app/Support/helpers.php';
$rootVendorAutoload = dirname(V2_ROOT) . '/vendor/autoload.php';
if (is_file($rootVendorAutoload)) {
    require_once $rootVendorAutoload;
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'MediaFeedbackV2\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = V2_APP . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});

MediaFeedbackV2\Core\ErrorHandler::register();

use MediaFeedbackV2\Controllers\ActivityController;
use MediaFeedbackV2\Controllers\AuthController;
use MediaFeedbackV2\Controllers\DashboardController;
use MediaFeedbackV2\Controllers\DiagnosticsController;
use MediaFeedbackV2\Controllers\FeedbackController;
use MediaFeedbackV2\Controllers\MediaController;
use MediaFeedbackV2\Controllers\PublicFeedbackController;
use MediaFeedbackV2\Controllers\ResultController;
use MediaFeedbackV2\Controllers\ShareController;
use MediaFeedbackV2\Controllers\SetupController;
use MediaFeedbackV2\Controllers\UserManagementController;
use MediaFeedbackV2\Core\Router;

$router = new Router();

if (!file_exists(V2_CONFIG)) {
    $router->add('GET', '/', [SetupController::class, 'show']);
    $router->add('GET', '/setup', [SetupController::class, 'show']);
    $router->add('POST', '/setup', [SetupController::class, 'install']);
    $router->run();
    exit;
}

$router->add('GET', '/', [DashboardController::class, 'index']);
$router->add('GET', '/login', [AuthController::class, 'show']);
$router->add('POST', '/login', [AuthController::class, 'login']);
$router->add('POST', '/logout', [AuthController::class, 'logout']);
$router->add('GET', '/dashboard', [DashboardController::class, 'index']);
$router->add('GET', '/diagnostics', [DiagnosticsController::class, 'index']);
$router->add('GET', '/users', [UserManagementController::class, 'index']);
$router->add('POST', '/users/create', [UserManagementController::class, 'create']);
$router->add('POST', '/users/delete', [UserManagementController::class, 'delete']);

$router->add('GET', '/feedback/create', [FeedbackController::class, 'create']);
$router->add('POST', '/feedback/create', [FeedbackController::class, 'store']);
$router->add('GET', '/feedback/edit', [FeedbackController::class, 'edit']);
$router->add('POST', '/feedback/update', [FeedbackController::class, 'update']);
$router->add('POST', '/feedback/delete', [FeedbackController::class, 'delete']);
$router->add('POST', '/feedback/status', [FeedbackController::class, 'changeStatus']);

$router->add('POST', '/activity/add', [ActivityController::class, 'add']);
$router->add('POST', '/activity/update', [ActivityController::class, 'update']);
$router->add('POST', '/activity/delete', [ActivityController::class, 'delete']);
$router->add('POST', '/activity/duplicate', [ActivityController::class, 'duplicate']);
$router->add('POST', '/activity/move', [ActivityController::class, 'move']);
$router->add('POST', '/activity/move-page', [ActivityController::class, 'movePage']);
$router->add('POST', '/activity/split-at-block', [ActivityController::class, 'splitAtBlock']);
$router->add('POST', '/activity/reorder', [ActivityController::class, 'reorder']);
$router->add('POST', '/activity/page-break', [ActivityController::class, 'insertPageBreak']);
$router->add('POST', '/activity/page-merge', [ActivityController::class, 'mergePage']);

$router->add('GET', '/f', [PublicFeedbackController::class, 'show']);
$router->add('POST', '/f/submit', [PublicFeedbackController::class, 'submit']);
$router->add('GET', '/results', [ResultController::class, 'index']);
$router->add('GET', '/export', [ResultController::class, 'export']);
$router->add('GET', '/media', [MediaController::class, 'serve']);
$router->add('GET', '/share/qr', [ShareController::class, 'qr']);

$router->run();
