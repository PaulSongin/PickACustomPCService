<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\HttpException;
use App\Models\BuildModel;
use App\Models\CategoryModel;
use App\Models\RateLimitModel;
use App\Support\Format;
use App\Validators\BuildItemsValidator;

final class BuildService
{
    public function __construct(
        private readonly BuildModel $builds = new BuildModel(),
        private readonly BuildItemsValidator $validator = new BuildItemsValidator(),
        private readonly CompatibilityService $compat = new CompatibilityService(),
        private readonly CategoryModel $categories = new CategoryModel(),
        private readonly RateLimitModel $limits = new RateLimitModel(),
    ) {
    }

    /** Полная проверка сборки на сервере: конфликты, недостающие детали, цена, мощность */
    public function check(mixed $items): array
    {
        $products = $this->validator->validate($items, false);
        $conflicts = $this->compat->check($products);
        $missing = array_values(array_diff($this->categories->required(), array_keys($products)));

        return [
            'ok'        => !$conflicts && !$missing,
            'conflicts' => $conflicts,
            'missing'   => $missing,
            'total'     => Format::money(array_sum(array_map(static fn (array $p): float => (float) $p['price'], $products))),
            'power'     => [
                'required' => $this->compat->requiredPower($products),
                'psu'      => $products['psu']['watt'] ?? null,
            ],
        ];
    }

    /** Сохранить сборку и вернуть код для ссылки */
    public function save(mixed $items, string $ip): string
    {
        $max = (int) Config::get('builds.max_per_hour', 30);
        if ($this->limits->count('build', $ip, 3600) >= $max) {
            throw new HttpException(429, 'Слишком много сохранённых сборок. Попробуйте позже.');
        }
        $products = $this->validator->validate($items, false);
        $code = $this->builds->create(array_map(static fn (array $p): int => $p['_pk'], $products), $ip);
        $this->limits->hit('build', $ip);
        return $code;
    }

    public function find(string $code): ?array
    {
        return $this->builds->findByCode($code);
    }
}
