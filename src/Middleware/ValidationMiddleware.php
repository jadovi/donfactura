<?php

declare(strict_types=1);

namespace DonFactura\DTE\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * Middleware para validación de requests
 */
class ValidationMiddleware implements MiddlewareInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Log de la request
        $this->logger->info('Request received', [
            'method' => $request->getMethod(),
            'uri' => (string) $request->getUri(),
            'headers' => $request->getHeaders(),
        ]);

        // Validar Content-Type para requests POST/PUT
        if (in_array($request->getMethod(), ['POST', 'PUT'])) {
            $contentType = $request->getHeaderLine('Content-Type');
            
            if (!str_contains($contentType, 'application/json') && 
                !str_contains($contentType, 'multipart/form-data')) {
                
                $response = new \Slim\Psr7\Response();
                $response->getBody()->write(json_encode([
                    'error' => 'Content-Type debe ser application/json o multipart/form-data'
                ]));
                
                return $response
                    ->withStatus(400)
                    ->withHeader('Content-Type', 'application/json');
            }
        }

        return $handler->handle($request);
    }
}
