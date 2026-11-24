<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\ProductModel;
use App\Models\ReviewModel;
use App\Services\ReviewService;

final class ReviewController
{
    /** GET /api/reviews?limit=50&product=cpu1 — одобренные отзывы, средняя оценка и количество */
    public function index(Request $request): Response
    {
        $limit = (int) $request->query('limit', '50');
        $limit = max(1, min(100, $limit));

        $productId = null;
        $code = $request->query('product', '');
        if ($code !== '') {
            $found = (new ProductModel())->findByCodes([$code]);
            if (!isset($found[$code])) {
                throw new HttpException(404, 'Товар не найден');
            }
            $productId = $found[$code]['_pk'];
        }

        $model = new ReviewModel();
        return Response::json(['items' => $model->approved($limit, $productId)] + $model->stats($productId));
    }

    /** POST /api/reviews  {"name": "...", "rating": 5, "text": "...", "product": "cpu1"?} */
    public function store(Request $request): Response
    {
        $result = (new ReviewService())->create($request->json(), $request->ip);
        $result['message'] = $result['status'] === 'approved'
            ? 'Спасибо! Отзыв опубликован.'
            : 'Спасибо! Отзыв появится на сайте после проверки модератором.';
        return Response::json($result, 201);
    }
}
