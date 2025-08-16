<?php

declare(strict_types=1);

namespace DonFactura\DTE\Models;

use PDO;

/**
 * Modelo para configuración de empresas y personalización PDF
 */
class EmpresasConfigModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function crear(array $data): int
    {
        $sql = "INSERT INTO empresas_config (
            rut_empresa, razon_social, nombre_fantasia, giro,
            direccion, comuna, ciudad, region, telefono, email, website,
            logo_empresa, logo_nombre, logo_tipo,
            formato_carta, formato_80mm, color_primario, color_secundario,
            margen_superior, margen_inferior, margen_izquierdo, margen_derecho
        ) VALUES (
            :rut_empresa, :razon_social, :nombre_fantasia, :giro,
            :direccion, :comuna, :ciudad, :region, :telefono, :email, :website,
            :logo_empresa, :logo_nombre, :logo_tipo,
            :formato_carta, :formato_80mm, :color_primario, :color_secundario,
            :margen_superior, :margen_inferior, :margen_izquierdo, :margen_derecho
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'rut_empresa' => $data['rut_empresa'],
            'razon_social' => $data['razon_social'],
            'nombre_fantasia' => $data['nombre_fantasia'] ?? null,
            'giro' => $data['giro'] ?? null,
            'direccion' => $data['direccion'] ?? null,
            'comuna' => $data['comuna'] ?? null,
            'ciudad' => $data['ciudad'] ?? null,
            'region' => $data['region'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'email' => $data['email'] ?? null,
            'website' => $data['website'] ?? null,
            'logo_empresa' => $data['logo_empresa'] ?? null,
            'logo_nombre' => $data['logo_nombre'] ?? null,
            'logo_tipo' => $data['logo_tipo'] ?? null,
            'formato_carta' => $data['formato_carta'] ?? true,
            'formato_80mm' => $data['formato_80mm'] ?? true,
            'color_primario' => $data['color_primario'] ?? '#000000',
            'color_secundario' => $data['color_secundario'] ?? '#666666',
            'margen_superior' => $data['margen_superior'] ?? 20.00,
            'margen_inferior' => $data['margen_inferior'] ?? 20.00,
            'margen_izquierdo' => $data['margen_izquierdo'] ?? 20.00,
            'margen_derecho' => $data['margen_derecho'] ?? 20.00,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerPorRut(string $rutEmpresa): ?array
    {
        $sql = "SELECT * FROM empresas_config WHERE rut_empresa = :rut_empresa AND activo = true";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['rut_empresa' => $rutEmpresa]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT * FROM empresas_config WHERE id = :id AND activo = true";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function listar(): array
    {
        $sql = "SELECT id, rut_empresa, razon_social, nombre_fantasia, ciudad, activo, created_at 
                FROM empresas_config 
                WHERE activo = true 
                ORDER BY razon_social";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    public function actualizar(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = [
            'razon_social', 'nombre_fantasia', 'giro', 'direccion', 'comuna', 'ciudad', 'region',
            'telefono', 'email', 'website', 'logo_empresa', 'logo_nombre', 'logo_tipo',
            'formato_carta', 'formato_80mm', 'color_primario', 'color_secundario',
            'margen_superior', 'margen_inferior', 'margen_izquierdo', 'margen_derecho'
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

        $sql = "UPDATE empresas_config SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute($params);
    }

    public function subirLogo(int $id, string $logoContent, string $nombreArchivo, string $tipoMime): bool
    {
        $sql = "UPDATE empresas_config 
                SET logo_empresa = :logo_empresa, 
                    logo_nombre = :logo_nombre, 
                    logo_tipo = :logo_tipo,
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            'id' => $id,
            'logo_empresa' => $logoContent,
            'logo_nombre' => $nombreArchivo,
            'logo_tipo' => $tipoMime
        ]);
    }

    public function eliminarLogo(int $id): bool
    {
        $sql = "UPDATE empresas_config 
                SET logo_empresa = NULL, 
                    logo_nombre = NULL, 
                    logo_tipo = NULL,
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute(['id' => $id]);
    }

    public function configurarFormatos(int $id, bool $formatoCarta, bool $formato80mm): bool
    {
        $sql = "UPDATE empresas_config 
                SET formato_carta = :formato_carta, 
                    formato_80mm = :formato_80mm,
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            'id' => $id,
            'formato_carta' => $formatoCarta,
            'formato_80mm' => $formato80mm
        ]);
    }

    public function configurarColores(int $id, string $colorPrimario, string $colorSecundario): bool
    {
        // Validar formato de colores hexadecimales
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $colorPrimario) || 
            !preg_match('/^#[0-9A-Fa-f]{6}$/', $colorSecundario)) {
            return false;
        }

        $sql = "UPDATE empresas_config 
                SET color_primario = :color_primario, 
                    color_secundario = :color_secundario,
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            'id' => $id,
            'color_primario' => $colorPrimario,
            'color_secundario' => $colorSecundario
        ]);
    }

    public function configurarMargenes(int $id, array $margenes): bool
    {
        $sql = "UPDATE empresas_config 
                SET margen_superior = :margen_superior,
                    margen_inferior = :margen_inferior,
                    margen_izquierdo = :margen_izquierdo,
                    margen_derecho = :margen_derecho,
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id";
        
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute([
            'id' => $id,
            'margen_superior' => $margenes['superior'] ?? 20.00,
            'margen_inferior' => $margenes['inferior'] ?? 20.00,
            'margen_izquierdo' => $margenes['izquierdo'] ?? 20.00,
            'margen_derecho' => $margenes['derecho'] ?? 20.00
        ]);
    }

    public function eliminar(int $id): bool
    {
        $sql = "UPDATE empresas_config SET activo = false, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute(['id' => $id]);
    }

    public function validarLogo(string $logoContent, string $tipoMime): array
    {
        $result = [
            'valido' => false,
            'error' => null,
            'info' => null
        ];

        // Validar tipo MIME
        $tiposPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($tipoMime, $tiposPermitidos)) {
            $result['error'] = 'Tipo de archivo no permitido. Use JPG, PNG, GIF o WebP.';
            return $result;
        }

        // Validar tamaño
        $tamaño = strlen($logoContent);
        $maxTamaño = 5 * 1024 * 1024; // 5MB
        if ($tamaño > $maxTamaño) {
            $result['error'] = 'El archivo es muy grande. Máximo 5MB.';
            return $result;
        }

        // Validar que sea una imagen válida
        $imageInfo = getimagesizefromstring($logoContent);
        if ($imageInfo === false) {
            $result['error'] = 'Archivo no es una imagen válida.';
            return $result;
        }

        $result['valido'] = true;
        $result['info'] = [
            'ancho' => $imageInfo[0],
            'alto' => $imageInfo[1],
            'tipo' => $imageInfo['mime'],
            'tamaño' => $tamaño
        ];

        return $result;
    }
}
