<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/** Одно PDO-подключение на запрос. Все запросы к БД — только подготовленные выражения. */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = Config::get('db');
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $c['host'], (int) $c['port'], $c['name'], $c['charset'] ?? 'utf8mb4'
            );
            try {
                self::$pdo = new PDO($dsn, $c['user'], $c['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                Logger::error('DB connect failed: ' . $e->getMessage());
                throw new HttpException(503, 'База данных недоступна. Попробуйте позже.');
            }
            // время в БД — в том же часовом поясе, что и PHP
            self::$pdo->exec("SET time_zone = '" . (new \DateTimeImmutable())->format('P') . "'");
        }
        return self::$pdo;
    }

    /** Разорвать соединение (для долгоживущего бота, если MySQL закрыл подключение) */
    public static function disconnect(): void
    {
        self::$pdo = null;
    }

    /** Выполнить функцию в транзакции */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
