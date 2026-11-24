<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\BuildService;

final class BuildController
{
    /** POST /api/compatibility  {"items": {"cpu": "cpu2", "mb": "mb2", ...}} */
    public function check(Request $request): Response
    {
        return Response::json((new BuildService())->check($request->json()['items'] ?? null));
    }

    /** POST /api/builds  {"items": {...}} → {"code": "aB3dE5fG"} */
    public function store(Request $request): Response
    {
        $code = (new BuildService())->save($request->json()['items'] ?? null, $request->ip);
        return Response::json(['code' => $code, 'link' => '#/builder?build=' . $code], 201);
    }

    /** GET /api/builds/{code} */
    public function show(Request $request, string $code): Response
    {
        $build = (new BuildService())->find($code);
        if ($build === null) {
            throw new HttpException(404, 'Сборка по этой ссылке не найдена');
        }
        return Response::json($build);
    }
}
