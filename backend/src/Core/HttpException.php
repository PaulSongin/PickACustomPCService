<?php
declare(strict_types=1);

namespace App\Core;

/** Ошибка, которую можно показать клиенту: код ответа + текст (+ доп. поля в JSON) */
class HttpException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $extra = []
    ) {
        parent::__construct($message, $status);
    }
}
