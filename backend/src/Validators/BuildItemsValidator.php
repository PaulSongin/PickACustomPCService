<?php
declare(strict_types=1);

namespace App\Validators;

use App\Models\CategoryModel;
use App\Models\ProductModel;

/** Проверка состава сборки { категория: артикул } по каталогу в БД */
final class BuildItemsValidator
{
    public function __construct(
        private readonly ProductModel $products = new ProductModel(),
        private readonly CategoryModel $categories = new CategoryModel(),
    ) {
    }

    /**
     * @param bool $forOrder заказ: товар должен продаваться и быть в наличии, все обязательные категории заполнены
     * @return array<string, array> категория → товар (с полями _pk, _active), в порядке категорий
     */
    public function validate(mixed $items, bool $forOrder): array
    {
        if (!is_array($items) || $items === [] || array_is_list($items)) {
            throw new ValidationException('Сборка пуста');
        }

        $names = $this->categories->names();
        foreach ($items as $cat => $code) {
            if (!isset($names[$cat]) || !is_string($code) || $code === '' || strlen($code) > 32) {
                throw new ValidationException('В сборке неизвестный товар');
            }
        }

        $found = $this->products->findByCodes(array_values($items));
        $result = [];
        foreach ($names as $cat => $catName) {           // порядок — как у категорий
            if (!isset($items[$cat])) {
                continue;
            }
            $product = $found[$items[$cat]] ?? null;
            if ($product === null || $product['cat'] !== $cat) {
                throw new ValidationException('В сборке неизвестный товар');
            }
            if ($forOrder && !$product['_active']) {
                throw new ValidationException("Товар «{$product['name']}» снят с продажи — замените его в сборке");
            }
            if ($forOrder && $product['stock'] <= 0) {
                throw new ValidationException("Товара «{$product['name']}» нет в наличии — замените его в сборке");
            }
            $result[$cat] = $product;
        }

        if ($forOrder) {
            $missing = array_diff($this->categories->required(), array_keys($result));
            if ($missing) {
                $list = implode(', ', array_map(static fn (string $c): string => mb_strtolower($names[$c]), $missing));
                throw new ValidationException("В сборке не хватает: $list", ['missing' => array_values($missing)]);
            }
        }
        return $result;
    }
}
