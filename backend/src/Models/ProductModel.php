<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Support\Format;

/**
 * Товары каталога. Наружу отдаются в том же формате, что использует фронтенд:
 * { id, cat, name, spec, price, stock, socket, mem, ff, tdp, len, h, tdpMax, sockets, gpuMax, coolerMax, watt }
 */
final class ProductModel
{
    private const SELECT = '
        SELECT p.*, r.avg_rating, r.reviews_count
        FROM products p
        JOIN categories c ON c.code = p.category_code
        LEFT JOIN v_product_ratings r ON r.product_id = p.id';

    public const SORTS = [
        'default'    => 'c.sort_order, p.id',
        'price_asc'  => 'p.price ASC, p.id',
        'price_desc' => 'p.price DESC, p.id',
        'name'       => 'p.name ASC, p.id',
        'rating'     => 'r.avg_rating IS NULL, r.avg_rating DESC, p.id',
    ];

    /**
     * Список активных товаров с фильтрами.
     * @param array{categories?:list<string>, q?:string, min?:float, max?:float, sort?:string} $filters
     */
    public function list(array $filters = []): array
    {
        $where = ['p.is_active = 1'];
        $params = [];

        if (!empty($filters['categories'])) {
            $where[] = 'p.category_code IN (' . implode(',', array_fill(0, count($filters['categories']), '?')) . ')';
            array_push($params, ...$filters['categories']);
        }
        if (isset($filters['min'])) {
            $where[] = 'p.price >= ?';
            $params[] = $filters['min'];
        }
        if (isset($filters['max'])) {
            $where[] = 'p.price <= ?';
            $params[] = $filters['max'];
        }
        if (($filters['q'] ?? '') !== '') {
            $like = '%' . addcslashes($filters['q'], '%_\\') . '%';
            $where[] = '(p.name LIKE ? OR p.spec LIKE ? OR c.name LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $order = self::SORTS[$filters['sort'] ?? 'default'] ?? self::SORTS['default'];

        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order);
        $stmt->execute($params);
        return $this->hydrate($stmt->fetchAll());
    }

    public function findByCode(string $code): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE p.code = ? AND p.is_active = 1');
        $stmt->execute([$code]);
        $rows = $this->hydrate($stmt->fetchAll());
        return $rows[0] ?? null;
    }

    /**
     * Товары по артикулам, включая снятые с продажи — для проверки заказов.
     * Дополнительно содержат служебные поля _pk (id в БД) и _active.
     * @return array<string, array> артикул → товар
     */
    public function findByCodes(array $codes): array
    {
        $codes = array_values(array_unique(array_filter($codes, 'is_string')));
        if (!$codes) {
            return [];
        }
        $stmt = Database::pdo()->prepare(
            self::SELECT . ' WHERE p.code IN (' . implode(',', array_fill(0, count($codes), '?')) . ')'
        );
        $stmt->execute($codes);
        $result = [];
        foreach ($this->hydrate($stmt->fetchAll(), true) as $product) {
            $result[$product['id']] = $product;
        }
        return $result;
    }

    /** Убрать служебные поля (_pk, _active) перед отдачей наружу */
    public static function publicView(array $product): array
    {
        return array_filter($product, static fn (string $k): bool => $k[0] !== '_', ARRAY_FILTER_USE_KEY);
    }

    private function hydrate(array $rows, bool $internal = false): array
    {
        if (!$rows) {
            return [];
        }
        $ids = array_map(static fn (array $r): int => (int) $r['id'], $rows);
        $sockets = $this->multiValues('SELECT product_id, socket AS v FROM cooler_sockets', $ids);
        $formFactors = $this->multiValues('SELECT product_id, form_factor AS v FROM case_form_factors', $ids);

        $result = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $p = [
                'id'    => $r['code'],
                'cat'   => $r['category_code'],
                'name'  => $r['name'],
                'spec'  => $r['spec'],
                'price' => Format::money($r['price']),
                'stock' => (int) $r['stock'],
            ];
            if ($r['image'] !== null && $r['image'] !== '') {
                $p['img'] = $r['image'];
            }
            // характеристики совместимости — только заполненные
            $map = [
                'socket' => 'socket', 'memory_type' => 'mem', 'form_factor' => 'ff', 'tdp_w' => 'tdp',
                'length_mm' => 'len', 'height_mm' => 'h', 'max_tdp_w' => 'tdpMax',
                'max_gpu_length_mm' => 'gpuMax', 'max_cooler_height_mm' => 'coolerMax', 'power_w' => 'watt',
            ];
            foreach ($map as $column => $key) {
                if ($r[$column] !== null) {
                    $p[$key] = is_numeric($r[$column]) ? (int) $r[$column] : $r[$column];
                }
            }
            if ($r['category_code'] === 'cooler') {
                $p['sockets'] = $sockets[$id] ?? [];
            }
            if ($r['category_code'] === 'case') {
                $p['ff'] = $formFactors[$id] ?? [];
            }
            if ($r['reviews_count'] !== null) {
                $p['rating'] = (float) $r['avg_rating'];
                $p['reviews'] = (int) $r['reviews_count'];
            }
            if ($internal) {
                $p['_pk'] = $id;
                $p['_active'] = (bool) $r['is_active'];
            }
            $result[] = $p;
        }
        return $result;
    }

    /** @return array<int, list<string>> id товара → значения */
    private function multiValues(string $sql, array $ids): array
    {
        $stmt = Database::pdo()->prepare(
            $sql . ' WHERE product_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY v'
        );
        $stmt->execute($ids);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[(int) $row['product_id']][] = $row['v'];
        }
        return $result;
    }
}
