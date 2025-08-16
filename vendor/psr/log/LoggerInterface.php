<?php

namespace Psr\Log;

/**
 * Describe una instancia de logger.
 */
interface LoggerInterface
{
    /**
     * El sistema no se puede usar.
     */
    public function emergency(string|\Stringable $message, array $context = []): void;

    /**
     * Se debe tomar acción inmediatamente.
     */
    public function alert(string|\Stringable $message, array $context = []): void;

    /**
     * Condiciones críticas.
     */
    public function critical(string|\Stringable $message, array $context = []): void;

    /**
     * Errores de tiempo de ejecución que no requieren acción inmediata.
     */
    public function error(string|\Stringable $message, array $context = []): void;

    /**
     * Ocurrencias excepcionales que no son errores.
     */
    public function warning(string|\Stringable $message, array $context = []): void;

    /**
     * Eventos normales pero significativos.
     */
    public function notice(string|\Stringable $message, array $context = []): void;

    /**
     * Eventos interesantes.
     */
    public function info(string|\Stringable $message, array $context = []): void;

    /**
     * Información detallada de depuración.
     */
    public function debug(string|\Stringable $message, array $context = []): void;

    /**
     * Logs con un nivel arbitrario.
     */
    public function log($level, string|\Stringable $message, array $context = []): void;
}
