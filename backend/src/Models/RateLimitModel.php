<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Учёт действий по IP для ограничения частоты запросов */
final class RateLimitModel
{
    public function count(string $action, string $ip, int $windowSeconds): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM rate_limits WHERE action = ? AND ip_address = ? AND created_at > NOW() - INTERVAL ? SECOND'
        );
        $stmt->execute([$action, $ip, $windowSeconds]);
        return (int) $stmt->fetchColumn();
    }

    public function hit(string $action, string $ip): void
    {
        Database::pdo()->prepare('INSERT INTO rate_limits (action, ip_address) VALUES (?, ?)')->execute([$action, $ip]);
        // изредка чистим старые записи
        if (random_int(1, 50) === 1) {
            Database::pdo()->exec('DELETE FROM rate_limits WHERE created_at < NOW() - INTERVAL 1 DAY');
        }
    }
}
