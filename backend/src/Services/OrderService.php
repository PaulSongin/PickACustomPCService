<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Config;
use App\Core\HttpException;
use App\Models\OrderModel;
use App\Models\RateLimitModel;
use App\Services\Telegram\TelegramBot;
use App\Support\Format;
use App\Validators\OrderValidator;
use App\Validators\ValidationException;

final class OrderService
{
    public function __construct(
        private readonly OrderModel $orders = new OrderModel(),
        private readonly OrderValidator $validator = new OrderValidator(),
        private readonly CompatibilityService $compat = new CompatibilityService(),
        private readonly RateLimitModel $limits = new RateLimitModel(),
    ) {
    }

    /**
     * Оформить заказ: всё (состав, наличие, совместимость, цена) проверяется заново по БД.
     * @return array{id:int, token:string, status:string, total:int|float, trackUrl:?string}
     */
    public function create(array $body, string $ip): array
    {
        $max = (int) Config::get('orders.max_per_hour', 5);
        if ($this->limits->count('order', $ip, 3600) >= $max) {
            throw new HttpException(429, 'Слишком много заказов с вашего адреса. Попробуйте через час.');
        }

        $data = $this->validator->validate($body);
        $products = $data['products'];

        $conflicts = $this->compat->check($products);
        if ($conflicts) {
            throw new ValidationException('Сборка несовместима: ' . $conflicts[0]['text'], ['conflicts' => $conflicts]);
        }

        $total = array_sum(array_map(static fn (array $p): float => (float) $p['price'], $products));
        // цена на сайте могла устареть, пока клиент собирал ПК
        if ($data['clientTotal'] !== null && abs($data['clientTotal'] - $total) > 0.009) {
            throw new HttpException(409, 'Цены обновились: сборка теперь стоит ' . Format::moneyText($total)
                . '. Проверьте сборку и отправьте заказ ещё раз.', ['code' => 'price_changed', 'total' => Format::money($total)]);
        }

        $items = [];
        foreach ($products as $cat => $p) {
            $items[] = ['cat' => $cat, 'pk' => $p['_pk'], 'name' => $p['name'], 'price' => $p['price']];
        }
        $token = bin2hex(random_bytes(12));
        $id = $this->orders->create(
            ['token' => $token, 'name' => $data['name'], 'phone' => $data['phone'], 'total' => $total, 'ip' => $ip],
            $items
        );
        $this->limits->hit('order', $ip);

        $trackUrl = null;
        $bot = TelegramBot::fromConfig();
        if ($bot !== null) {
            $username = $bot->username();
            if ($username !== null) {
                $trackUrl = 'https://t.me/' . $username . '?start=' . $token;
            }
            // карточка заказа сборщикам — уже после ответа браузеру
            App::defer(static fn () => $bot->announceOrder($id));
        }

        return ['id' => $id, 'token' => $token, 'status' => 'new', 'total' => Format::money($total), 'trackUrl' => $trackUrl];
    }

    /** Статус заказа для клиента (по секретному токену из ссылки) */
    public function publicStatus(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $token)) {
            return null;
        }
        $order = $this->orders->findByToken($token);
        if ($order === null) {
            return null;
        }
        return [
            'id'           => $order['id'],
            'status'       => $order['status'],
            'statusText'   => OrderStatus::label($order['status']),
            'rejectReason' => $order['reject_reason'],
            'total'        => Format::money($order['total']),
            'createdAt'    => $order['created_at'],
            'updatedAt'    => $order['updated_at'],
            'items'        => array_map(static fn (array $i): array => [
                'cat'      => $i['category_code'],
                'category' => $i['category_name'],
                'id'       => $i['product_code'],
                'name'     => $i['product_name'],
                'price'    => Format::money($i['price']),
            ], $order['items']),
            'history'      => array_map(static fn (array $h): array => [
                'status'     => $h['status'],
                'statusText' => OrderStatus::label($h['status']),
                'at'         => $h['created_at'],
            ], $this->orders->history($order['id'])),
        ];
    }
}
