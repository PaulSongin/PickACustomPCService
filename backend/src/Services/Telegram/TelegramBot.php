<?php
declare(strict_types=1);

namespace App\Services\Telegram;

use App\Core\Config;
use App\Core\Logger;
use App\Models\OrderModel;
use App\Models\ReviewModel;
use App\Models\TelegramStateModel;
use App\Services\OrderStatus;
use App\Support\Format;

/**
 * Telegram-бот PCBUILD: один бот для клиентов и сборщиков.
 *
 * Сборщик (ID из настроек telegram.builder_ids):
 *   получает карточки новых заказов и отзывов с кнопками, меняет статусы, отвечает клиентам реплаем.
 * Клиент (все остальные):
 *   привязывает заказ по ссылке с сайта (/start <token>), получает уведомления о статусах, пишет в поддержку.
 *
 * Обновления приходят либо через webhook (POST /api/telegram/webhook),
 * либо через опрос (php backend/bin/telegram-poll.php) — обработка одинаковая.
 */
final class TelegramBot
{
    private const MENU = [
        'keyboard'        => [[['text' => '📦 Мои заказы'], ['text' => '💬 Поддержка']]],
        'resize_keyboard' => true,
    ];

    private const REVIEW_STATUS = ['pending' => '⏳ На модерации', 'approved' => '✅ Опубликован', 'rejected' => '🗑 Отклонён'];

    /** @param list<int> $builders */
    public function __construct(
        private readonly TelegramApi $api,
        private readonly array $builders,
        private readonly string $configuredUsername = '',
        private readonly OrderModel $orders = new OrderModel(),
        private readonly ReviewModel $reviews = new ReviewModel(),
        private readonly TelegramStateModel $state = new TelegramStateModel(),
    ) {
    }

    /** Бот из настроек или null, если токен/сборщики не заданы (тогда заказы просто сохраняются в БД) */
    public static function fromConfig(): ?self
    {
        $token = trim((string) Config::get('telegram.token', ''));
        $builders = array_values(array_filter(array_map('intval', (array) Config::get('telegram.builder_ids', []))));
        if ($token === '' || !$builders) {
            return null;
        }
        $api = new TelegramApi(
            $token,
            (string) Config::get('telegram.api_url', 'https://api.telegram.org'),
            ((string) Config::get('telegram.ca_bundle', '')) ?: null
        );
        return new self($api, $builders, ltrim(trim((string) Config::get('telegram.bot_username', '')), '@'));
    }

    public function api(): TelegramApi
    {
        return $this->api;
    }

    public function builders(): array
    {
        return $this->builders;
    }

    /** Логин бота для ссылки t.me/<логин>: из настроек, из кэша в БД или через getMe */
    public function username(): ?string
    {
        if ($this->configuredUsername !== '') {
            return $this->configuredUsername;
        }
        $key = 'bot_username:' . substr(sha1((string) Config::get('telegram.token')), 0, 12);
        $cached = $this->state->get($key);
        if ($cached !== null) {
            return $cached;
        }
        try {
            $me = $this->api->call('getMe', [], 5);
            $this->state->set($key, (string) $me['username']);
            return (string) $me['username'];
        } catch (TelegramException $e) {
            Logger::error('Не удалось узнать имя бота: ' . $e->getMessage());
            return null;
        }
    }

    // =====================================================================
    //  Уведомления, которые отправляет сайт
    // =====================================================================

    public function announceOrder(int $orderId): void
    {
        $order = $this->orders->find($orderId);
        if ($order === null) {
            return;
        }
        foreach ($this->builders as $builder) {
            try {
                $this->showOrder($builder, $order);
            } catch (TelegramException $e) {
                Logger::error("Не удалось отправить заказ №$orderId сборщику $builder (он нажимал /start у бота?): " . $e->getMessage());
            }
        }
    }

    public function announceReview(int $reviewId): void
    {
        $review = $this->reviews->find($reviewId);
        if ($review === null) {
            return;
        }
        foreach ($this->builders as $builder) {
            try {
                $this->showReview($builder, $review);
            } catch (TelegramException $e) {
                Logger::error("Не удалось отправить отзыв №$reviewId сборщику $builder: " . $e->getMessage());
            }
        }
    }

