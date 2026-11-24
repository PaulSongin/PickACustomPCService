<?php
declare(strict_types=1);

namespace App\Services\Telegram;

/** Минимальный клиент Telegram Bot API на cURL */
final class TelegramApi
{
    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl = 'https://api.telegram.org',
        private readonly ?string $caBundle = null,
    ) {
    }

    /**
     * @param int $timeout секунд на весь запрос (для getUpdates — больше, чем его timeout)
     * @throws TelegramException
     */
    public function call(string $method, array $params = [], int $timeout = 10): mixed
    {
        $ch = curl_init(rtrim($this->baseUrl, '/') . '/bot' . $this->token . '/' . $method);
        $options = [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($params ?: new \stdClass(), JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => $timeout,
        ];
        if ($this->caBundle) {
            $options[CURLOPT_CAINFO] = $this->caBundle;
        }
        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);

        if ($raw === false) {
            throw new TelegramException($method . ': ' . curl_error($ch));
        }
        $json = json_decode((string) $raw, true);
        if (!is_array($json) || empty($json['ok'])) {
            $code = is_array($json) ? (int) ($json['error_code'] ?? 0) : 0;
            throw new TelegramException($method . ': ' . ($json['description'] ?? 'неверный ответ Telegram'), $code);
        }
        return $json['result'];
    }

    public function sendMessage(int $chatId, string $text, array $extra = []): array
    {
        return $this->call('sendMessage', $extra + [
            'chat_id'              => $chatId,
            'text'                 => $text,
            'parse_mode'           => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
        ]);
    }

    public function editMessageText(int $chatId, int $messageId, string $text, array $extra = []): void
    {
        $this->call('editMessageText', $extra + [
            'chat_id'              => $chatId,
            'message_id'           => $messageId,
            'text'                 => $text,
            'parse_mode'           => 'HTML',
            'link_preview_options' => ['is_disabled' => true],
        ]);
    }
}
