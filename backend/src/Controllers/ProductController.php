<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\CategoryModel;
use App\Models\ProductModel;
use App\Validators\ValidationException;

final class ProductController
{
    /**
     * GET /api/products?cat=cpu,gpu&q=ryzen&min=100&max=2000&sort=price_asc
     * sort: default | price_asc | price_desc | name | rating
     */
    public function index(Request $request): Response
    {
        $filters = [];

        $cat = $request->query('cat', '');
        if ($cat !== '') {
            $cats = array_values(array_unique(array_filter(array_map('trim', explode(',', $cat)))));
            $known = array_column((new CategoryModel())->all(), 'code');
            if (array_diff($cats, $known)) {
                throw new ValidationException('Неизвестная категория. Доступны: ' . implode(', ', $known));
            }
            $filters['categories'] = $cats;
        }

        $q = $request->query('q', '');
        if (mb_strlen($q) > 100) {
            throw new ValidationException('Слишком длинный поисковый запрос');
        }
        $filters['q'] = $q;

        foreach (['min', 'max'] as $key) {
            $value = $request->query($key, '');
            if ($value === '') {
                continue;
            }
            if (!is_numeric($value) || $value < 0) {
                throw new ValidationException("Параметр $key должен быть неотрицательным числом");
            }
            $filters[$key] = (float) $value;
        }

        $sort = $request->query('sort', 'default');
        if (!isset(ProductModel::SORTS[$sort])) {
            throw new ValidationException('Сортировка: ' . implode(', ', array_keys(ProductModel::SORTS)));
        }
        $filters['sort'] = $sort;

        $items = (new ProductModel())->list($filters);
        return Response::json(['items' => $items, 'count' => count($items)]);
    }

    /** GET /api/products/{id} */
    public function show(Request $request, string $id): Response
    {
        $product = (new ProductModel())->findByCode($id);
        if ($product === null) {
            throw new HttpException(404, 'Товар не найден');
        }
        return Response::json($product);
    }
}