    // =====================================================================
    //  Обработка входящих обновлений
    // =====================================================================

    public function handleUpdate(array $update): void
    {
        if (isset($update['message'])) {
            $this->onMessage($update['message']);
        } elseif (isset($update['callback_query'])) {
            $this->onCallback($update['callback_query']);
        }
    }

    private function onMessage(array $m): void
    {
        if (($m['chat']['type'] ?? '') !== 'private' || !isset($m['from']['id'])) {
            return;
        }
        $text = trim((string) ($m['text'] ?? ''));
        $isBuilder = $this->isBuilder((int) $m['from']['id']);

        if (preg_match('~^/start(?:@\w+)?(?:\s+(\S+))?~u', $text, $mm)) {
            $this->cmdStart($m, $mm[1] ?? null, $isBuilder);
            return;
        }
        $isBuilder ? $this->builderMessage($m, $text) : $this->clientMessage($m, $text);
    }

    private function cmdStart(array $m, ?string $token, bool $isBuilder): void
    {
        $chat = (int) $m['chat']['id'];
        if ($isBuilder) {
            $this->send($chat, "👷 Вы вошли как <b>сборщик</b>.\nНовые заказы и отзывы на модерацию приходят сюда с кнопками.\n\n"
                . "/orders — активные заказы\n/order 12 — открыть заказ №12\n/reviews — отзывы на модерации\n"
                . 'Чтобы ответить клиенту, ответьте <b>реплаем</b> на его сообщение.');
            return;
        }
        if ($token !== null) {
            $order = preg_match('/^[a-f0-9]{24}$/', $token) ? $this->orders->findByToken($token) : null;
            if ($order === null) {
                $this->send($chat, 'Заказ по этой ссылке не найден.', ['reply_markup' => self::MENU]);
                return;
            }
            if (!$this->orders->bindClientChat($order['id'], $chat)) {
                $this->send($chat, 'Этот заказ уже привязан к другому аккаунту.', ['reply_markup' => self::MENU]);
                return;
            }
            $this->send($chat, "✅ Заказ №{$order['id']} привязан. Статус: " . OrderStatus::label($order['status'])
                . "\nМы сообщим здесь о каждом изменении. Есть вопросы — просто напишите сообщение.", ['reply_markup' => self::MENU]);
            return;
        }
        $this->send($chat, "Здравствуйте! Это бот PCBUILD.\n📦 «Мои заказы» — статусы ваших заказов\n💬 Любое сообщение уйдёт в поддержку.",
            ['reply_markup' => self::MENU]);
    }

    private function clientMessage(array $m, string $text): void
    {
        $chat = (int) $m['chat']['id'];

        if ($text === '/orders' || $text === '📦 Мои заказы') {
            $mine = $this->orders->byClientChat($chat);
            $this->send($chat, $mine
                ? implode("\n", array_map(static fn (array $o): string => "№{$o['id']} — " . OrderStatus::label($o['status'])
                    . ' — ' . Format::moneyText($o['total']), $mine))
                : 'Привязанных заказов пока нет. Оформите заказ на сайте и перейдите по ссылке после оформления.');
            return;
        }
        if ($text === '💬 Поддержка') {
            $this->send($chat, 'Напишите сообщение — оно уйдёт сборщикам, ответ придёт сюда.');
            return;
        }
        if ($text === '') {
            $this->send($chat, 'Пока поддерживаются только текстовые сообщения.');
            return;
        }

        $active = $this->orders->byClientChat($chat, true);
        $label = $active
            ? 'заказ ' . implode(', ', array_map(static fn (array $o): string => '№' . $o['id'], $active))
            : 'без активного заказа';
        $sent = 0;
        foreach ($this->builders as $builder) {
            try {
                $r = $this->api->sendMessage($builder, '💬 <b>' . Format::html($this->nameOf($m['from'])) . "</b> ($label)\n\n" . Format::html($text));
                $this->state->mapSupport($builder, (int) $r['message_id'], $chat);
                $sent++;
            } catch (TelegramException $e) {
                Logger::error($e->getMessage());
            }
        }
        $this->send($chat, $sent ? '✉️ Сообщение передано сборщикам.' : 'Поддержка сейчас недоступна, попробуйте позже.');
    }

