<?php

declare(strict_types=1);

define('V2_ROOT', dirname(__DIR__));
define('V2_APP', V2_ROOT . '/app');
define('V2_DATA', V2_ROOT . '/data');
define('V2_CONFIG', V2_DATA . '/config.php');
define('V2_MIGRATIONS', V2_ROOT . '/migrations');
define('V2_TEMPLATES', V2_ROOT . '/templates');
define('V2_BASE_URL', '/v2');
define('V2_WEB_ROOT', '/v2');

require_once V2_ROOT . '/app/Support/helpers.php';

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
