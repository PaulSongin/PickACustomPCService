<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Support\Format;
use PDO;

final class OrderModel
{
    private const ACTIVE = "status NOT IN ('done', 'rejected')";

    /** Диапазон номеров заказов: 6-значные числа */
    private const NUMBER_MIN = 100000;
    private const NUMBER_MAX = 999999;

    /**
     * Создать заказ вместе с составом и первой записью истории (в одной транзакции).
     * Номер заказа — случайный, а не следующий по счёту: по нему нельзя узнать количество заказов.
     * @param array{token:string, name:string, phone:string, total:float, ip:string} $order
     * @param list<array{cat:string, pk:int, name:string, price:float}> $items
     */
    public function create(array $order, array $items): int
    {
        return Database::transaction(static function (PDO $pdo) use ($order, $items): int {
            $insert = $pdo->prepare(
                'INSERT INTO orders (id, public_token, customer_name, phone, total, ip_address) VALUES (?, ?, ?, ?, ?, ?)'
            );
            for ($attempt = 1; ; $attempt++) {
                $id = random_int(self::NUMBER_MIN, self::NUMBER_MAX);
                try {
                    $insert->execute([$id, $order['token'], $order['name'], $order['phone'], $order['total'], $order['ip']]);
                    break;
                } catch (\PDOException $e) {
                    // такой номер уже занят — берём другой
                    $taken = $e->getCode() === '23000' && str_contains($e->getMessage(), 'PRIMARY');
                    if (!$taken || $attempt >= 20) {
                        throw $e;
                    }
                }
            }

            $stmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, category_code, product_id, product_name, price) VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($items as $item) {
                $stmt->execute([$id, $item['cat'], $item['pk'], $item['name'], $item['price']]);
            }

            $pdo->prepare('INSERT INTO order_status_history (order_id, status, changed_by) VALUES (?, ?, ?)')
                ->execute([$id, 'new', 'сайт']);
            return $id;
        });
    }

    public function find(int $id): ?array
    {
        return $this->findBy('id', $id);
    }

    public function findByToken(string $token): ?array
    {
        return $this->findBy('public_token', $token);
    }

    private function findBy(string $column, int|string $value): ?array
    {
        $stmt = Database::pdo()->prepare("SELECT * FROM orders WHERE $column = ?");
        $stmt->execute([$value]);
        $order = $stmt->fetch();
        if (!$order) {
            return null;
        }
        $stmt = Database::pdo()->prepare('
            SELECT i.category_code, c.name AS category_name, p.code AS product_code, i.product_name, i.price
            FROM order_items i
            JOIN categories c ON c.code = i.category_code
            JOIN products p   ON p.id = i.product_id
            WHERE i.order_id = ?
            ORDER BY c.sort_order');
        $stmt->execute([$order['id']]);
        $order['items'] = $stmt->fetchAll();
        $order['id'] = (int) $order['id'];
        $order['client_chat_id'] = $order['client_chat_id'] === null ? null : (int) $order['client_chat_id'];
        $order['builder_tg_id'] = $order['builder_tg_id'] === null ? null : (int) $order['builder_tg_id'];
        return $order;
    }

    /** Последние активные заказы (от старых к новым) */
    public function active(int $limit = 10): array
    {
        $stmt = Database::pdo()->prepare('SELECT id FROM orders WHERE ' . self::ACTIVE . ' ORDER BY created_at DESC, id DESC LIMIT ?');
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $ids = array_reverse(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
        return array_values(array_filter(array_map(fn (int $id) => $this->find($id), $ids)));
    }

    /** Заказы, привязанные к Telegram-чату клиента */
    public function byClientChat(int $chatId, bool $onlyActive = false): array
    {
        $sql = 'SELECT id, status, total FROM orders WHERE client_chat_id = ?' . ($onlyActive ? ' AND ' . self::ACTIVE : '') . ' ORDER BY created_at, id';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([$chatId]);
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'status' => $r['status'], 'total' => Format::money($r['total']),
        ], $stmt->fetchAll());
    }

    /** Привязать заказ к чату клиента. false — заказ уже привязан к другому чату */
    public function bindClientChat(int $id, int $chatId): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE orders SET client_chat_id = ? WHERE id = ? AND (client_chat_id IS NULL OR client_chat_id = ?)'
        );
        $stmt->execute([$chatId, $id, $chatId]);
        if ($stmt->rowCount() === 1) {
            return true;
        }
        $order = $this->find($id);
        return $order !== null && $order['client_chat_id'] === $chatId;
    }

    /**
     * Сменить статус, только если заказ сейчас в статусе $from (и выполняются условия $where).
     * Защищает от гонки: если два сборщика одновременно нажали «Принять», успешным будет только один.
     *
     * @param array<string, int|string|null> $set   доп. поля: builder_tg_id, builder_name, reject_reason
     * @param array<string, int|string>      $where доп. условия: builder_tg_id
     */
    public function transition(int $id, string $from, string $to, array $set = [], array $where = [], ?string $by = null, ?string $comment = null): bool
    {
        $allowedSet = ['builder_tg_id', 'builder_name', 'reject_reason'];
        $allowedWhere = ['builder_tg_id'];

        $sql = 'UPDATE orders SET status = ?';
        $params = [$to];
        foreach ($set as $column => $value) {
            if (!in_array($column, $allowedSet, true)) {
                throw new \InvalidArgumentException("Нельзя менять поле $column");
            }
            $sql .= ", $column = ?";
            $params[] = $value;
        }
        $sql .= ' WHERE id = ? AND status = ?';
        array_push($params, $id, $from);
        foreach ($where as $column => $value) {
            if (!in_array($column, $allowedWhere, true)) {
                throw new \InvalidArgumentException("Нельзя фильтровать по полю $column");
            }
            $sql .= " AND $column = ?";
            $params[] = $value;
        }

        return Database::transaction(static function (PDO $pdo) use ($sql, $params, $id, $to, $by, $comment): bool {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            if ($stmt->rowCount() !== 1) {
                return false;
            }
            $pdo->prepare('INSERT INTO order_status_history (order_id, status, comment, changed_by) VALUES (?, ?, ?, ?)')
                ->execute([$id, $to, $comment, $by]);
            return true;
        });
    }

    public function history(int $id): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT status, comment, created_at FROM order_status_history WHERE order_id = ? ORDER BY id'
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }
}