    private function builderMessage(array $m, string $text): void
    {
        $chat = (int) $m['chat']['id'];
        $uid = (int) $m['from']['id'];

        if ($text === '/cancel') {
            $this->state->clearPending($uid);
            $this->send($chat, 'Отменено.');
            return;
        }
        if ($text === '/orders') {
            $active = $this->orders->active(10);
            if (!$active) {
                $this->send($chat, 'Активных заказов нет.');
            }
            foreach ($active as $order) {
                $this->showOrder($chat, $order);
            }
            return;
        }
        if (preg_match('~^/order\s+(\d+)~', $text, $mm)) {
            $order = $this->orders->find((int) $mm[1]);
            $order ? $this->showOrder($chat, $order) : $this->send($chat, 'Заказ не найден.');
            return;
        }
        if ($text === '/reviews') {
            $pending = $this->reviews->pending(10);
            if (!$pending) {
                $this->send($chat, 'Отзывов на модерации нет.');
            }
            foreach ($pending as $review) {
                $this->showReview($chat, $review);
            }
            return;
        }

        // ждём от сборщика причину отказа
        $pending = $this->state->pending($uid);
        if ($pending !== null && $text !== '' && $text[0] !== '/') {
            $this->state->clearPending($uid);
            if ($pending['action'] === 'reject_order') {
                $this->rejectOrder($chat, $m['from'], $pending['entity_id'], $text);
                return;
            }
        }

        // ответ клиенту реплаем на его сообщение
        if (isset($m['reply_to_message']['message_id']) && $text !== '') {
            $target = $this->state->supportTarget($chat, (int) $m['reply_to_message']['message_id']);
            if ($target !== null) {
                try {
                    $this->api->sendMessage($target, "👷 <b>Сборщик:</b>\n" . Format::html($text));
                    $this->send($chat, '✉️ Отправлено клиенту.');
                } catch (TelegramException $e) {
                    $this->send($chat, 'Не удалось доставить сообщение клиенту: ' . Format::html($e->getMessage()));
                }
                return;
            }
        }
        $this->send($chat, "Чтобы ответить клиенту, ответьте реплаем (Reply) на его сообщение.\nКоманды: /orders, /order 12, /reviews");
    }

    private function rejectOrder(int $chat, array $from, int $orderId, string $reason): void
    {
        $reason = mb_substr($reason, 0, 500);
        $name = $this->nameOf($from);
        $ok = $this->orders->transition($orderId, 'new', 'rejected', [
            'reject_reason' => $reason,
            'builder_tg_id' => (int) $from['id'],
            'builder_name'  => $name,
        ], [], $name, $reason);
        if (!$ok) {
            $this->send($chat, 'Этот заказ уже обработан.');
            return;
        }
        $order = $this->orders->find($orderId);
        $this->notifyClient($order, "❌ Заказ №$orderId отклонён.\nПричина: " . Format::html($reason) . "\nЕсть вопросы — напишите сюда.");
        $this->refreshOrder($order);
        $this->send($chat, $order['client_chat_id']
            ? "Заказ №$orderId отклонён, клиент уведомлён."
            : "Заказ №$orderId отклонён. Клиент не подключил Telegram — сообщите ему по телефону " . Format::html($order['phone']) . '.');
    }

