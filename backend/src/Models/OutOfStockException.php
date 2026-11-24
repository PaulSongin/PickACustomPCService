<?php
declare(strict_types=1);

namespace App\Models;

final class OutOfStockException extends \RuntimeException
{
    public function __construct(public readonly string $productName)
    {
        parent::__construct("Товара «{$productName}» больше нет в наличии");
    }
}
