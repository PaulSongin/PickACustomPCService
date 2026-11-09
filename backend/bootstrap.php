<?php
/**
 * Общая инициализация для API (api/index.php) и консольных скриптов (backend/bin/*).
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('BACKEND_ROOT', __DIR__);

// Автозагрузка классов: App\Core\Router → backend/src/Core/Router.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

App\Core\Config::load(__DIR__ . '/config');
date_default_timezone_set((string) App\Core\Config::get('app.timezone', 'Europe/Minsk'));
mb_internal_encoding('UTF-8');
