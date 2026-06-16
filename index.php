<?php
date_default_timezone_set('America/Mexico_City');

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

$blockedPaths = [
    '/app',
    '/config',
    '/Database',
    '/logs',
    '/node_modules',
    '/resources',
    '/routes',
    '/vendor',
    '/.env',
    '/composer.json',
    '/composer.lock',
    '/package.json',
    '/package-lock.json',
    '/webpack.config.js',
    '/.git',
    '/.gitignore',
];

foreach ($blockedPaths as $blocked) {
    if ($uri === $blocked || str_starts_with($uri, $blocked . '/')) {
        http_response_code(404);
        exit('404 Not Found');
    }
}

if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri)) {
    return false;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/public/index.php';