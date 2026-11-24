<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class ReviewModel
{
    /** Одобренные отзывы, новые первыми */
    public function approved(int $limit, ?int $productId = null): array
    {
        $sql = '
            SELECT r.id, r.author_name, r.rating, r.text, r.created_at, p.code AS product_code, p.name AS product_name
            FROM reviews r
            LEFT JOIN products p ON p.id = r.product_id
            WHERE r.status = \'approved\'';
        $params = [];
        if ($productId !== null) {
            $sql .= ' AND r.product_id = ?';
            $params[] = $productId;
        }
        $sql .= ' ORDER BY r.created_at DESC, r.id DESC LIMIT ' . max(1, $limit);

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return array_map([$this, 'publicView'], $stmt->fetchAll());
    }

    /** Средняя оценка и количество одобренных отзывов */
    public function stats(?int $productId = null): array
    {
        $sql = 'SELECT COUNT(*) AS cnt, AVG(rating) AS avg_rating FROM reviews WHERE status = \'approved\'';
        $params = [];
        if ($productId !== null) {
            $sql .= ' AND product_id = ?';
            $params[] = $productId;
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return [
            'count' => (int) $row['cnt'],
            'avg'   => $row['avg_rating'] === null ? 0.0 : round((float) $row['avg_rating'], 1),
        ];
    }

    /** @param array{product_id:?int, name:string, rating:int, text:string, status:string, ip:string} $d */
    public function create(array $d): int
    {
        Database::pdo()->prepare(
            'INSERT INTO reviews (product_id, author_name, rating, text, status, ip_address) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$d['product_id'], $d['name'], $d['rating'], $d['text'], $d['status'], $d['ip']]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT r.*, p.name AS product_name FROM reviews r LEFT JOIN products p ON p.id = r.product_id WHERE r.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['id'] = (int) $row['id'];
        $row['rating'] = (int) $row['rating'];
        return $row;
    }

    /** Отзывы на модерации, старые первыми */
    public function pending(int $limit = 20): array
    {
        $stmt = Database::pdo()->prepare('SELECT id FROM reviews WHERE status = \'pending\' ORDER BY id LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_values(array_filter(array_map(
            fn ($id) => $this->find((int) $id),
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        )));
    }

    /** Одобрить / отклонить. false — отзыв уже кто-то промодерировал */
    public function moderate(int $id, string $status, string $by): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE reviews SET status = ?, moderated_by = ?, moderated_at = NOW() WHERE id = ? AND status = \'pending\''
        );
        $stmt->execute([$status, $by, $id]);
        return $stmt->rowCount() === 1;
    }

    /** Такой же текст с этого IP за последние сутки (защита от повторной отправки) */
    public function isDuplicate(string $ip, string $text): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM reviews WHERE ip_address = ? AND text = ? AND created_at > NOW() - INTERVAL 1 DAY LIMIT 1'
        );
        $stmt->execute([$ip, $text]);
        return (bool) $stmt->fetchColumn();
    }

    private function publicView(array $r): array
    {
        $review = [
            'id'     => (int) $r['id'],
            'name'   => $r['author_name'],
            'rating' => (int) $r['rating'],
            'text'   => $r['text'],
            'date'   => substr((string) $r['created_at'], 0, 10),
        ];
        if ($r['product_code'] !== null) {
            $review['product'] = ['id' => $r['product_code'], 'name' => $r['product_name']];
        }
        return $review;
    }
}
