<?php

require_once __DIR__.'/../vendor/autoload.php';

date_default_timezone_set('Asia/Jakarta');

set_error_handler('errorHandler');
register_shutdown_function('fatalHandler');

function env($key, $default = null)
{
    $env = $_ENV[$key] ?? $default;

    if ($env === 'true') {
        $env = true;
    } else if ($env === 'false') {
        $env = false;
    }

    return $env;
}

try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/..');
    $dotenv->load();
} catch (\Exception $e) {
    logError($e->getMessage(), $data = [], $publish = true);
}

if (env('APP_DEBUG')) {
    logInfo('DEBUG TRUE', $data = [], $publish = true);

    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

\App\Database::setup();
