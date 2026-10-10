<?php
declare(strict_types=1);

final class Router
{
    private array $routes = [];

    public function get(string $pattern, callable $h): self { return $this->add(['GET', 'HEAD'], $pattern, $h); }
    public function post(string $pattern, callable $h): self { return $this->add(['POST'], $pattern, $h); }
    public function any(string $pattern, callable $h): self { return $this->add(['GET', 'HEAD', 'POST'], $pattern, $h); }

    public function add(array $methods, string $pattern, callable $h): self
    {
        $re = preg_replace_callback('#\{(\w+)(?::([^}]+))?\}#', fn($m) => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')', $pattern);
        $this->routes[] = [$methods, '#^' . $re . '$#u', $h];
        return $this;
    }

    /** @return bool true if a route matched */
    public function dispatch(string $method, string $path): bool
    {
        $allowed = false;
        foreach ($this->routes as [$methods, $re, $h]) {
            if (preg_match($re, $path, $m)) {
                if (!in_array($method, $methods, true)) { $allowed = true; continue; }
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                if ($method === 'POST') csrf_check();
                $h(...array_values($params));
                return true;
            }
        }
        if ($allowed) {
            http_response_code(405);
            echo 'Method not allowed';
            return true;
        }
        return false;
    }
}
