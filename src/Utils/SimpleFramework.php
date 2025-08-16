<?php

declare(strict_types=1);

namespace DonFactura\DTE\Utils;

/**
 * Framework simple para manejar HTTP sin dependencias externas
 */
class SimpleFramework
{
    private array $routes = [];
    private array $middleware = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->addRoute('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->addRoute('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): void
    {
        $this->addRoute('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->addRoute('DELETE', $pattern, $handler);
    }

    private function addRoute(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler
        ];
    }

    public function run(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Manejar CORS
        $this->handleCors();

        // Buscar ruta
        $route = $this->matchRoute($method, $uri);

        if (!$route) {
            $this->sendNotFound();
            return;
        }

        try {
            $request = new SimpleRequest();
            $response = new SimpleResponse();

            // Ejecutar handler
            $result = call_user_func($route['handler'], $request, $response, $route['params']);

            if ($result instanceof SimpleResponse) {
                $result->send();
            } else {
                $response->send();
            }

        } catch (\Exception $e) {
            $this->sendError($e->getMessage());
        }
    }

    private function matchRoute(string $method, string $uri): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $route['pattern']);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $uri, $matches)) {
                array_shift($matches); // Remover match completo
                
                return [
                    'handler' => $route['handler'],
                    'params' => $matches
                ];
            }
        }

        return null;
    }

    private function handleCors(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    private function sendNotFound(): void
    {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Endpoint no encontrado']);
    }

    private function sendError(string $message): void
    {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
    }
}

/**
 * Request simple
 */
class SimpleRequest
{
    private array $parsedBody;
    private array $uploadedFiles;

    public function __construct()
    {
        $this->uploadedFiles = $_FILES;
        
        $input = file_get_contents('php://input');
        $this->parsedBody = json_decode($input, true) ?: [];
    }

    public function getParsedBody(): array
    {
        return $this->parsedBody;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploadedFiles;
    }

    public function getQueryParams(): array
    {
        return $_GET;
    }

    public function getMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function getHeaderLine(string $name): string
    {
        $name = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$name] ?? '';
    }
}

/**
 * Response simple
 */
class SimpleResponse
{
    private string $body = '';
    private int $statusCode = 200;
    private array $headers = [];

    public function getBody(): SimpleStream
    {
        return new SimpleStream($this->body);
    }

    public function withHeader(string $name, string $value): self
    {
        $new = clone $this;
        $new->headers[$name] = $value;
        return $new;
    }

    public function withStatus(int $code): self
    {
        $new = clone $this;
        $new->statusCode = $code;
        return $new;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);
        
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo $this->body;
    }
}

/**
 * Stream simple
 */
class SimpleStream
{
    private string $content;

    public function __construct(string $content = '')
    {
        $this->content = $content;
    }

    public function write(string $string): void
    {
        $this->content .= $string;
    }

    public function getContents(): string
    {
        return $this->content;
    }
}

/**
 * Logger simple
 */
class SimpleLogger
{
    private string $logFile;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('ERROR', $message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->log('DEBUG', $message, $context);
    }

    private function log(string $level, string $message, array $context): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        $logLine = "[{$timestamp}] {$level}: {$message}{$contextStr}\n";
        
        file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
    }
}