    private function onCallback(array $q): void
    {
        $uid = (int) ($q['from']['id'] ?? 0);
        $answer = function (string $text = '', bool $alert = false) use ($q): void {
            $this->safe(fn () => $this->api->call('answerCallbackQuery', [
                'callback_query_id' => (string) $q['id'], 'text' => $text, 'show_alert' => $alert,
            ]));
        };
        if (!$this->isBuilder($uid)) {
            $answer('Нет доступа', true);
            return;
        }

        [$action, $id] = array_pad(explode(':', (string) ($q['data'] ?? ''), 2), 2, '0');
        $id = (int) $id;
        $name = $this->nameOf($q['from']);

        if ($action === 'rv_ok' || $action === 'rv_no') {
            $status = $action === 'rv_ok' ? 'approved' : 'rejected';
            if (!$this->reviews->moderate($id, $status, $name)) {
                $answer('Отзыв уже обработан', true);
                return;
            }
            $this->refreshReview($id);
            $answer($status === 'approved' ? 'Отзыв опубликован' : 'Отзыв отклонён');
            return;
        }

        $order = $this->orders->find($id);
        if ($order === null) {
            $answer('Заказ не найден', true);
            return;
        }

        switch ($action) {
            case 'ok': // принять в сборку — заказ достаётся тому, кто нажал первым
                $ok = $this->orders->transition($id, 'new', 'building', [
                    'builder_tg_id' => $uid, 'builder_name' => $name,
                ], [], $name);
                if (!$ok) {
                    $answer('Заказ уже обработан', true);
                    return;
                }
                $order = $this->orders->find($id);
                $this->notifyClient($order, sprintf(OrderStatus::CLIENT_TEXT['building'], $id));
                $this->refreshOrder($order);
                $answer('Заказ принят в сборку');
                return;

            case 'no': // отклонить — сначала спросим причину
                if ($order['status'] !== 'new') {
                    $answer('Заказ уже обработан', true);
                    return;
                }
                $this->state->setPending($uid, 'reject_order', $id);
                $answer();
                $this->send($uid, "Напишите причину отказа по заказу №$id одним сообщением (или /cancel).");
                return;

            case 'next': // следующий статус — только у своего заказа
                if ($order['builder_tg_id'] !== $uid) {
                    $answer('Заказ ведёт другой сборщик', true);
                    return;
                }
                $next = OrderStatus::NEXT[$order['status']] ?? null;
                if ($next === null) {
                    $answer('Дальше менять нечего', true);
                    return;
                }
                if (!$this->orders->transition($id, $order['status'], $next[0], [], ['builder_tg_id' => $uid], $name)) {
                    $answer('Статус уже изменён', true);
                    return;
                }
                $order = $this->orders->find($id);
                $this->notifyClient($order, sprintf(OrderStatus::CLIENT_TEXT[$next[0]], $id));
                $this->refreshOrder($order);
                $answer('Статус обновлён');
                return;
        }
        $answer('Неизвестное действие', true);
    }

    // =====================================================================
    //  Карточки
    // =====================================================================

    private function orderView(array $o, int $chat): array
    {
        $lines = array_map(
            static fn (array $i): string => Format::html($i['category_name'] . ': ' . $i['product_name'] . ' — ' . Format::moneyText($i['price'])),
            $o['items']
        );
        $text = "<b>Заказ №{$o['id']}</b> · " . OrderStatus::label($o['status'])
            . "\n👤 " . Format::html($o['customer_name']) . ' · ' . Format::html($o['phone'])
            . "\n🕒 " . date('d.m.Y H:i', strtotime($o['created_at']))
            . "\n\n" . implode("\n", $lines)
            . "\n\n<b>Итого: " . Format::moneyText($o['total']) . '</b>';
        if ($o['reject_reason']) {
            $text .= "\n\n❗ Причина отказа: " . Format::html($o['reject_reason']);
        }

        $mine = $o['builder_tg_id'] === $chat;
        if ($o['builder_tg_id'] !== null && !$mine) {
            $text .= "\n\n👷 Ведёт: " . Format::html((string) $o['builder_name']);
        }

        $keyboard = [];
        if ($o['status'] === 'new') {
            $keyboard = [[
                ['text' => '✅ Принять в сборку', 'callback_data' => 'ok:' . $o['id']],
                ['text' => '❌ Отклонить', 'callback_data' => 'no:' . $o['id']],
            ]];
        } elseif ($mine && isset(OrderStatus::NEXT[$o['status']])) {
            $keyboard = [[['text' => OrderStatus::NEXT[$o['status']][1], 'callback_data' => 'next:' . $o['id']]]];
        }
        return ['text' => $text, 'reply_markup' => ['inline_keyboard' => $keyboard]];
    }

