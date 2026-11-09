<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Support\Format;

/** Готовые сборки: { id, tag, title, purpose, items: {cat: артикул}, total } */
final class PresetModel
{
    public const PURPOSES = ['office', 'gaming', 'streaming', 'graphics'];

    public function list(?string $purpose = null): array
    {
        $sql = '
            SELECT pr.code, pr.tag, pr.title, pr.purpose, pi.category_code, p.code AS product_code, p.price
            FROM presets pr
            JOIN preset_items pi ON pi.preset_id = pr.id
            JOIN products p      ON p.id = pi.product_id
            JOIN categories c    ON c.code = pi.category_code
            WHERE pr.is_active = 1';
        $params = [];
        if ($purpose !== null) {
            $sql .= ' AND pr.purpose = ?';
            $params[] = $purpose;
        }
        $sql .= ' ORDER BY pr.sort_order, pr.id, c.sort_order';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);

        $presets = [];
        foreach ($stmt->fetchAll() as $r) {
            $code = $r['code'];
            $presets[$code] ??= [
                'id'      => $code,
                'tag'     => $r['tag'],
                'title'   => $r['title'],
                'purpose' => $r['purpose'],
                'items'   => [],
                'total'   => 0,
            ];
            $presets[$code]['items'][$r['category_code']] = $r['product_code'];
            $presets[$code]['total'] += (float) $r['price'];
        }
        foreach ($presets as &$p) {
            $p['total'] = Format::money($p['total']);
        }
        return array_values($presets);
    }
}
