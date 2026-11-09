<?php
/**
 * Установка базы данных: создаёт БД (если её нет), таблицы и начальные данные.
 *
 *   php backend/bin/install.php          — установить (откажется, если таблицы уже есть)
 *   php backend/bin/install.php --fresh  — удалить все таблицы и установить заново
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Только для командной строки\n");
}
require __DIR__ . '/../bootstrap.php';

use App\Core\Config;

$fresh = in_array('--fresh', $argv, true);
$db = Config::get('db');

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $db['host'], (int) $db['port']),
        $db['user'],
        $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "Не удалось подключиться к MySQL {$db['host']}:{$db['port']} — {$e->getMessage()}\n"
        . "Проверьте, что MySQL запущен и настройки в backend/config/config.php верны.\n");
    exit(1);
}

$name = str_replace('`', '', $db['name']);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `$name`");

$exists = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'products'")->fetchColumn();
if ($exists && !$fresh) {
    fwrite(STDERR, "В базе `$name` уже есть таблицы. Чтобы удалить их и установить заново: php backend/bin/install.php --fresh\n");
    exit(1);
}

foreach (['schema.sql', 'seed.sql'] as $file) {
    $sql = file_get_contents(APP_ROOT . '/database/' . $file);
    // убираем комментарии и делим на запросы по «;» в конце строки
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql)));
    foreach ($statements as $statement) {
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            fwrite(STDERR, "Ошибка в $file: {$e->getMessage()}\n---\n" . mb_substr($statement, 0, 300) . "\n");
            exit(1);
        }
    }
    echo "✓ $file (" . count($statements) . " запросов)\n";
}

$counts = $pdo->query('SELECT (SELECT COUNT(*) FROM products) p, (SELECT COUNT(*) FROM presets) s, (SELECT COUNT(*) FROM reviews) r')->fetch(PDO::FETCH_ASSOC);
echo "Готово: база `$name`, товаров — {$counts['p']}, готовых сборок — {$counts['s']}, отзывов — {$counts['r']}.\n";
