<?php

namespace Psr\Http\Message;

/**
 * Representación de un mensaje de respuesta HTTP saliente.
 */
interface ResponseInterface extends MessageInterface
{
    /**
     * Obtiene el código de estado de la respuesta.
     */
    public function getStatusCode(): int;

    /**
     * Retorna una instancia con el código de estado especificado y, opcionalmente, la frase de razón.
     */
    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface;

    /**
     * Obtiene la frase de razón de la respuesta asociada con el código de estado.
     */
    public function getReasonPhrase(): string;
}

/**
 * Interfaz base para mensajes HTTP
 */
interface MessageInterface
{
    public function getProtocolVersion(): string;
    public function withProtocolVersion(string $version): MessageInterface;
    public function getHeaders(): array;
    public function hasHeader(string $name): bool;
    public function getHeader(string $name): array;
    public function getHeaderLine(string $name): string;
    public function withHeader(string $name, $value): MessageInterface;
    public function withAddedHeader(string $name, $value): MessageInterface;
    public function withoutHeader(string $name): MessageInterface;
    public function getBody();
    public function withBody($body): MessageInterface;
}
