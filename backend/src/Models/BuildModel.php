<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

/** Сохранённые сборки, которыми делятся по ссылке */
final class BuildModel
{
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // без похожих символов

    /**
     * @param array<string, int> $items категория → id товара в БД
     * @return string код сборки
     */
    public function create(array $items, string $ip): string
    {
        return Database::transaction(function (PDO $pdo) use ($items, $ip): string {
            for ($attempt = 0; ; $attempt++) {
                $code = $this->randomCode();
                try {
                    $pdo->prepare('INSERT INTO builds (code, ip_address) VALUES (?, ?)')->execute([$code, $ip]);
                    break;
                } catch (\PDOException $e) {
                    // 23000 — нарушение уникальности: такой код уже есть, пробуем другой
                    if ($e->getCode() !== '23000' || $attempt >= 5) {
                        throw $e;
                    }
                }
            }
            $buildId = (int) $pdo->lastInsertId();
            $stmt = $pdo->prepare('INSERT INTO build_items (build_id, category_code, product_id) VALUES (?, ?, ?)');
            foreach ($items as $cat => $productId) {
                $stmt->execute([$buildId, $cat, $productId]);
            }
            return $code;
        });
    }

    /** @return array{code:string, items:array<string,string>, created_at:string}|null */
    public function findByCode(string $code): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT id, code, created_at FROM builds WHERE code = ?');
        $stmt->execute([$code]);
        $build = $stmt->fetch();
        if (!$build) {
            return null;
        }
        $stmt = Database::pdo()->prepare('
            SELECT bi.category_code, p.code
            FROM build_items bi
            JOIN products p   ON p.id = bi.product_id
            JOIN categories c ON c.code = bi.category_code
            WHERE bi.build_id = ? AND p.is_active = 1
            ORDER BY c.sort_order');
        $stmt->execute([$build['id']]);
        return [
            'code'       => $build['code'],
            'items'      => $stmt->fetchAll(PDO::FETCH_KEY_PAIR),
            'created_at' => $build['created_at'],
        ];
    }

    private function randomCode(int $length = 8): string
    {
        $code = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }
        return $code;
    }
}
