<?php

declare(strict_types=1);

namespace DonFactura\DTE\Models;

use PDO;

/**
 * Modelo para Boletas de Honorarios Electrónicas (BHE - DTE Tipo 41)
 */
class BHEModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function crear(array $data): int
    {
        $sql = "INSERT INTO boletas_honorarios_electronicas (
            dte_id, rut_profesional, nombre_profesional, apellido_paterno, apellido_materno,
            profesion, direccion_profesional, comuna_profesional, codigo_comuna_profesional,
            rut_pagador, nombre_pagador, direccion_pagador, comuna_pagador, codigo_comuna_pagador,
            periodo_desde, periodo_hasta, descripcion_servicios,
            monto_bruto, retencion_honorarios, monto_liquido,
            aplica_retencion, porcentaje_retencion
        ) VALUES (
            :dte_id, :rut_profesional, :nombre_profesional, :apellido_paterno, :apellido_materno,
            :profesion, :direccion_profesional, :comuna_profesional, :codigo_comuna_profesional,
            :rut_pagador, :nombre_pagador, :direccion_pagador, :comuna_pagador, :codigo_comuna_pagador,
            :periodo_desde, :periodo_hasta, :descripcion_servicios,
            :monto_bruto, :retencion_honorarios, :monto_liquido,
            :aplica_retencion, :porcentaje_retencion
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'dte_id' => $data['dte_id'],
            'rut_profesional' => $data['rut_profesional'],
            'nombre_profesional' => $data['nombre_profesional'],
            'apellido_paterno' => $data['apellido_paterno'],
            'apellido_materno' => $data['apellido_materno'] ?? null,
            'profesion' => $data['profesion'] ?? null,
            'direccion_profesional' => $data['direccion_profesional'] ?? null,
            'comuna_profesional' => $data['comuna_profesional'] ?? null,
            'codigo_comuna_profesional' => $data['codigo_comuna_profesional'] ?? null,
            'rut_pagador' => $data['rut_pagador'],
            'nombre_pagador' => $data['nombre_pagador'],
            'direccion_pagador' => $data['direccion_pagador'] ?? null,
            'comuna_pagador' => $data['comuna_pagador'] ?? null,
            'codigo_comuna_pagador' => $data['codigo_comuna_pagador'] ?? null,
            'periodo_desde' => $data['periodo_desde'],
            'periodo_hasta' => $data['periodo_hasta'],
            'descripcion_servicios' => $data['descripcion_servicios'],
            'monto_bruto' => $data['monto_bruto'],
            'retencion_honorarios' => $data['retencion_honorarios'],
            'monto_liquido' => $data['monto_liquido'],
            'aplica_retencion' => $data['aplica_retencion'] ?? true,
            'porcentaje_retencion' => $data['porcentaje_retencion'] ?? 10.00,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerPorDteId(int $dteId): ?array
    {
        $sql = "SELECT bhe.*, d.tipo_dte, d.folio, d.fecha_emision, d.estado 
                FROM boletas_honorarios_electronicas bhe
                INNER JOIN documentos_dte d ON bhe.dte_id = d.id
                WHERE bhe.dte_id = :dte_id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['dte_id' => $dteId]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function obtenerPorProfesional(string $rutProfesional, int $limite = 50, int $offset = 0): array
    {
        $sql = "SELECT bhe.*, d.tipo_dte, d.folio, d.fecha_emision, d.estado, d.monto_total
                FROM boletas_honorarios_electronicas bhe
                INNER JOIN documentos_dte d ON bhe.dte_id = d.id
                WHERE bhe.rut_profesional = :rut_profesional 
                ORDER BY d.fecha_emision DESC, d.folio DESC
                LIMIT :limite OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('rut_profesional', $rutProfesional, PDO::PARAM_STR);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    public function obtenerPorPeriodo(string $rutProfesional, string $fechaDesde, string $fechaHasta): array
    {
        $sql = "SELECT bhe.*, d.tipo_dte, d.folio, d.fecha_emision, d.estado, d.monto_total
                FROM boletas_honorarios_electronicas bhe
                INNER JOIN documentos_dte d ON bhe.dte_id = d.id
                WHERE bhe.rut_profesional = :rut_profesional 
                AND d.fecha_emision BETWEEN :fecha_desde AND :fecha_hasta
                ORDER BY d.fecha_emision DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'rut_profesional' => $rutProfesional,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta
        ]);
        
        return $stmt->fetchAll();
    }

    public function calcularTotalesPeriodo(string $rutProfesional, string $fechaDesde, string $fechaHasta): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_bhe,
                    SUM(bhe.monto_bruto) as total_bruto,
                    SUM(bhe.retencion_honorarios) as total_retencion,
                    SUM(bhe.monto_liquido) as total_liquido
                FROM boletas_honorarios_electronicas bhe
                INNER JOIN documentos_dte d ON bhe.dte_id = d.id
                WHERE bhe.rut_profesional = :rut_profesional 
                AND d.fecha_emision BETWEEN :fecha_desde AND :fecha_hasta
                AND d.estado IN ('firmado', 'enviado_sii', 'aceptado')";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'rut_profesional' => $rutProfesional,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta
        ]);
        
        return $stmt->fetch() ?: [
            'total_bhe' => 0,
            'total_bruto' => 0.00,
            'total_retencion' => 0.00,
            'total_liquido' => 0.00
        ];
    }

    public function actualizar(int $bheId, array $data): bool
    {
        $fields = [];
        $params = ['id' => $bheId];

        $allowedFields = [
            'descripcion_servicios', 'monto_bruto', 'retencion_honorarios', 
            'monto_liquido', 'aplica_retencion', 'porcentaje_retencion'
        ];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE boletas_honorarios_electronicas SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute($params);
    }

    public function validarPeriodoServicios(string $fechaDesde, string $fechaHasta): array
    {
        $errores = [];
        
        // Validar formato de fechas
        $desde = \DateTime::createFromFormat('Y-m-d', $fechaDesde);
        $hasta = \DateTime::createFromFormat('Y-m-d', $fechaHasta);
        
        if (!$desde) {
            $errores[] = "Fecha desde inválida";
        }
        
        if (!$hasta) {
            $errores[] = "Fecha hasta inválida";
        }
        
        if ($desde && $hasta) {
            // Validar que fecha desde sea anterior a fecha hasta
            if ($desde > $hasta) {
                $errores[] = "Fecha desde debe ser anterior a fecha hasta";
            }
            
            // Validar que el período no sea mayor a 12 meses
            $diferencia = $desde->diff($hasta);
            if ($diferencia->y >= 1) {
                $errores[] = "El período de servicios no puede ser mayor a 12 meses";
            }
            
            // Validar que las fechas no sean futuras
            $hoy = new \DateTime();
            if ($hasta > $hoy) {
                $errores[] = "Las fechas no pueden ser futuras";
            }
        }
        
        return [
            'valido' => empty($errores),
            'errores' => $errores
        ];
    }

    public function calcularRetencion(float $montoBruto, float $porcentaje = 10.0): array
    {
        $retencion = round($montoBruto * ($porcentaje / 100), 0);
        $montoLiquido = $montoBruto - $retencion;
        
        return [
            'monto_bruto' => $montoBruto,
            'porcentaje_retencion' => $porcentaje,
            'retencion_honorarios' => $retencion,
            'monto_liquido' => $montoLiquido
        ];
    }

    public function obtenerEstadisticasProfesional(string $rutProfesional): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_bhe_emitidas,
                    COUNT(CASE WHEN d.estado = 'aceptado' THEN 1 END) as bhe_aceptadas,
                    SUM(CASE WHEN d.estado = 'aceptado' THEN bhe.monto_bruto ELSE 0 END) as ingresos_brutos_aceptados,
                    SUM(CASE WHEN d.estado = 'aceptado' THEN bhe.retencion_honorarios ELSE 0 END) as retenciones_total,
                    AVG(bhe.monto_bruto) as promedio_monto_bruto,
                    MIN(d.fecha_emision) as primera_bhe,
                    MAX(d.fecha_emision) as ultima_bhe
                FROM boletas_honorarios_electronicas bhe
                INNER JOIN documentos_dte d ON bhe.dte_id = d.id
                WHERE bhe.rut_profesional = :rut_profesional";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['rut_profesional' => $rutProfesional]);
        
        return $stmt->fetch() ?: [];
    }
}
