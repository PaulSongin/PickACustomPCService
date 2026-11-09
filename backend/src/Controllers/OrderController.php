<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\OrderService;

final class OrderController
{
    /**
     * POST /api/orders
     * {"name": "Иван", "phone": "+375-29-123-45-67", "items": {"cpu": "cpu2", ...}, "total": 4110}
     * total необязателен: если передан и не совпадает с ценой в БД — 409 (цены обновились).
     */
    public function store(Request $request): Response
    {
        $result = (new OrderService())->create($request->json(), $request->ip);
        return Response::json($result, 201);
    }

    /** GET /api/orders/{token} — статус заказа */
    public function show(Request $request, string $token): Response
    {
        $order = (new OrderService())->publicStatus($token);
        if ($order === null) {
            throw new HttpException(404, 'Заказ не найден');
        }
        return Response::json($order);
    }
}
