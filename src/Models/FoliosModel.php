<?php

declare(strict_types=1);

namespace DonFactura\DTE\Models;

use PDO;

/**
 * Modelo para gestión de folios CAF
 */
class FoliosModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function crear(array $data): int
    {
        $sql = "INSERT INTO folios (
            tipo_dte, rut_empresa, folio_desde, folio_hasta,
            fecha_resolucion, fecha_vencimiento, xml_caf, folios_disponibles, activo
        ) VALUES (
            :tipo_dte, :rut_empresa, :folio_desde, :folio_hasta,
            :fecha_resolucion, :fecha_vencimiento, :xml_caf, :folios_disponibles, :activo
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tipo_dte' => $data['tipo_dte'],
            'rut_empresa' => $data['rut_empresa'],
            'folio_desde' => $data['folio_desde'],
            'folio_hasta' => $data['folio_hasta'],
            'fecha_resolucion' => $data['fecha_resolucion'],
            'fecha_vencimiento' => $data['fecha_vencimiento'],
            'xml_caf' => $data['xml_caf'],
            'folios_disponibles' => $data['folio_hasta'] - $data['folio_desde'] + 1,
            'activo' => true,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerDisponibles(int $tipoDte, string $rutEmpresa): array
    {
        $sql = "SELECT * FROM folios 
                WHERE tipo_dte = :tipo_dte 
                AND rut_empresa = :rut_empresa 
                AND activo = true 
                AND folios_disponibles > 0 
                AND fecha_vencimiento > CURDATE()
                ORDER BY folio_desde ASC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tipo_dte' => $tipoDte,
            'rut_empresa' => $rutEmpresa
        ]);
        
        return $stmt->fetchAll();
    }

    public function obtenerSiguienteFolio(int $tipoDte, string $rutEmpresa): ?int
    {
        // Buscar el primer rango de folios disponible
        $foliosDisponibles = $this->obtenerDisponibles($tipoDte, $rutEmpresa);
        
        if (empty($foliosDisponibles)) {
            return null;
        }

        $rangoFolios = $foliosDisponibles[0];
        
        // Buscar folios ya utilizados en este rango
        $sql = "SELECT folio_numero FROM folios_utilizados fu
                INNER JOIN folios f ON fu.folio_caf_id = f.id
                WHERE f.id = :folio_caf_id
                ORDER BY folio_numero DESC
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['folio_caf_id' => $rangoFolios['id']]);
        $ultimoUtilizado = $stmt->fetchColumn();
        
        if ($ultimoUtilizado) {
            $siguienteFolio = $ultimoUtilizado + 1;
        } else {
            $siguienteFolio = $rangoFolios['folio_desde'];
        }
        
        // Verificar que el folio esté dentro del rango
        if ($siguienteFolio > $rangoFolios['folio_hasta']) {
            // Marcar este rango como agotado
            $this->actualizarFoliosDisponibles($rangoFolios['id'], 0);
            
            // Intentar con el siguiente rango
            return $this->obtenerSiguienteFolio($tipoDte, $rutEmpresa);
        }
        
        return $siguienteFolio;
    }

    public function marcarFolioUtilizado(int $tipoDte, string $rutEmpresa, int $folio, int $dteId = null): bool
    {
        // Encontrar el rango CAF correspondiente
        $sql = "SELECT id FROM folios 
                WHERE tipo_dte = :tipo_dte 
                AND rut_empresa = :rut_empresa 
                AND :folio BETWEEN folio_desde AND folio_hasta 
                AND activo = true";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tipo_dte' => $tipoDte,
            'rut_empresa' => $rutEmpresa,
            'folio' => $folio
        ]);
        
        $foliosCafId = $stmt->fetchColumn();
        
        if (!$foliosCafId) {
            return false;
        }
        
        // Marcar folio como utilizado
        $sql = "INSERT INTO folios_utilizados (folio_caf_id, folio_numero, dte_id) 
                VALUES (:folio_caf_id, :folio_numero, :dte_id)
                ON DUPLICATE KEY UPDATE fecha_utilizacion = CURRENT_TIMESTAMP";
        
        $stmt = $this->pdo->prepare($sql);
        $resultado = $stmt->execute([
            'folio_caf_id' => $foliosCafId,
            'folio_numero' => $folio,
            'dte_id' => $dteId
        ]);
        
        if ($resultado) {
            // Actualizar contador de folios disponibles
            $this->recalcularFoliosDisponibles($foliosCafId);
        }
        
        return $resultado;
    }

    public function validarFolio(int $tipoDte, string $rutEmpresa, int $folio): bool
    {
        $sql = "SELECT COUNT(*) FROM folios 
                WHERE tipo_dte = :tipo_dte 
                AND rut_empresa = :rut_empresa 
                AND :folio BETWEEN folio_desde AND folio_hasta 
                AND activo = true 
                AND fecha_vencimiento > CURDATE()";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tipo_dte' => $tipoDte,
            'rut_empresa' => $rutEmpresa,
            'folio' => $folio
        ]);
        
        return $stmt->fetchColumn() > 0;
    }

    public function obtenerCAFPorFolio(int $tipoDte, string $rutEmpresa, int $folio): ?array
    {
        $sql = "SELECT * FROM folios 
                WHERE tipo_dte = :tipo_dte 
                AND rut_empresa = :rut_empresa 
                AND :folio BETWEEN folio_desde AND folio_hasta 
                AND activo = true";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tipo_dte' => $tipoDte,
            'rut_empresa' => $rutEmpresa,
            'folio' => $folio
        ]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    private function actualizarFoliosDisponibles(int $folioCafId, int $disponibles): bool
    {
        $sql = "UPDATE folios SET folios_disponibles = :disponibles WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            'disponibles' => $disponibles,
            'id' => $folioCafId
        ]);
    }

    private function recalcularFoliosDisponibles(int $folioCafId): void
    {
        // Contar folios utilizados
        $sql = "SELECT COUNT(*) FROM folios_utilizados WHERE folio_caf_id = :folio_caf_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['folio_caf_id' => $folioCafId]);
        $foliosUtilizados = $stmt->fetchColumn();
        
        // Obtener total de folios en el rango
        $sql = "SELECT (folio_hasta - folio_desde + 1) as total FROM folios WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $folioCafId]);
        $totalFolios = $stmt->fetchColumn();
        
        // Actualizar folios disponibles
        $foliosDisponibles = $totalFolios - $foliosUtilizados;
        $this->actualizarFoliosDisponibles($folioCafId, $foliosDisponibles);
    }

    public function listar(string $rutEmpresa = null, int $tipoDte = null): array
    {
        $conditions = ['activo = true'];
        $params = [];
        
        if ($rutEmpresa) {
            $conditions[] = 'rut_empresa = :rut_empresa';
            $params['rut_empresa'] = $rutEmpresa;
        }
        
        if ($tipoDte) {
            $conditions[] = 'tipo_dte = :tipo_dte';
            $params['tipo_dte'] = $tipoDte;
        }
        
        $sql = "SELECT * FROM folios WHERE " . implode(' AND ', $conditions) . " ORDER BY created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
}
