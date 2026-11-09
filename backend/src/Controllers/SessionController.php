<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;

final class SessionController
{
    /** GET /api/csrf — токен для заголовка X-CSRF-Token */
    public function csrf(Request $request): Response
    {
        return Response::json(['token' => Csrf::token()]);
    }
}
