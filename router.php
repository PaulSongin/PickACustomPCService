<?php
/**
 * Маршрутизатор для встроенного сервера PHP (вместо Apache при разработке):
 *
 *   php -S localhost:8000 router.php
 *
 * /api/* → api/index.php, служебные папки закрыты, остальное — статические файлы сайта.
 */
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

if (preg_match('#^/api(/|$)#', $path)) {
    require __DIR__ . '/api/index.php';
    return true;
}

if (preg_match('#(^|/)\.|^/(backend|database|bot|docs)(/|$)|\.(php|sql|md|log)$#i', $path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Not found';
    return true;
}

return false;
