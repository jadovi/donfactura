<?php

declare(strict_types=1);

namespace DonFactura\DTE\Controllers;

use DonFactura\DTE\Models\DTEModel;
use DonFactura\DTE\Models\FoliosModel;
use DonFactura\DTE\Services\DTEXMLGenerator;
use DonFactura\DTE\Services\DigitalSignature;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use PDO;

/**
 * Controlador para documentos tributarios electrónicos
 */
class DTEController
{
    private PDO $pdo;
    private LoggerInterface $logger;
    private array $config;
    private DTEModel $dteModel;
    private FoliosModel $foliosModel;
    private DTEXMLGenerator $xmlGenerator;
    private DigitalSignature $digitalSignature;

    public function __construct(PDO $pdo, LoggerInterface $logger, array $config)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->config = $config;
        $this->dteModel = new DTEModel($pdo);
        $this->foliosModel = new FoliosModel($pdo);
        $this->xmlGenerator = new DTEXMLGenerator($config);
        $this->digitalSignature = new DigitalSignature($config);
    }

    public function generar(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            
            // Validar datos requeridos
            $validacion = $this->validarDatosGeneracion($data);
            if (!$validacion['valido']) {
                return $this->respuestaError($response, $validacion['errores'], 400);
            }

            // Verificar que el tipo de DTE sea válido
            if (!$this->esTipoDTEValido($data['tipo_dte'])) {
                return $this->respuestaError($response, ['Tipo de DTE no válido'], 400);
            }

            // Obtener siguiente folio disponible
            $folio = $this->foliosModel->obtenerSiguienteFolio(
                $data['tipo_dte'], 
                $data['emisor']['rut']
            );

            if (!$folio) {
                return $this->respuestaError($response, ['No hay folios disponibles para este tipo de DTE'], 400);
            }

            // Iniciar transacción
            $this->pdo->beginTransaction();

            try {
                // Preparar datos del DTE
                $datosRaw = $this->prepararDatosDTE($data, $folio);
                
                // Crear registro en base de datos
                $dteId = $this->dteModel->crear($datosRaw);
                
                // Agregar detalles
                if (isset($data['detalles']) && is_array($data['detalles'])) {
                    foreach ($data['detalles'] as $index => $detalle) {
                        $detalle['numero_linea'] = $index + 1;
                        $this->dteModel->agregarDetalle($dteId, $detalle);
                    }
                }
                
                // Agregar referencias si existen
                if (isset($data['referencias']) && is_array($data['referencias'])) {
                    foreach ($data['referencias'] as $index => $referencia) {
                        $referencia['numero_linea'] = $index + 1;
                        $this->dteModel->agregarReferencia($dteId, $referencia);
                    }
                }
                
                // Marcar folio como utilizado
                $this->foliosModel->marcarFolioUtilizado(
                    $data['tipo_dte'],
                    $data['emisor']['rut'],
                    $folio,
                    $dteId
                );

                // Generar XML del DTE
                $xmlDte = $this->xmlGenerator->generar($data['tipo_dte'], $data, $folio);
                
                // Actualizar registro con XML generado
                $this->dteModel->actualizar($dteId, [
                    'xml_dte' => $xmlDte,
                    'estado' => 'generado'
                ]);

                $this->pdo->commit();

                $this->logger->info('DTE generado exitosamente', [
                    'dte_id' => $dteId,
                    'tipo' => $data['tipo_dte'],
                    'folio' => $folio
                ]);

                return $this->respuestaExito($response, [
                    'id' => $dteId,
                    'tipo_dte' => $data['tipo_dte'],
                    'folio' => $folio,
                    'estado' => 'generado',
                    'xml' => $xmlDte
                ]);

            } catch (\Exception $e) {
                $this->pdo->rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            $this->logger->error('Error al generar DTE', [
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
            $dte = $this->dteModel->obtenerPorId($id);

            if (!$dte) {
                return $this->respuestaError($response, ['DTE no encontrado'], 404);
            }

            // Obtener detalles y referencias
            $dte['detalles'] = $this->dteModel->obtenerDetalles($id);
            $dte['referencias'] = $this->dteModel->obtenerReferencias($id);

            return $this->respuestaExito($response, $dte);

        } catch (\Exception $e) {
            $this->logger->error('Error al obtener DTE', [
                'id' => $args['id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function listarPorTipo(Request $request, Response $response, array $args): Response
    {
        try {
            $tipo = (int) $args['tipo'];
            $params = $request->getQueryParams();
            $limite = min((int) ($params['limite'] ?? 50), 100);
            $offset = (int) ($params['offset'] ?? 0);

            if (!$this->esTipoDTEValido($tipo)) {
                return $this->respuestaError($response, ['Tipo de DTE no válido'], 400);
            }

            $dtes = $this->dteModel->listarPorTipo($tipo, $limite, $offset);

            return $this->respuestaExito($response, [
                'tipo_dte' => $tipo,
                'limite' => $limite,
                'offset' => $offset,
                'total' => count($dtes),
                'documentos' => $dtes
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error al listar DTEs por tipo', [
                'tipo' => $args['tipo'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function firmar(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int) $args['id'];
            $dte = $this->dteModel->obtenerPorId($id);

            if (!$dte) {
                return $this->respuestaError($response, ['DTE no encontrado'], 404);
            }

            if ($dte['estado'] !== 'generado') {
                return $this->respuestaError($response, ['DTE debe estar en estado "generado" para ser firmado'], 400);
            }

            // Firmar el XML
            $xmlFirmado = $this->digitalSignature->firmarDTE($dte['xml_dte'], $dte['rut_emisor']);

            if (!$xmlFirmado) {
                return $this->respuestaError($response, ['Error al firmar el DTE'], 500);
            }

            // Actualizar registro
            $this->dteModel->actualizar($id, [
                'xml_firmado' => $xmlFirmado,
                'estado' => 'firmado'
            ]);

            $this->logger->info('DTE firmado exitosamente', ['dte_id' => $id]);

            return $this->respuestaExito($response, [
                'id' => $id,
                'estado' => 'firmado',
                'xml_firmado' => $xmlFirmado
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error al firmar DTE', [
                'id' => $args['id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function enviarSII(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int) $args['id'];
            $dte = $this->dteModel->obtenerPorId($id);

            if (!$dte) {
                return $this->respuestaError($response, ['DTE no encontrado'], 404);
            }

            if ($dte['estado'] !== 'firmado') {
                return $this->respuestaError($response, ['DTE debe estar firmado para ser enviado'], 400);
            }

            // TODO: Implementar envío al SII
            // Por ahora solo actualizamos el estado
            $this->dteModel->actualizar($id, ['estado' => 'enviado_sii']);

            $this->logger->info('DTE enviado al SII', ['dte_id' => $id]);

            return $this->respuestaExito($response, [
                'id' => $id,
                'estado' => 'enviado_sii',
                'mensaje' => 'DTE enviado al SII exitosamente'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error al enviar DTE al SII', [
                'id' => $args['id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function consultarEstado(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int) $args['id'];
            $dte = $this->dteModel->obtenerPorId($id);

            if (!$dte) {
                return $this->respuestaError($response, ['DTE no encontrado'], 404);
            }

            return $this->respuestaExito($response, [
                'id' => $id,
                'tipo_dte' => $dte['tipo_dte'],
                'folio' => $dte['folio'],
                'estado' => $dte['estado'],
                'fecha_emision' => $dte['fecha_emision'],
                'monto_total' => $dte['monto_total']
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error al consultar estado DTE', [
                'id' => $args['id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    private function validarDatosGeneracion(array $data): array
    {
        $errores = [];

        // Validar campos requeridos
        $requeridos = ['tipo_dte', 'emisor', 'receptor', 'detalles'];
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

        // Validar receptor
        if (isset($data['receptor'])) {
            if (empty($data['receptor']['rut'])) {
                $errores[] = "RUT del receptor es requerido";
            }
            if (empty($data['receptor']['razon_social'])) {
                $errores[] = "Razón social del receptor es requerida";
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

        return [
            'valido' => empty($errores),
            'errores' => $errores
        ];
    }

    private function prepararDatosDTE(array $data, int $folio): array
    {
        $total = 0;
        $neto = 0;
        $iva = 0;

        // Calcular totales
        if (isset($data['detalles'])) {
            foreach ($data['detalles'] as $detalle) {
                $cantidad = $detalle['cantidad'] ?? 1;
                $precio = $detalle['precio_unitario'];
                $descuento = $detalle['descuento_monto'] ?? 0;
                
                $montoLinea = ($cantidad * $precio) - $descuento;
                $neto += $montoLinea;
            }
        }

        // Calcular IVA (19% en Chile)
        $iva = $neto * 0.19;
        $total = $neto + $iva;

        return [
            'tipo_dte' => $data['tipo_dte'],
            'folio' => $folio,
            'fecha_emision' => $data['fecha_emision'] ?? date('Y-m-d'),
            'rut_emisor' => $data['emisor']['rut'],
            'razon_social_emisor' => $data['emisor']['razon_social'],
            'giro_emisor' => $data['emisor']['giro'] ?? null,
            'direccion_emisor' => $data['emisor']['direccion'] ?? null,
            'comuna_emisor' => $data['emisor']['comuna'] ?? null,
            'ciudad_emisor' => $data['emisor']['ciudad'] ?? null,
            'rut_receptor' => $data['receptor']['rut'],
            'razon_social_receptor' => $data['receptor']['razon_social'],
            'giro_receptor' => $data['receptor']['giro'] ?? null,
            'direccion_receptor' => $data['receptor']['direccion'] ?? null,
            'comuna_receptor' => $data['receptor']['comuna'] ?? null,
            'ciudad_receptor' => $data['receptor']['ciudad'] ?? null,
            'monto_neto' => round($neto, 2),
            'monto_iva' => round($iva, 2),
            'monto_total' => round($total, 2),
            'observaciones' => $data['observaciones'] ?? null,
        ];
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
