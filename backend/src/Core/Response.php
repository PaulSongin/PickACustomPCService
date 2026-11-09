<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        public readonly int $status,
        public readonly array $data,
        public readonly array $headers = [],
    ) {
    }

    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        return new self($status, $data, $headers);
    }

    public function send(): void
    {
        $body = json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            $body = '{"error":"Ошибка кодирования ответа"}';
        }
        http_response_code($this->status);
        $headers = $this->headers + [
            'Content-Type'           => 'application/json; charset=utf-8',
            'Cache-Control'          => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options'        => 'DENY',
            'Referrer-Policy'        => 'same-origin',
            'Content-Length'         => (string) strlen($body),
        ];
        foreach ($headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $body;
    }
}
