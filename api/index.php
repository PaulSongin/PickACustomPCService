<?php
/**
 * Единая точка входа API: все запросы /api/* попадают сюда
 * (Apache — через .htaccess, встроенный сервер PHP — через router.php).
 */
declare(strict_types=1);

require __DIR__ . '/../backend/bootstrap.php';

App\Core\App::run(require BACKEND_ROOT . '/routes.php');
