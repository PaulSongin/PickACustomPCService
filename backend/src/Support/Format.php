<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Config;

final class Format
{
    /** DECIMAL из БД → число для JSON: 280.00 → 280, 280.50 → 280.5 */
    public static function money(string|int|float $value): int|float
    {
        $value = round((float) $value, 2);
        return floor($value) == $value ? (int) $value : $value;
    }

    /** Цена для сообщений: 4110 → «4 110 BYN» */
    public static function moneyText(string|int|float $value): string
    {
        $value = round((float) $value, 2);
        $decimals = floor($value) == $value ? 0 : 2;
        return number_format($value, $decimals, ',', "\u{00A0}") . "\u{00A0}" . Config::get('app.currency', 'BYN');
    }

    /** Экранирование для Telegram (parse_mode=HTML) */
    public static function html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** Убрать управляющие символы и лишние пробелы из пользовательского текста */
    public static function cleanText(mixed $value, bool $multiline = false): string
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return '';
        }
        $text = (string) $value;
        if (!mb_check_encoding($text, 'UTF-8')) {
            return '';
        }
        if ($multiline) {
            $text = str_replace(["\r\n", "\r"], "\n", $text);
            $text = preg_replace('/[^\P{C}\n]/u', '', $text) ?? '';
            $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? '';
            $text = preg_replace('/[ \t]+/u', ' ', $text) ?? '';
        } else {
            $text = preg_replace('/\p{C}/u', '', $text) ?? '';
            $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        }
        return trim($text);
    }
}
