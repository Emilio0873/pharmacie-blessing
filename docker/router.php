<?php
/**
 * Router for PHP built-in server on Railway.
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
$file = __DIR__ . '/../' . ltrim($uri, '/');

if ($uri !== '/' && is_file($file)) {
    return false; // serve static file as-is
}

if ($uri === '/' || $uri === '') {
    require __DIR__ . '/../index.php';
    return true;
}

http_response_code(404);
echo '404 Not Found';
return true;
