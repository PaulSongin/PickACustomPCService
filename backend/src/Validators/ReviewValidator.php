<?php
declare(strict_types=1);

namespace App\Validators;

use App\Support\Format;

final class ReviewValidator
{
    /** @return array{name:string, rating:int, text:string, product:?string} */
    public function validate(array $body): array
    {
        $name = Format::cleanText($body['name'] ?? '');
        $length = mb_strlen($name);
        if ($length < 2 || $length > 60) {
            throw new ValidationException('Укажите имя (2–60 символов)', ['field' => 'name']);
        }

        $rating = $body['rating'] ?? null;
        if (is_string($rating) && ctype_digit($rating)) {
            $rating = (int) $rating;
        }
        if (!is_int($rating) || $rating < 1 || $rating > 5) {
            throw new ValidationException('Оценка должна быть от 1 до 5', ['field' => 'rating']);
        }

        $text = Format::cleanText($body['text'] ?? '', true);
        $length = mb_strlen($text);
        if ($length < 10) {
            throw new ValidationException('Отзыв слишком короткий (от 10 символов)', ['field' => 'text']);
        }
        if ($length > 1000) {
            throw new ValidationException('Отзыв слишком длинный (до 1000 символов)', ['field' => 'text']);
        }

        $product = $body['product'] ?? null;
        if ($product !== null && (!is_string($product) || $product === '' || strlen($product) > 32)) {
            throw new ValidationException('Товар не найден', ['field' => 'product']);
        }

        return ['name' => $name, 'rating' => $rating, 'text' => $text, 'product' => $product];
    }
}
