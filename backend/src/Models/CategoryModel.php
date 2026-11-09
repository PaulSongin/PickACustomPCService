<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class CategoryModel
{
    private ?array $cache = null;

    /** @return list<array{code:string, name:string, required:bool}> */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $rows = Database::pdo()
            ->query('SELECT code, name, is_required FROM categories ORDER BY sort_order, code')
            ->fetchAll();

        return $this->cache = array_map(static fn (array $r): array => [
            'code'     => $r['code'],
            'name'     => $r['name'],
            'required' => (bool) $r['is_required'],
        ], $rows);
    }

    /** @return array<string, string> код → название */
    public function names(): array
    {
        return array_column($this->all(), 'name', 'code');
    }

    /** @return list<string> коды обязательных категорий */
    public function required(): array
    {
        return array_column(array_filter($this->all(), static fn (array $c): bool => $c['required']), 'code');
    }
}
