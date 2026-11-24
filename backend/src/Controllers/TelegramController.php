<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Services\Telegram\TelegramBot;

final class TelegramController
{
    /**
     * POST /api/telegram/webhook — обновления от Telegram в режиме webhook.
     * Запрос принимается только с секретом из настроек (заголовок X-Telegram-Bot-Api-Secret-Token).
     */
    public function webhook(Request $request): Response
    {
        $secret = (string) Config::get('telegram.webhook_secret', '');
        $given = $request->header('X-Telegram-Bot-Api-Secret-Token') ?? '';
        if ($secret === '' || !hash_equals($secret, $given)) {
            throw new HttpException(403, 'Forbidden');
        }
        $bot = TelegramBot::fromConfig();
        if ($bot === null) {
            throw new HttpException(503, 'Бот не настроен');
        }
        try {
            $bot->handleUpdate($request->json());
        } catch (\Throwable $e) {
            // отвечаем 200, иначе Telegram будет бесконечно повторять это обновление
            Logger::error($e);
        }
        return Response::json(['ok' => true]);
    }
}
