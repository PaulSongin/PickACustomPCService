<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\CategoryModel;
use App\Models\PresetModel;
use App\Models\ProductModel;

final class CatalogController
{
    /** GET /api/catalog — всё, что нужно фронтенду при загрузке, одним запросом */
    public function index(Request $request): Response
    {
        return Response::json([
            'categories' => (new CategoryModel())->all(),
            'products'   => (new ProductModel())->list(),
            'presets'    => (new PresetModel())->list(),
        ]);
    }

    /** GET /api/categories */
    public function categories(Request $request): Response
    {
        return Response::json(['items' => (new CategoryModel())->all()]);
    }
}
