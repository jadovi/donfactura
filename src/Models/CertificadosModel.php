<?php

declare(strict_types=1);

namespace DonFactura\DTE\Models;

use PDO;

/**
 * Modelo para gestión de certificados digitales
 */
class CertificadosModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function crear(array $data): int
    {
        $sql = "INSERT INTO certificados (
            nombre, archivo_pfx, password_pfx, rut_empresa, 
            razon_social, fecha_vencimiento, activo
        ) VALUES (
            :nombre, :archivo_pfx, :password_pfx, :rut_empresa,
            :razon_social, :fecha_vencimiento, :activo
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'nombre' => $data['nombre'],
            'archivo_pfx' => $data['archivo_pfx'],
            'password_pfx' => $data['password_pfx'],
            'rut_empresa' => $data['rut_empresa'],
            'razon_social' => $data['razon_social'],
            'fecha_vencimiento' => $data['fecha_vencimiento'],
            'activo' => $data['activo'] ?? true,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT * FROM certificados WHERE id = :id AND activo = true";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function obtenerPorRutEmpresa(string $rutEmpresa): ?array
    {
        $sql = "SELECT * FROM certificados 
                WHERE rut_empresa = :rut_empresa 
                AND activo = true 
                AND fecha_vencimiento > CURDATE()
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['rut_empresa' => $rutEmpresa]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function listar(): array
    {
        $sql = "SELECT id, nombre, rut_empresa, razon_social, fecha_vencimiento, activo, created_at 
                FROM certificados 
                WHERE activo = true 
                ORDER BY created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    public function actualizar(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach ($data as $field => $value) {
            if (in_array($field, [
                'nombre', 'password_pfx', 'fecha_vencimiento', 'activo'
            ])) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE certificados SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute($params);
    }

    public function eliminar(int $id): bool
    {
        $sql = "UPDATE certificados SET activo = false WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute(['id' => $id]);
    }

    public function validarCertificado(string $archivoPfx, string $password): array
    {
        $resultado = [
            'valido' => false,
            'info' => null,
            'error' => null
        ];

        try {
            // Intentar leer el certificado PFX
            $certs = [];
            if (openssl_pkcs12_read($archivoPfx, $certs, $password)) {
                // Obtener información del certificado
                $certInfo = openssl_x509_parse($certs['cert']);
                
                if ($certInfo) {
                    $resultado['valido'] = true;
                    $resultado['info'] = [
                        'subject' => $certInfo['subject'],
                        'issuer' => $certInfo['issuer'],
                        'valid_from' => date('Y-m-d H:i:s', $certInfo['validFrom_time_t']),
                        'valid_to' => date('Y-m-d H:i:s', $certInfo['validTo_time_t']),
                        'serial_number' => $certInfo['serialNumber'],
                    ];

                    // Extraer RUT si está disponible en el certificado
                    if (isset($certInfo['subject']['serialNumber'])) {
                        $resultado['info']['rut'] = $certInfo['subject']['serialNumber'];
                    }

                    // Verificar si el certificado no ha vencido
                    if ($certInfo['validTo_time_t'] < time()) {
                        $resultado['error'] = 'El certificado ha vencido';
                        $resultado['valido'] = false;
                    }
                } else {
                    $resultado['error'] = 'No se pudo analizar el certificado';
                }
            } else {
                $resultado['error'] = 'Password incorrecto o archivo PFX inválido';
            }
        } catch (\Exception $e) {
            $resultado['error'] = 'Error al procesar el certificado: ' . $e->getMessage();
        }

        return $resultado;
    }

    public function obtenerCertificadoParaFirma(string $rutEmpresa): ?array
    {
        $certificado = $this->obtenerPorRutEmpresa($rutEmpresa);
        
        if (!$certificado) {
            return null;
        }

        // Validar que el certificado esté vigente y funcional
        $validacion = $this->validarCertificado(
            $certificado['archivo_pfx'], 
            $certificado['password_pfx']
        );

        if (!$validacion['valido']) {
            return null;
        }

        return $certificado;
    }
}
