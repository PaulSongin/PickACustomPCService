<?php
declare(strict_types=1);

namespace App\Validators;

use App\Support\Format;

final class OrderValidator
{
    /** +375-XX-XXX-XX-XX, коды городов и мобильных операторов Беларуси */
    public const PHONE = '/^\+375-(15|16|17|21|22|23|25|29|33|44)-\d{3}-\d{2}-\d{2}$/';

    public function __construct(private readonly BuildItemsValidator $items = new BuildItemsValidator())
    {
    }

    /** @return array{name:string, phone:string, products:array<string,array>, clientTotal:?float} */
    public function validate(array $body): array
    {
        $name = Format::cleanText($body['name'] ?? '');
        $length = mb_strlen($name);
        if ($length < 2 || $length > 60) {
            throw new ValidationException('Укажите имя (2–60 символов)', ['field' => 'name']);
        }

        $phone = is_string($body['phone'] ?? null) ? trim($body['phone']) : '';
        if (!preg_match(self::PHONE, $phone)) {
            throw new ValidationException('Неверный формат телефона: +375-XX-XXX-XX-XX', ['field' => 'phone']);
        }

        $products = $this->items->validate($body['items'] ?? null, true);

        $clientTotal = null;
        if (isset($body['total'])) {
            if (!is_int($body['total']) && !is_float($body['total'])) {
                throw new ValidationException('Неверная сумма заказа');
            }
            $clientTotal = (float) $body['total'];
        }

        return ['name' => $name, 'phone' => $phone, 'products' => $products, 'clientTotal' => $clientTotal];
    }
}
