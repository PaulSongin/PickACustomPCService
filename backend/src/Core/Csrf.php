<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Защита от CSRF: токен хранится в сессии, фронтенд получает его через GET /api/csrf
 * и передаёт в заголовке X-CSRF-Token при каждом POST-запросе.
 */
final class Csrf
{
    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('PCBSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }

    public static function token(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        $token = $_SESSION['csrf'];
        session_write_close();
        return $token;
    }

    public static function verify(Request $request): void
    {
        self::startSession();
        $expected = $_SESSION['csrf'] ?? '';
        session_write_close(); // не держим блокировку сессии, пока идёт обработка запроса

        $given = $request->header('X-CSRF-Token') ?? '';
        if ($expected === '' || !hash_equals($expected, $given)) {
            throw new HttpException(403, 'Сессия устарела. Обновите страницу и попробуйте ещё раз.', ['code' => 'csrf']);
        }
    }
}
