<?php
/**
 * Telegram-бот в режиме опроса (long polling) — для запуска на своём компьютере,
 * когда у сайта нет публичного HTTPS-адреса для webhook.
 *
 *   php backend/bin/telegram-poll.php
 *
 * Остановить: Ctrl+C. Пока скрипт не запущен, заказы всё равно сохраняются в БД
 * и уходят сборщикам, но кнопки и сообщения в боте не обрабатываются.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Только для командной строки\n");
}
require __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Core\Logger;
use App\Models\TelegramStateModel;
use App\Services\Telegram\TelegramBot;
use App\Services\Telegram\TelegramException;

set_time_limit(0);

$bot = TelegramBot::fromConfig();
if ($bot === null) {
    fwrite(STDERR, "Заполните telegram.token и telegram.builder_ids в backend/config/config.php\n");
    exit(1);
}

try {
    $me = $bot->api()->call('getMe', [], 10);
    $webhook = $bot->api()->call('getWebhookInfo', [], 10);
} catch (TelegramException $e) {
    fwrite(STDERR, "Не удалось подключиться к Telegram (проверьте токен и интернет): {$e->getMessage()}\n");
    exit(1);
}
if (!empty($webhook['url'])) {
    fwrite(STDERR, "У бота включён webhook ({$webhook['url']}), опрос с ним не работает.\n"
        . "Отключите его: https://api.telegram.org/bot<TOKEN>/deleteWebhook — или используйте только webhook.\n");
    exit(1);
}

$state = new TelegramStateModel();
$state->set('bot_username:' . substr(sha1((string) App\Core\Config::get('telegram.token')), 0, 12), (string) $me['username']);
echo "Бот @{$me['username']} запущен. Сборщиков: " . count($bot->builders()) . ". Остановить: Ctrl+C\n";

for (;;) {
    try {
        $offset = (int) ($state->get('telegram_offset') ?? 0);
        $updates = $bot->api()->call('getUpdates', [
            'offset'          => $offset,
            'timeout'         => 25,
            'allowed_updates' => ['message', 'callback_query'],
        ], 35);

        foreach ($updates as $update) {
            // сначала сдвигаем offset — «сломанное» обновление не будет обрабатываться по кругу
            $state->set('telegram_offset', (string) ($update['update_id'] + 1));
            try {
                $bot->handleUpdate($update);
            } catch (Throwable $e) {
                Logger::error($e);
            }
        }
    } catch (TelegramException $e) {
        Logger::error('Ошибка опроса Telegram: ' . $e->getMessage());
        sleep(3);
    } catch (PDOException | App\Core\HttpException $e) {
        // MySQL мог закрыть долгое соединение — переподключимся на следующей итерации
        Logger::error('Ошибка БД: ' . $e->getMessage());
        Database::disconnect();
        sleep(3);
    }
}
