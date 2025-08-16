<?php

declare(strict_types=1);

namespace DonFactura\DTE\Controllers;

use DonFactura\DTE\Models\FoliosModel;
use DonFactura\DTE\Services\SIIService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use PDO;

/**
 * Controlador para gestión de folios CAF
 */
class FoliosController
{
    private PDO $pdo;
    private LoggerInterface $logger;
    private array $config;
    private FoliosModel $foliosModel;
    private SIIService $siiService;

    public function __construct(PDO $pdo, LoggerInterface $logger, array $config)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->config = $config;
        $this->foliosModel = new FoliosModel($pdo);
        $this->siiService = new SIIService($config);
    }

    public function solicitar(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            
            // Validar datos requeridos
            $validacion = $this->validarDatosSolicitud($data);
            if (!$validacion['valido']) {
                return $this->respuestaError($response, $validacion['errores'], 400);
            }

            // Verificar que el tipo de DTE sea válido
            if (!$this->esTipoDTEValido($data['tipo_dte'])) {
                return $this->respuestaError($response, ['Tipo de DTE no válido'], 400);
            }

            // Solicitar folios al SII
            $resultadoSII = $this->siiService->solicitarFolios(
                $data['tipo_dte'],
                $data['rut_empresa'],
                $data['cantidad']
            );

            if (!$resultadoSII['success']) {
                return $this->respuestaError($response, [$resultadoSII['error']], 500);
            }

            $this->logger->info('Folios solicitados al SII exitosamente', [
                'tipo_dte' => $data['tipo_dte'],
                'rut_empresa' => $data['rut_empresa'],
                'cantidad' => $data['cantidad']
            ]);

            return $this->respuestaExito($response, [
                'tipo_dte' => $data['tipo_dte'],
                'cantidad_solicitada' => $data['cantidad'],
                'estado' => 'solicitado',
                'mensaje' => 'Solicitud de folios enviada al SII. Debe esperar la respuesta y cargar el archivo CAF.',
                'tracking_id' => $resultadoSII['tracking_id'] ?? null
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error al solicitar folios', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function cargarCAF(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            
            // Validar datos requeridos
            if (empty($data['xml_caf'])) {
                return $this->respuestaError($response, ['XML del CAF es requerido'], 400);
            }

            // Parsear XML del CAF
            $cafData = $this->parsearCAF($data['xml_caf']);
            if (!$cafData) {
                return $this->respuestaError($response, ['XML del CAF no válido'], 400);
            }

            // Crear registro de folios
            $folioId = $this->foliosModel->crear([
                'tipo_dte' => $cafData['tipo_dte'],
                'rut_empresa' => $cafData['rut_empresa'],
                'folio_desde' => $cafData['folio_desde'],
                'folio_hasta' => $cafData['folio_hasta'],
                'fecha_resolucion' => $cafData['fecha_resolucion'],
                'fecha_vencimiento' => $cafData['fecha_vencimiento'],
                'xml_caf' => $data['xml_caf'],
            ]);

            $this->logger->info('CAF cargado exitosamente', [
                'folio_id' => $folioId,
                'tipo_dte' => $cafData['tipo_dte'],
                'rango' => "{$cafData['folio_desde']}-{$cafData['folio_hasta']}"
            ]);

            return $this->respuestaExito($response, [
                'id' => $folioId,
                'tipo_dte' => $cafData['tipo_dte'],
                'folio_desde' => $cafData['folio_desde'],
                'folio_hasta' => $cafData['folio_hasta'],
                'folios_disponibles' => $cafData['folio_hasta'] - $cafData['folio_desde'] + 1,
                'fecha_vencimiento' => $cafData['fecha_vencimiento']
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error al cargar CAF', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function disponibles(Request $request, Response $response, array $args): Response
    {
        try {
            $tipo = (int) $args['tipo'];
            $params = $request->getQueryParams();
            $rutEmpresa = $params['rut_empresa'] ?? null;

            if (!$rutEmpresa) {
                return $this->respuestaError($response, ['RUT de empresa es requerido'], 400);
            }

            if (!$this->esTipoDTEValido($tipo)) {
                return $this->respuestaError($response, ['Tipo de DTE no válido'], 400);
            }

            $foliosDisponibles = $this->foliosModel->obtenerDisponibles($tipo, $rutEmpresa);
            
            $total = 0;
            foreach ($foliosDisponibles as $rango) {
                $total += $rango['folios_disponibles'];
            }

            return $this->respuestaExito($response, [
                'tipo_dte' => $tipo,
                'rut_empresa' => $rutEmpresa,
                'total_folios_disponibles' => $total,
                'rangos' => $foliosDisponibles
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error al consultar folios disponibles', [
                'tipo' => $args['tipo'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function siguienteFolio(Request $request, Response $response, array $args): Response
    {
        try {
            $tipo = (int) $args['tipo'];
            $params = $request->getQueryParams();
            $rutEmpresa = $params['rut_empresa'] ?? null;

            if (!$rutEmpresa) {
                return $this->respuestaError($response, ['RUT de empresa es requerido'], 400);
            }

            if (!$this->esTipoDTEValido($tipo)) {
                return $this->respuestaError($response, ['Tipo de DTE no válido'], 400);
            }

            $siguienteFolio = $this->foliosModel->obtenerSiguienteFolio($tipo, $rutEmpresa);
            
            if (!$siguienteFolio) {
                return $this->respuestaError($response, ['No hay folios disponibles'], 404);
            }

            return $this->respuestaExito($response, [
                'tipo_dte' => $tipo,
                'rut_empresa' => $rutEmpresa,
                'siguiente_folio' => $siguienteFolio
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error al obtener siguiente folio', [
                'tipo' => $args['tipo'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    private function validarDatosSolicitud(array $data): array
    {
        $errores = [];

        $requeridos = ['tipo_dte', 'rut_empresa', 'cantidad'];
        foreach ($requeridos as $campo) {
            if (!isset($data[$campo]) || empty($data[$campo])) {
                $errores[] = "Campo requerido: {$campo}";
            }
        }

        if (isset($data['cantidad']) && ($data['cantidad'] < 1 || $data['cantidad'] > 1000)) {
            $errores[] = "La cantidad debe estar entre 1 y 1000";
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores
        ];
    }

    private function parsearCAF(string $xmlCaf): ?array
    {
        try {
            $xml = simplexml_load_string($xmlCaf);
            if (!$xml) {
                return null;
            }

            // Extraer datos del CAF
            $autorizacion = $xml->CAF->DA ?? null;
            if (!$autorizacion) {
                return null;
            }

            return [
                'tipo_dte' => (int) $autorizacion->TD,
                'rut_empresa' => (string) $autorizacion->RE,
                'folio_desde' => (int) $autorizacion->RNG->D,
                'folio_hasta' => (int) $autorizacion->RNG->H,
                'fecha_resolucion' => (string) $autorizacion->FA,
                'fecha_vencimiento' => (string) $autorizacion->RSAPK->EXPT ?? date('Y-m-d', strtotime('+2 years')),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Error al parsear CAF', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function esTipoDTEValido(int $tipo): bool
    {
        return array_key_exists($tipo, $this->config['dte_types']);
    }

    private function respuestaExito(Response $response, array $data): Response
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $data
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function respuestaError(Response $response, array $errores, int $codigo = 400): Response
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'errors' => $errores
        ]));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($codigo);
    }
}
