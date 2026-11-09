<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** Максимальный размер тела запроса, байт */
    private const MAX_BODY = 20000;

    private ?array $json = null;

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $headers,
        public readonly string $ip,
        private readonly string $rawBody,
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = rawurldecode($uri);
        // сайт может лежать в подпапке: /site/api/products → /api/products
        $pos = strpos($uri, '/api/');
        $path = $pos === false ? $uri : substr($uri, $pos);
        $path = rtrim($path, '/') ?: '/';

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        $raw = (string) file_get_contents('php://input', false, null, 0, self::MAX_BODY + 1);

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $headers,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            $raw,
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function query(string $name, ?string $default = null): ?string
    {
        $value = $this->query[$name] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    /** Тело запроса как JSON-объект */
    public function json(): array
    {
        if ($this->json !== null) {
            return $this->json;
        }
        if (strlen($this->rawBody) > self::MAX_BODY) {
            throw new HttpException(413, 'Слишком большой запрос');
        }
        if (!str_contains(strtolower($this->header('Content-Type') ?? ''), 'application/json')) {
            throw new HttpException(415, 'Ожидается Content-Type: application/json');
        }
        $data = json_decode($this->rawBody, true);
        if (!is_array($data) || array_is_list($data) && $data !== []) {
            throw new HttpException(400, 'Неверный запрос: ожидается JSON-объект');
        }
        return $this->json = $data;
    }
}
