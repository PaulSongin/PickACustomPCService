<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Самописный роутер: шаблоны вида /api/products/{id}.
 * Обработчик — [Класс контроллера, 'метод'], параметры из пути передаются аргументами.
 */
final class Router
{
    /** @var list<array{method:string, regex:string, handler:array, csrf:bool}> */
    private array $routes = [];

    public function get(string $pattern, array $handler): void
    {
        $this->add('GET', $pattern, $handler, false);
    }

    /** POST-запросы по умолчанию требуют CSRF-токен */
    public function post(string $pattern, array $handler, bool $csrf = true): void
    {
        $this->add('POST', $pattern, $handler, $csrf);
    }

    private function add(string $method, string $pattern, array $handler, bool $csrf): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[A-Za-z0-9_-]+)', $pattern) . '$#';
        $this->routes[] = ['method' => $method, 'regex' => $regex, 'handler' => $handler, 'csrf' => $csrf];
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path, $m)) {
                continue;
            }
            if ($route['method'] !== $request->method) {
                $allowed[] = $route['method'];
                continue;
            }
            if ($route['csrf']) {
                Csrf::verify($request);
            }
            $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
            [$class, $method] = $route['handler'];
            return (new $class())->$method($request, ...array_values($params));
        }

        if ($allowed) {
            throw new HttpException(405, 'Метод не поддерживается', ['allowed' => array_values(array_unique($allowed))]);
        }
        throw new HttpException(404, 'Адрес API не найден');
    }
}
