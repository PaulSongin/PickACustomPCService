<?php
/**
 * Карта API. Все ответы — JSON.
 * POST-запросы (кроме webhook Telegram) требуют заголовок X-CSRF-Token (см. GET /api/csrf).
 */
declare(strict_types=1);

use App\Controllers\BuildController;
use App\Controllers\CatalogController;
use App\Controllers\OrderController;
use App\Controllers\PresetController;
use App\Controllers\ProductController;
use App\Controllers\ReviewController;
use App\Controllers\SessionController;
use App\Controllers\TelegramController;
use App\Core\Router;

$router = new Router();

$router->get('/api/csrf', [SessionController::class, 'csrf']);

// Каталог
$router->get('/api/catalog', [CatalogController::class, 'index']);           // всё сразу: категории + товары + готовые сборки
$router->get('/api/categories', [CatalogController::class, 'categories']);
$router->get('/api/products', [ProductController::class, 'index']);          // ?cat=cpu,gpu&q=&min=&max=&sort=price_asc
$router->get('/api/products/{id}', [ProductController::class, 'show']);
$router->get('/api/presets', [PresetController::class, 'index']);            // ?purpose=gaming&min=&max=

// Конструктор
$router->post('/api/compatibility', [BuildController::class, 'check']);      // проверка совместимости на сервере
$router->post('/api/builds', [BuildController::class, 'store']);             // сохранить сборку и получить ссылку
$router->get('/api/builds/{code}', [BuildController::class, 'show']);

// Заказы
$router->post('/api/orders', [OrderController::class, 'store']);
$router->get('/api/orders/{token}', [OrderController::class, 'show']);       // статус заказа по секретному токену

// Отзывы
$router->get('/api/reviews', [ReviewController::class, 'index']);            // ?limit=&product=cpu1
$router->post('/api/reviews', [ReviewController::class, 'store']);

// Telegram (режим webhook; для локальной разработки — backend/bin/telegram-poll.php)
$router->post('/api/telegram/webhook', [TelegramController::class, 'webhook'], csrf: false);

return $router;
