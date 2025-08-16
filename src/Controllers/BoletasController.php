<?php

declare(strict_types=1);

namespace DonFactura\DTE\Controllers;

use DonFactura\DTE\Models\DTEModel;
use DonFactura\DTE\Models\FoliosModel;
use DonFactura\DTE\Services\BoletasService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use PDO;

/**
 * Controlador específico para boletas electrónicas (DTE tipo 39)
 */
class BoletasController
{
    private PDO $pdo;
    private LoggerInterface $logger;
    private array $config;
    private DTEModel $dteModel;
    private FoliosModel $foliosModel;
    private BoletasService $boletasService;

    public function __construct(PDO $pdo, LoggerInterface $logger, array $config)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->config = $config;
        $this->dteModel = new DTEModel($pdo);
        $this->foliosModel = new FoliosModel($pdo);
        $this->boletasService = new BoletasService($pdo, $config);
    }

    public function generar(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            
            // Forzar tipo DTE a 39 (Boleta Electrónica)
            $data['tipo_dte'] = 39;
            
            // Validar datos específicos de boletas
            $validacion = $this->validarDatosBoleta($data);
            if (!$validacion['valido']) {
                return $this->respuestaError($response, $validacion['errores'], 400);
            }

            // Obtener siguiente folio disponible para boletas
            $folio = $this->foliosModel->obtenerSiguienteFolio(39, $data['emisor']['rut']);

            if (!$folio) {
                return $this->respuestaError($response, ['No hay folios disponibles para boletas electrónicas'], 400);
            }

            // Generar boleta usando el servicio especializado
            $resultado = $this->boletasService->generar($data, $folio);

            if (!$resultado['success']) {
                return $this->respuestaError($response, [$resultado['error']], 500);
            }

            $this->logger->info('Boleta electrónica generada exitosamente', [
                'boleta_id' => $resultado['data']['id'],
                'folio' => $folio,
                'monto' => $resultado['data']['monto_total']
            ]);

            return $this->respuestaExito($response, $resultado['data']);

        } catch (\Exception $e) {
            $this->logger->error('Error al generar boleta electrónica', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function obtener(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int) $args['id'];
            $boleta = $this->boletasService->obtenerPorId($id);

            if (!$boleta) {
                return $this->respuestaError($response, ['Boleta no encontrada'], 404);
            }

            return $this->respuestaExito($response, $boleta);

        } catch (\Exception $e) {
            $this->logger->error('Error al obtener boleta', [
                'id' => $args['id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function envioMasivo(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            
            // Validar que se proporcionen boletas
            if (empty($data['boletas']) || !is_array($data['boletas'])) {
                return $this->respuestaError($response, ['Debe proporcionar un array de boletas'], 400);
            }

            $resultados = [];
            $errores = [];

            foreach ($data['boletas'] as $index => $boletaData) {
                try {
                    $boletaData['tipo_dte'] = 39;
                    
                    // Validar cada boleta
                    $validacion = $this->validarDatosBoleta($boletaData);
                    if (!$validacion['valido']) {
                        $errores[] = "Boleta {$index}: " . implode(', ', $validacion['errores']);
                        continue;
                    }

                    // Obtener folio
                    $folio = $this->foliosModel->obtenerSiguienteFolio(39, $boletaData['emisor']['rut']);
                    if (!$folio) {
                        $errores[] = "Boleta {$index}: No hay folios disponibles";
                        continue;
                    }

                    // Generar boleta
                    $resultado = $this->boletasService->generar($boletaData, $folio);
                    if ($resultado['success']) {
                        $resultados[] = $resultado['data'];
                    } else {
                        $errores[] = "Boleta {$index}: " . $resultado['error'];
                    }

                } catch (\Exception $e) {
                    $errores[] = "Boleta {$index}: " . $e->getMessage();
                }
            }

            $this->logger->info('Envío masivo de boletas procesado', [
                'total_enviadas' => count($data['boletas']),
                'exitosas' => count($resultados),
                'con_errores' => count($errores)
            ]);

            return $this->respuestaExito($response, [
                'total_procesadas' => count($data['boletas']),
                'exitosas' => count($resultados),
                'con_errores' => count($errores),
                'boletas_generadas' => $resultados,
                'errores' => $errores
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error en envío masivo de boletas', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function reporteDiario(Request $request, Response $response, array $args): Response
    {
        try {
            $fecha = $args['fecha'];
            $params = $request->getQueryParams();
            $rutEmpresa = $params['rut_empresa'] ?? null;

            if (!$rutEmpresa) {
                return $this->respuestaError($response, ['RUT de empresa es requerido'], 400);
            }

            // Validar formato de fecha
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                return $this->respuestaError($response, ['Formato de fecha inválido (YYYY-MM-DD)'], 400);
            }

            $reporte = $this->boletasService->generarReporteDiario($fecha, $rutEmpresa);

            return $this->respuestaExito($response, $reporte);

        } catch (\Exception $e) {
            $this->logger->error('Error al generar reporte diario', [
                'fecha' => $args['fecha'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    private function validarDatosBoleta(array $data): array
    {
        $errores = [];

        // Validar campos requeridos básicos
        $requeridos = ['emisor', 'receptor', 'detalles'];
        foreach ($requeridos as $campo) {
            if (!isset($data[$campo])) {
                $errores[] = "Campo requerido: {$campo}";
            }
        }

        // Validar emisor
        if (isset($data['emisor'])) {
            if (empty($data['emisor']['rut'])) {
                $errores[] = "RUT del emisor es requerido";
            }
            if (empty($data['emisor']['razon_social'])) {
                $errores[] = "Razón social del emisor es requerida";
            }
        }

        // Validar receptor - para boletas puede ser consumidor final
        if (isset($data['receptor'])) {
            if (empty($data['receptor']['rut']) && empty($data['receptor']['razon_social'])) {
                // Para boletas a consumidor final
                $data['receptor']['rut'] = '66666666-6';
                $data['receptor']['razon_social'] = 'CONSUMIDOR FINAL';
            }
        }

        // Validar detalles
        if (isset($data['detalles']) && is_array($data['detalles'])) {
            if (empty($data['detalles'])) {
                $errores[] = "Debe incluir al menos un detalle";
            } else {
                foreach ($data['detalles'] as $index => $detalle) {
                    if (empty($detalle['nombre_item'])) {
                        $errores[] = "Nombre del item es requerido en detalle " . ($index + 1);
                    }
                    if (!isset($detalle['precio_unitario']) || $detalle['precio_unitario'] <= 0) {
                        $errores[] = "Precio unitario debe ser mayor a 0 en detalle " . ($index + 1);
                    }
                }
            }
        }

        // Validar campos específicos de boletas
        if (isset($data['boleta'])) {
            if (isset($data['boleta']['forma_pago'])) {
                $formasPago = ['efectivo', 'cheque', 'tarjeta_credito', 'tarjeta_debito', 'transferencia', 'otro'];
                if (!in_array($data['boleta']['forma_pago'], $formasPago)) {
                    $errores[] = "Forma de pago no válida";
                }
            }
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores
        ];
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
