<?php

declare(strict_types=1);

namespace DonFactura\DTE\Models;

use PDO;

/**
 * Modelo para gestión de profesionales independientes (BHE)
 */
class ProfesionalesModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function crear(array $data): int
    {
        $sql = "INSERT INTO profesionales_bhe (
            rut_profesional, nombres, apellido_paterno, apellido_materno, fecha_nacimiento,
            profesion, titulo_profesional, universidad,
            telefono, email, direccion, comuna, codigo_comuna, region,
            activo_bhe, porcentaje_retencion_default, certificado_id
        ) VALUES (
            :rut_profesional, :nombres, :apellido_paterno, :apellido_materno, :fecha_nacimiento,
            :profesion, :titulo_profesional, :universidad,
            :telefono, :email, :direccion, :comuna, :codigo_comuna, :region,
            :activo_bhe, :porcentaje_retencion_default, :certificado_id
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'rut_profesional' => $data['rut_profesional'],
            'nombres' => $data['nombres'],
            'apellido_paterno' => $data['apellido_paterno'],
            'apellido_materno' => $data['apellido_materno'] ?? null,
            'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
            'profesion' => $data['profesion'] ?? null,
            'titulo_profesional' => $data['titulo_profesional'] ?? null,
            'universidad' => $data['universidad'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'email' => $data['email'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'comuna' => $data['comuna'] ?? null,
            'codigo_comuna' => $data['codigo_comuna'] ?? null,
            'region' => $data['region'] ?? null,
            'activo_bhe' => $data['activo_bhe'] ?? true,
            'porcentaje_retencion_default' => $data['porcentaje_retencion_default'] ?? 10.00,
            'certificado_id' => $data['certificado_id'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerPorRut(string $rutProfesional): ?array
    {
        $sql = "SELECT p.*, c.nombre as comuna_nombre
                FROM profesionales_bhe p
                LEFT JOIN comunas_chile c ON p.codigo_comuna = c.codigo
                WHERE p.rut_profesional = :rut_profesional AND p.activo_bhe = true";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['rut_profesional' => $rutProfesional]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT p.*, c.nombre as comuna_nombre
                FROM profesionales_bhe p
                LEFT JOIN comunas_chile c ON p.codigo_comuna = c.codigo
                WHERE p.id = :id AND p.activo_bhe = true";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function listar(int $limite = 50, int $offset = 0): array
    {
        $sql = "SELECT p.id, p.rut_profesional, p.nombres, p.apellido_paterno, p.apellido_materno,
                       p.profesion, p.email, p.activo_bhe, p.fecha_registro,
                       c.nombre as comuna_nombre
                FROM profesionales_bhe p
                LEFT JOIN comunas_chile c ON p.codigo_comuna = c.codigo
                WHERE p.activo_bhe = true
                ORDER BY p.apellido_paterno, p.nombres
                LIMIT :limite OFFSET :offset";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    public function actualizar(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = [
            'nombres', 'apellido_paterno', 'apellido_materno', 'fecha_nacimiento',
            'profesion', 'titulo_profesional', 'universidad',
            'telefono', 'email', 'direccion', 'comuna', 'codigo_comuna', 'region',
            'porcentaje_retencion_default', 'certificado_id'
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

        $sql = "UPDATE profesionales_bhe SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute($params);
    }

    public function desactivar(int $id): bool
    {
        $sql = "UPDATE profesionales_bhe SET activo_bhe = false, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute(['id' => $id]);
    }

    public function asociarCertificado(string $rutProfesional, int $certificadoId): bool
    {
        $sql = "UPDATE profesionales_bhe 
                SET certificado_id = :certificado_id, updated_at = CURRENT_TIMESTAMP 
                WHERE rut_profesional = :rut_profesional";
        
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            'certificado_id' => $certificadoId,
            'rut_profesional' => $rutProfesional
        ]);
    }

    public function obtenerCertificado(string $rutProfesional): ?array
    {
        $sql = "SELECT c.* 
                FROM profesionales_bhe p
                INNER JOIN certificados c ON p.certificado_id = c.id
                WHERE p.rut_profesional = :rut_profesional 
                AND p.activo_bhe = true 
                AND c.activo = true";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['rut_profesional' => $rutProfesional]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function validarDatos(array $data): array
    {
        $errores = [];

        // Validar campos requeridos
        $requeridos = ['rut_profesional', 'nombres', 'apellido_paterno'];
        foreach ($requeridos as $campo) {
            if (!isset($data[$campo]) || empty($data[$campo])) {
                $errores[] = "Campo requerido: {$campo}";
            }
        }

        // Validar formato RUT
        if (isset($data['rut_profesional']) && !preg_match('/^\d{7,8}-[\dkK]$/', $data['rut_profesional'])) {
            $errores[] = "Formato de RUT inválido";
        }

        // Validar email si se proporciona
        if (isset($data['email']) && !empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = "Formato de email inválido";
        }

        // Validar fecha de nacimiento
        if (isset($data['fecha_nacimiento']) && !empty($data['fecha_nacimiento'])) {
            $fecha = \DateTime::createFromFormat('Y-m-d', $data['fecha_nacimiento']);
            if (!$fecha) {
                $errores[] = "Formato de fecha de nacimiento inválido (YYYY-MM-DD)";
            } else {
                $hoy = new \DateTime();
                $edad = $hoy->diff($fecha)->y;
                if ($edad < 18 || $edad > 100) {
                    $errores[] = "Edad debe estar entre 18 y 100 años";
                }
            }
        }

        // Validar porcentaje de retención
        if (isset($data['porcentaje_retencion_default'])) {
            $porcentaje = (float) $data['porcentaje_retencion_default'];
            if ($porcentaje < 0 || $porcentaje > 100) {
                $errores[] = "Porcentaje de retención debe estar entre 0 y 100";
            }
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores
        ];
    }

    public function buscar(string $termino, int $limite = 20): array
    {
        $sql = "SELECT p.id, p.rut_profesional, p.nombres, p.apellido_paterno, p.apellido_materno,
                       p.profesion, p.email, c.nombre as comuna_nombre
                FROM profesionales_bhe p
                LEFT JOIN comunas_chile c ON p.codigo_comuna = c.codigo
                WHERE p.activo_bhe = true 
                AND (
                    p.rut_profesional LIKE :termino 
                    OR p.nombres LIKE :termino 
                    OR p.apellido_paterno LIKE :termino 
                    OR p.apellido_materno LIKE :termino
                    OR p.profesion LIKE :termino
                    OR p.email LIKE :termino
                )
                ORDER BY p.apellido_paterno, p.nombres
                LIMIT :limite";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('termino', "%{$termino}%", PDO::PARAM_STR);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    public function verificarRutUnico(string $rutProfesional, int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) FROM profesionales_bhe WHERE rut_profesional = :rut_profesional";
        $params = ['rut_profesional' => $rutProfesional];
        
        if ($excludeId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchColumn() == 0;
    }

    public function obtenerComunas(string $region = null): array
    {
        $sql = "SELECT codigo, nombre, region_nombre FROM comunas_chile WHERE activa = true";
        $params = [];
        
        if ($region) {
            $sql .= " AND region_codigo = :region";
            $params['region'] = $region;
        }
        
        $sql .= " ORDER BY nombre";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }

    public function obtenerEstadisticas(): array
    {
        $sql = "SELECT 
                    COUNT(*) as total_profesionales,
                    COUNT(CASE WHEN certificado_id IS NOT NULL THEN 1 END) as con_certificado,
                    COUNT(CASE WHEN activo_bhe = true THEN 1 END) as activos,
                    AVG(porcentaje_retencion_default) as promedio_retencion
                FROM profesionales_bhe";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch() ?: [];
    }
}
