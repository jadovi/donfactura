<?php

declare(strict_types=1);

namespace DonFactura\DTE\Core;

/**
 * Clase principal de la aplicación
 */
class Application
{
    private Database $database;
    private array $config;

    public function __construct(Database $database, array $config)
    {
        $this->database = $database;
        $this->config = $config;
    }

    public function getDatabase(): Database
    {
        return $this->database;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getSIIEnvironment(): string
    {
        return $this->config['sii']['environment'] ?? 'certification';
    }

    public function getSIIUrl(string $service): string
    {
        $env = $this->getSIIEnvironment();
        $key = $env === 'production' ? "prod_url_{$service}" : "cert_url_{$service}";
        
        return $this->config['sii'][$key] ?? '';
    }

    public function getPath(string $type): string
    {
        return $this->config['paths'][$type] ?? '';
    }

    public function getDTETypes(): array
    {
        return $this->config['dte_types'] ?? [];
    }

    public function isDTETypeValid(int $type): bool
    {
        return array_key_exists($type, $this->getDTETypes());
    }
}
