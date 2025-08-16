<?php

declare(strict_types=1);

namespace DonFactura\DTE\Services;

use DonFactura\DTE\Models\DTEModel;
use DonFactura\DTE\Models\BHEModel;
use DonFactura\DTE\Models\ProfesionalesModel;
use DonFactura\DTE\Models\FoliosModel;
use PDO;

/**
 * Servicio para Boletas de Honorarios Electrónicas (BHE - DTE Tipo 41)
 */
class BHEService
{
    private PDO $pdo;
    private array $config;
    private DTEModel $dteModel;
    private BHEModel $bheModel;
    private ProfesionalesModel $profesionalesModel;
    private FoliosModel $foliosModel;
    private BHEXMLGenerator $xmlGenerator;
    private BHEDigitalSignature $digitalSignature;

    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->dteModel = new DTEModel($pdo);
        $this->bheModel = new BHEModel($pdo);
        $this->profesionalesModel = new ProfesionalesModel($pdo);
        $this->foliosModel = new FoliosModel($pdo);
        $this->xmlGenerator = new BHEXMLGenerator($config);
        $this->digitalSignature = new BHEDigitalSignature($config);
    }

    public function generar(array $data): array
    {
        try {
            // Validar datos de entrada
            $validacion = $this->validarDatosBHE($data);
            if (!$validacion['valido']) {
                return [
                    'success' => false,
                    'error' => 'Datos inválidos',
                    'errores' => $validacion['errores']
                ];
            }

            // Verificar que el profesional esté registrado y activo
            $profesional = $this->profesionalesModel->obtenerPorRut($data['profesional']['rut']);
            if (!$profesional) {
                return [
                    'success' => false,
                    'error' => 'Profesional no registrado o inactivo'
                ];
            }

            // Obtener siguiente folio para BHE (tipo 41)
            $folio = $this->foliosModel->obtenerSiguienteFolio(41, $data['profesional']['rut']);
            if (!$folio) {
                return [
                    'success' => false,
                    'error' => 'No hay folios disponibles para BHE (tipo 41)'
                ];
            }

            // Calcular montos con retención
            $calculos = $this->calcularMontosBHE($data);

            // Iniciar transacción
            $this->pdo->beginTransaction();

            try {
                // Crear DTE principal
                $datosDte = $this->prepararDatosDTE($data, $folio, $calculos, $profesional);
                $dteId = $this->dteModel->crear($datosDte);

                // Crear registro específico BHE
                $datosBhe = $this->prepararDatosBHE($data, $dteId, $calculos, $profesional);
                $bheId = $this->bheModel->crear($datosBhe);

                // Marcar folio como utilizado
                $this->foliosModel->marcarFolioUtilizado(41, $data['profesional']['rut'], $folio, $dteId);

                // Generar XML específico para BHE
                $xmlBhe = $this->xmlGenerator->generar($data, $folio, $calculos, $profesional);

                // IMPORTANTE: Firmar digitalmente (obligatorio para BHE)
                $xmlFirmado = $this->digitalSignature->firmarBHE($xmlBhe, $data['profesional']['rut']);
                
                if (!$xmlFirmado) {
                    throw new \Exception('Error al firmar BHE digitalmente');
                }

                // Actualizar DTE con XML firmado
                $this->dteModel->actualizar($dteId, [
                    'xml_dte' => $xmlBhe,
                    'xml_firmado' => $xmlFirmado,
                    'estado' => 'firmado'
                ]);

                $this->pdo->commit();

                return [
                    'success' => true,
                    'data' => [
                        'dte_id' => $dteId,
                        'bhe_id' => $bheId,
                        'tipo_dte' => 41,
                        'folio' => $folio,
                        'rut_profesional' => $data['profesional']['rut'],
                        'nombre_profesional' => $profesional['nombres'] . ' ' . $profesional['apellido_paterno'],
                        'monto_bruto' => $calculos['monto_bruto'],
                        'retencion' => $calculos['retencion_honorarios'],
                        'monto_liquido' => $calculos['monto_liquido'],
                        'estado' => 'firmado',
                        'xml_firmado' => $xmlFirmado
                    ]
                ];

            } catch (\Exception $e) {
                $this->pdo->rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al generar BHE: ' . $e->getMessage()
            ];
        }
    }

    public function obtenerPorId(int $dteId): ?array
    {
        $bhe = $this->bheModel->obtenerPorDteId($dteId);
        
        if (!$bhe) {
            return null;
        }

        // Obtener datos del profesional
        $profesional = $this->profesionalesModel->obtenerPorRut($bhe['rut_profesional']);
        
        return [
            'bhe' => $bhe,
            'profesional' => $profesional
        ];
    }

    public function listarPorProfesional(string $rutProfesional, int $limite = 50, int $offset = 0): array
    {
        return $this->bheModel->obtenerPorProfesional($rutProfesional, $limite, $offset);
    }

    public function generarReportePeriodo(string $rutProfesional, string $fechaDesde, string $fechaHasta): array
    {
        // Validar período
        $validacionPeriodo = $this->bheModel->validarPeriodoServicios($fechaDesde, $fechaHasta);
        if (!$validacionPeriodo['valido']) {
            return [
                'success' => false,
                'errores' => $validacionPeriodo['errores']
            ];
        }

        // Obtener BHE del período
        $bhesPeriodo = $this->bheModel->obtenerPorPeriodo($rutProfesional, $fechaDesde, $fechaHasta);
        
        // Calcular totales
        $totales = $this->bheModel->calcularTotalesPeriodo($rutProfesional, $fechaDesde, $fechaHasta);
        
        // Obtener datos del profesional
        $profesional = $this->profesionalesModel->obtenerPorRut($rutProfesional);

        return [
            'success' => true,
            'data' => [
                'profesional' => $profesional,
                'periodo' => [
                    'desde' => $fechaDesde,
                    'hasta' => $fechaHasta
                ],
                'totales' => $totales,
                'bhe_detalle' => $bhesPeriodo,
                'resumen' => [
                    'total_documentos' => count($bhesPeriodo),
                    'ingresos_brutos' => $totales['total_bruto'],
                    'retenciones' => $totales['total_retencion'],
                    'ingresos_liquidos' => $totales['total_liquido']
                ]
            ]
        ];
    }

    private function validarDatosBHE(array $data): array
    {
        $errores = [];

        // Validar campos requeridos
        $requeridos = ['profesional', 'pagador', 'servicios'];
        foreach ($requeridos as $campo) {
            if (!isset($data[$campo])) {
                $errores[] = "Campo requerido: {$campo}";
            }
        }

        // Validar datos del profesional
        if (isset($data['profesional'])) {
            if (empty($data['profesional']['rut'])) {
                $errores[] = "RUT del profesional es requerido";
            }
        }

        // Validar datos del pagador
        if (isset($data['pagador'])) {
            if (empty($data['pagador']['rut'])) {
                $errores[] = "RUT del pagador es requerido";
            }
            if (empty($data['pagador']['nombre'])) {
                $errores[] = "Nombre del pagador es requerido";
            }
        }

        // Validar datos de servicios
        if (isset($data['servicios'])) {
            if (empty($data['servicios']['descripcion'])) {
                $errores[] = "Descripción de servicios es requerida";
            }
            if (empty($data['servicios']['monto_bruto']) || $data['servicios']['monto_bruto'] <= 0) {
                $errores[] = "Monto bruto debe ser mayor a 0";
            }
            if (empty($data['servicios']['periodo_desde'])) {
                $errores[] = "Fecha inicio del período es requerida";
            }
            if (empty($data['servicios']['periodo_hasta'])) {
                $errores[] = "Fecha fin del período es requerida";
            }

            // Validar período de servicios
            if (!empty($data['servicios']['periodo_desde']) && !empty($data['servicios']['periodo_hasta'])) {
                $validacionPeriodo = $this->bheModel->validarPeriodoServicios(
                    $data['servicios']['periodo_desde'],
                    $data['servicios']['periodo_hasta']
                );
                
                if (!$validacionPeriodo['valido']) {
                    $errores = array_merge($errores, $validacionPeriodo['errores']);
                }
            }
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores
        ];
    }

    private function calcularMontosBHE(array $data): array
    {
        $montoBruto = (float) $data['servicios']['monto_bruto'];
        $porcentajeRetencion = $data['servicios']['porcentaje_retencion'] ?? 10.0;
        
        return $this->bheModel->calcularRetencion($montoBruto, $porcentajeRetencion);
    }

    private function prepararDatosDTE(array $data, int $folio, array $calculos, array $profesional): array
    {
        return [
            'tipo_dte' => 41, // BHE
            'folio' => $folio,
            'fecha_emision' => date('Y-m-d'),
            'rut_emisor' => $data['profesional']['rut'],
            'razon_social_emisor' => $profesional['nombres'] . ' ' . $profesional['apellido_paterno'] . ' ' . ($profesional['apellido_materno'] ?? ''),
            'giro_emisor' => $profesional['profesion'],
            'direccion_emisor' => $profesional['direccion'],
            'comuna_emisor' => $profesional['comuna'],
            'ciudad_emisor' => $profesional['region'],
            'rut_receptor' => $data['pagador']['rut'],
            'razon_social_receptor' => $data['pagador']['nombre'],
            'direccion_receptor' => $data['pagador']['direccion'] ?? null,
            'comuna_receptor' => $data['pagador']['comuna'] ?? null,
            'monto_neto' => 0, // En BHE no aplica concepto de neto/IVA
            'monto_iva' => 0,
            'monto_total' => $calculos['monto_liquido'], // El total es el monto líquido
            'observaciones' => $data['servicios']['observaciones'] ?? null,
        ];
    }

    private function prepararDatosBHE(array $data, int $dteId, array $calculos, array $profesional): array
    {
        return [
            'dte_id' => $dteId,
            'rut_profesional' => $data['profesional']['rut'],
            'nombre_profesional' => $profesional['nombres'],
            'apellido_paterno' => $profesional['apellido_paterno'],
            'apellido_materno' => $profesional['apellido_materno'],
            'profesion' => $profesional['profesion'],
            'direccion_profesional' => $profesional['direccion'],
            'comuna_profesional' => $profesional['comuna'],
            'codigo_comuna_profesional' => $profesional['codigo_comuna'],
            'rut_pagador' => $data['pagador']['rut'],
            'nombre_pagador' => $data['pagador']['nombre'],
            'direccion_pagador' => $data['pagador']['direccion'] ?? null,
            'comuna_pagador' => $data['pagador']['comuna'] ?? null,
            'codigo_comuna_pagador' => $data['pagador']['codigo_comuna'] ?? null,
            'periodo_desde' => $data['servicios']['periodo_desde'],
            'periodo_hasta' => $data['servicios']['periodo_hasta'],
            'descripcion_servicios' => $data['servicios']['descripcion'],
            'monto_bruto' => $calculos['monto_bruto'],
            'retencion_honorarios' => $calculos['retencion_honorarios'],
            'monto_liquido' => $calculos['monto_liquido'],
            'aplica_retencion' => true,
            'porcentaje_retencion' => $calculos['porcentaje_retencion'],
        ];
    }
}
