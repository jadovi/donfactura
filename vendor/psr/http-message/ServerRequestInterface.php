<?php

namespace Psr\Http\Message;

/**
 * Representación de un mensaje de solicitud HTTP entrante del lado del servidor.
 */
interface ServerRequestInterface extends RequestInterface
{
    /**
     * Recupera los parámetros del servidor.
     */
    public function getServerParams(): array;

    /**
     * Recupera los parámetros de cookie.
     */
    public function getCookieParams(): array;

    /**
     * Retorna una instancia con los parámetros de cookie especificados.
     */
    public function withCookieParams(array $cookies): ServerRequestInterface;

    /**
     * Recupera los parámetros de consulta normalizados.
     */
    public function getQueryParams(): array;

    /**
     * Retorna una instancia con los parámetros de consulta especificados.
     */
    public function withQueryParams(array $query): ServerRequestInterface;

    /**
     * Recupera los archivos subidos normalizados.
     */
    public function getUploadedFiles(): array;

    /**
     * Crea una nueva instancia con los archivos subidos especificados.
     */
    public function withUploadedFiles(array $uploadedFiles): ServerRequestInterface;

    /**
     * Recupera cualquier parámetro proporcionado en el cuerpo de la solicitud.
     */
    public function getParsedBody();

    /**
     * Retorna una instancia con el cuerpo especificado parseado.
     */
    public function withParsedBody($data): ServerRequestInterface;

    /**
     * Recupera atributos derivados de la solicitud.
     */
    public function getAttributes(): array;

    /**
     * Recupera un único atributo derivado.
     */
    public function getAttribute(string $name, $default = null);

    /**
     * Retorna una instancia con el atributo especificado.
     */
    public function withAttribute(string $name, $value): ServerRequestInterface;

    /**
     * Retorna una instancia que remueve el atributo especificado.
     */
    public function withoutAttribute(string $name): ServerRequestInterface;
}

/**
 * Representación de un mensaje de solicitud HTTP saliente del lado del cliente.
 */
interface RequestInterface extends MessageInterface
{
    public function getRequestTarget(): string;
    public function withRequestTarget(string $requestTarget): RequestInterface;
    public function getMethod(): string;
    public function withMethod(string $method): RequestInterface;
    public function getUri();
    public function withUri($uri, bool $preserveHost = false): RequestInterface;
}
