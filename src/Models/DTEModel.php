<?php

declare(strict_types=1);

namespace DonFactura\DTE\Models;

use PDO;

/**
 * Modelo para documentos tributarios electrónicos
 */
class DTEModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function crear(array $data): int
    {
        $sql = "INSERT INTO documentos_dte (
            tipo_dte, folio, fecha_emision,
            rut_emisor, razon_social_emisor, giro_emisor, direccion_emisor, comuna_emisor, ciudad_emisor,
            rut_receptor, razon_social_receptor, giro_receptor, direccion_receptor, comuna_receptor, ciudad_receptor,
            monto_neto, monto_iva, monto_total,
            xml_dte, estado, observaciones
        ) VALUES (
            :tipo_dte, :folio, :fecha_emision,
            :rut_emisor, :razon_social_emisor, :giro_emisor, :direccion_emisor, :comuna_emisor, :ciudad_emisor,
            :rut_receptor, :razon_social_receptor, :giro_receptor, :direccion_receptor, :comuna_receptor, :ciudad_receptor,
            :monto_neto, :monto_iva, :monto_total,
            :xml_dte, :estado, :observaciones
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tipo_dte' => $data['tipo_dte'],
            'folio' => $data['folio'],
            'fecha_emision' => $data['fecha_emision'],
            'rut_emisor' => $data['rut_emisor'],
            'razon_social_emisor' => $data['razon_social_emisor'],
            'giro_emisor' => $data['giro_emisor'] ?? null,
            'direccion_emisor' => $data['direccion_emisor'] ?? null,
            'comuna_emisor' => $data['comuna_emisor'] ?? null,
            'ciudad_emisor' => $data['ciudad_emisor'] ?? null,
            'rut_receptor' => $data['rut_receptor'],
            'razon_social_receptor' => $data['razon_social_receptor'],
            'giro_receptor' => $data['giro_receptor'] ?? null,
            'direccion_receptor' => $data['direccion_receptor'] ?? null,
            'comuna_receptor' => $data['comuna_receptor'] ?? null,
            'ciudad_receptor' => $data['ciudad_receptor'] ?? null,
            'monto_neto' => $data['monto_neto'] ?? 0,
            'monto_iva' => $data['monto_iva'] ?? 0,
            'monto_total' => $data['monto_total'],
            'xml_dte' => $data['xml_dte'] ?? null,
            'estado' => $data['estado'] ?? 'borrador',
            'observaciones' => $data['observaciones'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT * FROM documentos_dte WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function obtenerPorTipoYFolio(int $tipo, int $folio, string $rutEmisor): ?array
    {
        $sql = "SELECT * FROM documentos_dte WHERE tipo_dte = :tipo AND folio = :folio AND rut_emisor = :rut_emisor";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'tipo' => $tipo,
            'folio' => $folio,
            'rut_emisor' => $rutEmisor
        ]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function listarPorTipo(int $tipo, int $limite = 50, int $offset = 0): array
    {
        $sql = "SELECT * FROM documentos_dte WHERE tipo_dte = :tipo ORDER BY created_at DESC LIMIT :limite OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('tipo', $tipo, PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    public function actualizar(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];

        foreach ($data as $field => $value) {
            if (in_array($field, [
                'xml_dte', 'xml_firmado', 'estado', 'observaciones',
                'monto_neto', 'monto_iva', 'monto_total'
            ])) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE documentos_dte SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        
        return $stmt->execute($params);
    }

    public function agregarDetalle(int $dteId, array $detalle): int
    {
        $sql = "INSERT INTO dte_detalles (
            dte_id, numero_linea, codigo_item, nombre_item, descripcion,
            cantidad, unidad_medida, precio_unitario, descuento_porcentaje, descuento_monto,
            monto_bruto, monto_neto, indica_exento
        ) VALUES (
            :dte_id, :numero_linea, :codigo_item, :nombre_item, :descripcion,
            :cantidad, :unidad_medida, :precio_unitario, :descuento_porcentaje, :descuento_monto,
            :monto_bruto, :monto_neto, :indica_exento
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'dte_id' => $dteId,
            'numero_linea' => $detalle['numero_linea'],
            'codigo_item' => $detalle['codigo_item'] ?? null,
            'nombre_item' => $detalle['nombre_item'],
            'descripcion' => $detalle['descripcion'] ?? null,
            'cantidad' => $detalle['cantidad'] ?? 1,
            'unidad_medida' => $detalle['unidad_medida'] ?? 'UN',
            'precio_unitario' => $detalle['precio_unitario'],
            'descuento_porcentaje' => $detalle['descuento_porcentaje'] ?? 0,
            'descuento_monto' => $detalle['descuento_monto'] ?? 0,
            'monto_bruto' => $detalle['monto_bruto'],
            'monto_neto' => $detalle['monto_neto'],
            'indica_exento' => $detalle['indica_exento'] ?? false,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerDetalles(int $dteId): array
    {
        $sql = "SELECT * FROM dte_detalles WHERE dte_id = :dte_id ORDER BY numero_linea";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['dte_id' => $dteId]);
        
        return $stmt->fetchAll();
    }

    public function agregarReferencia(int $dteId, array $referencia): int
    {
        $sql = "INSERT INTO dte_referencias (
            dte_id, numero_linea, tipo_documento, folio_referencia,
            fecha_referencia, codigo_referencia, razon_referencia
        ) VALUES (
            :dte_id, :numero_linea, :tipo_documento, :folio_referencia,
            :fecha_referencia, :codigo_referencia, :razon_referencia
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'dte_id' => $dteId,
            'numero_linea' => $referencia['numero_linea'],
            'tipo_documento' => $referencia['tipo_documento'],
            'folio_referencia' => $referencia['folio_referencia'],
            'fecha_referencia' => $referencia['fecha_referencia'],
            'codigo_referencia' => $referencia['codigo_referencia'],
            'razon_referencia' => $referencia['razon_referencia'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function obtenerReferencias(int $dteId): array
    {
        $sql = "SELECT * FROM dte_referencias WHERE dte_id = :dte_id ORDER BY numero_linea";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['dte_id' => $dteId]);
        
        return $stmt->fetchAll();
    }
}