    private function showOrder(int $chat, array $order): void
    {
        $view = $this->orderView($order, $chat);
        $r = $this->api->sendMessage($chat, $view['text'], ['reply_markup' => $view['reply_markup']]);
        $this->state->addMessage('order', $order['id'], $chat, (int) $r['message_id']);
    }

    /** Обновить карточку заказа у всех сборщиков */
    private function refreshOrder(array $order): void
    {
        foreach ($this->state->messages('order', $order['id']) as $msg) {
            $view = $this->orderView($order, $msg['chat_id']);
            $this->safe(fn () => $this->api->editMessageText($msg['chat_id'], $msg['message_id'], $view['text'], [
                'reply_markup' => $view['reply_markup'],
            ]));
        }
    }

    private function reviewView(array $r): array
    {
        $text = "📝 <b>Отзыв №{$r['id']}</b> · " . (self::REVIEW_STATUS[$r['status']] ?? $r['status'])
            . "\n👤 " . Format::html($r['author_name']) . ' · ' . str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']);
        if ($r['product_name']) {
            $text .= "\n🧩 " . Format::html($r['product_name']);
        }
        $text .= "\n\n" . Format::html($r['text']);
        if ($r['status'] !== 'pending' && $r['moderated_by']) {
            $text .= "\n\n👷 " . Format::html($r['moderated_by']);
        }
        $keyboard = $r['status'] === 'pending' ? [[
            ['text' => '✅ Опубликовать', 'callback_data' => 'rv_ok:' . $r['id']],
            ['text' => '🗑 Отклонить', 'callback_data' => 'rv_no:' . $r['id']],
        ]] : [];
        return ['text' => $text, 'reply_markup' => ['inline_keyboard' => $keyboard]];
    }

    private function showReview(int $chat, array $review): void
    {
        $view = $this->reviewView($review);
        $r = $this->api->sendMessage($chat, $view['text'], ['reply_markup' => $view['reply_markup']]);
        $this->state->addMessage('review', $review['id'], $chat, (int) $r['message_id']);
    }

    private function refreshReview(int $id): void
    {
        $review = $this->reviews->find($id);
        if ($review === null) {
            return;
        }
        $view = $this->reviewView($review);
        foreach ($this->state->messages('review', $id) as $msg) {
            $this->safe(fn () => $this->api->editMessageText($msg['chat_id'], $msg['message_id'], $view['text'], [
                'reply_markup' => $view['reply_markup'],
            ]));
        }
    }

    // =====================================================================
    //  Вспомогательное
    // =====================================================================

    private function isBuilder(int $userId): bool
    {
        return in_array($userId, $this->builders, true);
    }

    private function send(int $chat, string $text, array $extra = []): void
    {
        $this->safe(fn () => $this->api->sendMessage($chat, $text, $extra));
    }

    private function notifyClient(?array $order, string $text): void
    {
        if ($order !== null && $order['client_chat_id'] !== null) {
            $this->send($order['client_chat_id'], $text);
        }
    }

    /** Ошибки Telegram логируем, но не роняем обработку («message is not modified» — не ошибка) */
    private function safe(callable $fn): void
    {
        try {
            $fn();
        } catch (TelegramException $e) {
            if (!str_contains($e->getMessage(), 'message is not modified')) {
                Logger::error($e->getMessage());
            }
        }
    }

    private function nameOf(array $user): string
    {
        $name = trim((string) ($user['first_name'] ?? '') . ' ' . (string) ($user['last_name'] ?? ''));
        if (!empty($user['username'])) {
            $name .= ($name !== '' ? ' ' : '') . '@' . $user['username'];
        }
        return $name !== '' ? $name : 'id' . ($user['id'] ?? '?');
    }
}
