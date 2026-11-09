<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Ядро API: маршрутизация, единый формат ошибок и отложенные задачи
 * (например, уведомление в Telegram выполняется уже после ответа браузеру).
 */
final class App
{
    /** @var list<callable> */
    private static array $deferred = [];

    public static function defer(callable $task): void
    {
        self::$deferred[] = $task;
    }

    public static function run(Router $router): void
    {
        try {
            $response = $router->dispatch(Request::fromGlobals());
        } catch (HttpException $e) {
            $response = Response::json(['error' => $e->getMessage()] + $e->extra, $e->status);
        } catch (\Throwable $e) {
            Logger::error($e);
            $message = 'Внутренняя ошибка сервера';
            if (Config::get('app.debug')) {
                $message .= ': ' . $e->getMessage();
            }
            $response = Response::json(['error' => $message], 500);
        }

        $response->send();
        self::runDeferred();
    }

    private static function runDeferred(): void
    {
        if (!self::$deferred) {
            return;
        }
        // отдаём ответ клиенту, а долгие задачи выполняем после
        ignore_user_abort(true);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
        foreach (self::$deferred as $task) {
            try {
                $task();
            } catch (\Throwable $e) {
                Logger::error($e);
            }
        }
        self::$deferred = [];
    }
}
