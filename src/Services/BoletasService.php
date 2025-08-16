<?php

declare(strict_types=1);

namespace DonFactura\DTE\Services;

use DonFactura\DTE\Models\DTEModel;
use DonFactura\DTE\Models\FoliosModel;
use PDO;

/**
 * Servicio específico para boletas electrónicas (DTE tipo 39)
 */
class BoletasService
{
    private PDO $pdo;
    private array $config;
    private DTEModel $dteModel;
    private FoliosModel $foliosModel;
    private DTEXMLGenerator $xmlGenerator;
    private DigitalSignature $digitalSignature;

    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->dteModel = new DTEModel($pdo);
        $this->foliosModel = new FoliosModel($pdo);
        $this->xmlGenerator = new DTEXMLGenerator($config);
        $this->digitalSignature = new DigitalSignature($config, $pdo);
    }

    public function generar(array $data, int $folio): array
    {
        try {
            // Iniciar transacción
            $this->pdo->beginTransaction();

            // Preparar datos específicos para boleta
            $datosBoleta = $this->prepararDatosBoleta($data, $folio);

            // Crear registro de DTE
            $dteId = $this->dteModel->crear($datosBoleta);

            // Agregar detalles
            if (isset($data['detalles']) && is_array($data['detalles'])) {
                foreach ($data['detalles'] as $index => $detalle) {
                    $detalle['numero_linea'] = $index + 1;
                    $this->dteModel->agregarDetalle($dteId, $detalle);
                }
            }

            // Crear registro específico de boleta
            $this->crearRegistroBoleta($dteId, $data);

            // Marcar folio como utilizado
            $this->foliosModel->marcarFolioUtilizado(
                39, // Tipo boleta
                $data['emisor']['rut'],
                $folio,
                $dteId
            );

            // Generar XML de la boleta
            $xmlBoleta = $this->xmlGenerator->generar(39, $data, $folio);

            // Actualizar registro con XML
            $this->dteModel->actualizar($dteId, [
                'xml_dte' => $xmlBoleta,
                'estado' => 'generado'
            ]);

            $this->pdo->commit();

            return [
                'success' => true,
                'data' => [
                    'id' => $dteId,
                    'tipo_dte' => 39,
                    'folio' => $folio,
                    'fecha_emision' => $datosBoleta['fecha_emision'],
                    'monto_total' => $datosBoleta['monto_total'],
                    'estado' => 'generado',
                    'xml' => $xmlBoleta
                ]
            ];

        } catch (\Exception $e) {
            $this->pdo->rollback();
            
            return [
                'success' => false,
                'error' => 'Error al generar boleta: ' . $e->getMessage()
            ];
        }
    }

    public function obtenerPorId(int $id): ?array
    {
        $dte = $this->dteModel->obtenerPorId($id);
        
        if (!$dte || $dte['tipo_dte'] != 39) {
            return null;
        }

        // Obtener detalles
        $dte['detalles'] = $this->dteModel->obtenerDetalles($id);

        // Obtener datos específicos de boleta
        $sql = "SELECT * FROM boletas_electronicas WHERE dte_id = :dte_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['dte_id' => $id]);
        $boletaData = $stmt->fetch();

        if ($boletaData) {
            $dte['boleta'] = $boletaData;
        }

        return $dte;
    }

    public function generarReporteDiario(string $fecha, string $rutEmpresa): array
    {
        try {
            // Consultar boletas del día
            $sql = "SELECT d.*, b.numero_caja, b.cajero, b.forma_pago 
                    FROM documentos_dte d
                    LEFT JOIN boletas_electronicas b ON d.id = b.dte_id
                    WHERE d.tipo_dte = 39 
                    AND d.fecha_emision = :fecha 
                    AND d.rut_emisor = :rut_emisor
                    ORDER BY d.folio";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'fecha' => $fecha,
                'rut_emisor' => $rutEmpresa
            ]);
            
            $boletas = $stmt->fetchAll();

            // Calcular totales
            $totalBoletas = count($boletas);
            $montoTotal = 0;
            $montoNeto = 0;
            $montoIva = 0;
            
            $estadisticas = [
                'efectivo' => 0,
                'tarjeta_credito' => 0,
                'tarjeta_debito' => 0,
                'transferencia' => 0,
                'otro' => 0
            ];

            foreach ($boletas as $boleta) {
                $montoTotal += $boleta['monto_total'];
                $montoNeto += $boleta['monto_neto'];
                $montoIva += $boleta['monto_iva'];
                
                $formaPago = $boleta['forma_pago'] ?? 'efectivo';
                if (isset($estadisticas[$formaPago])) {
                    $estadisticas[$formaPago] += $boleta['monto_total'];
                } else {
                    $estadisticas['otro'] += $boleta['monto_total'];
                }
            }

            return [
                'fecha' => $fecha,
                'rut_empresa' => $rutEmpresa,
                'resumen' => [
                    'total_boletas' => $totalBoletas,
                    'monto_total' => $montoTotal,
                    'monto_neto' => $montoNeto,
                    'monto_iva' => $montoIva,
                ],
                'por_forma_pago' => $estadisticas,
                'boletas' => $boletas
            ];

        } catch (\Exception $e) {
            return [
                'error' => 'Error al generar reporte: ' . $e->getMessage()
            ];
        }
    }

    public function firmarBoleta(int $id): array
    {
        try {
            $boleta = $this->obtenerPorId($id);
            
            if (!$boleta) {
                return [
                    'success' => false,
                    'error' => 'Boleta no encontrada'
                ];
            }

            if ($boleta['estado'] !== 'generado') {
                return [
                    'success' => false,
                    'error' => 'La boleta debe estar en estado "generado" para ser firmada'
                ];
            }

            // Firmar XML
            $xmlFirmado = $this->digitalSignature->firmarDTE(
                $boleta['xml_dte'], 
                $boleta['rut_emisor']
            );

            if (!$xmlFirmado) {
                return [
                    'success' => false,
                    'error' => 'Error al firmar la boleta'
                ];
            }

            // Actualizar estado
            $this->dteModel->actualizar($id, [
                'xml_firmado' => $xmlFirmado,
                'estado' => 'firmado'
            ]);

            return [
                'success' => true,
                'data' => [
                    'id' => $id,
                    'estado' => 'firmado',
                    'xml_firmado' => $xmlFirmado
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al firmar boleta: ' . $e->getMessage()
            ];
        }
    }

    public function enviarBoletasSII(array $boletaIds): array
    {
        $resultados = [];
        
        foreach ($boletaIds as $id) {
            try {
                $boleta = $this->obtenerPorId($id);
                
                if (!$boleta) {
                    $resultados[] = [
                        'id' => $id,
                        'success' => false,
                        'error' => 'Boleta no encontrada'
                    ];
                    continue;
                }

                if ($boleta['estado'] !== 'firmado') {
                    $resultados[] = [
                        'id' => $id,
                        'success' => false,
                        'error' => 'La boleta debe estar firmada'
                    ];
                    continue;
                }

                // TODO: Implementar envío real al SII
                // Por ahora solo cambiamos el estado
                $this->dteModel->actualizar($id, ['estado' => 'enviado_sii']);

                $resultados[] = [
                    'id' => $id,
                    'success' => true,
                    'estado' => 'enviado_sii'
                ];

            } catch (\Exception $e) {
                $resultados[] = [
                    'id' => $id,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $resultados;
    }

    private function prepararDatosBoleta(array $data, int $folio): array
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

        // Calcular IVA para boletas (19%)
        $iva = round($neto * 0.19, 0);
        $total = $neto + $iva;

        // Asegurar que el receptor sea consumidor final si no se especifica
        if (empty($data['receptor']['rut'])) {
            $data['receptor']['rut'] = '66666666-6';
            $data['receptor']['razon_social'] = 'CONSUMIDOR FINAL';
        }

        return [
            'tipo_dte' => 39,
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
            'monto_neto' => $neto,
            'monto_iva' => $iva,
            'monto_total' => $total,
            'observaciones' => $data['observaciones'] ?? null,
        ];
    }

    private function crearRegistroBoleta(int $dteId, array $data): void
    {
        $sql = "INSERT INTO boletas_electronicas (
            dte_id, numero_caja, cajero, forma_pago, periodo_desde, periodo_hasta
        ) VALUES (
            :dte_id, :numero_caja, :cajero, :forma_pago, :periodo_desde, :periodo_hasta
        )";

        $boletaData = $data['boleta'] ?? [];

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'dte_id' => $dteId,
            'numero_caja' => $boletaData['numero_caja'] ?? null,
            'cajero' => $boletaData['cajero'] ?? null,
            'forma_pago' => $boletaData['forma_pago'] ?? 'efectivo',
            'periodo_desde' => $boletaData['periodo_desde'] ?? null,
            'periodo_hasta' => $boletaData['periodo_hasta'] ?? null,
        ]);
    }
}
