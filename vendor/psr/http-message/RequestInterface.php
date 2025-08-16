<?php

namespace Psr\Http\Message;

/**
 * Representación de un mensaje de solicitud HTTP saliente del lado del cliente.
 */
interface RequestInterface extends MessageInterface
{
    /**
     * Recupera el target de la solicitud del mensaje.
     */
    public function getRequestTarget(): string;

    /**
     * Retorna una instancia con el target de solicitud específico.
     */
    public function withRequestTarget(string $requestTarget): RequestInterface;

    /**
     * Recupera el método HTTP de la solicitud.
     */
    public function getMethod(): string;

    /**
     * Retorna una instancia con el método HTTP proporcionado.
     */
    public function withMethod(string $method): RequestInterface;

    /**
     * Recupera la instancia URI.
     */
    public function getUri();

    /**
     * Retorna una instancia con la URI especificada.
     */
    public function withUri($uri, bool $preserveHost = false): RequestInterface;
}
