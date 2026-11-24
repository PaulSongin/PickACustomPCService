<?php
declare(strict_types=1);

namespace App\Validators;

use App\Core\HttpException;

/** Ошибка во входных данных — 422 и понятный текст для пользователя */
final class ValidationException extends HttpException
{
    public function __construct(string $message, array $extra = [])
    {
        parent::__construct(422, $message, $extra);
    }
}
