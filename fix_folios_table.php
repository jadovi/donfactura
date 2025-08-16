<?php
/**
 * Script para corregir la tabla folios agregando columnas faltantes
 */

echo "=== CORRIGIENDO TABLA FOLIOS ===\n";

$pdo = new PDO('mysql:host=localhost;dbname=dte_sistema', 'root', '123123');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "✓ Conectado a la base de datos\n";

// Verificar si las columnas existen
$stmt = $pdo->query("DESCRIBE folios");
$columns = [];
while ($row = $stmt->fetch()) {
    $columns[] = $row['Field'];
}

echo "Columnas actuales: " . implode(', ', $columns) . "\n";

// Agregar columnas faltantes
$columnasFaltantes = [
    'activo' => "ALTER TABLE folios ADD COLUMN activo TINYINT(1) DEFAULT 1",
    'folios_disponibles' => "ALTER TABLE folios ADD COLUMN folios_disponibles INT DEFAULT 0"
];

foreach ($columnasFaltantes as $columna => $sql) {
    if (!in_array($columna, $columns)) {
        echo "\nAgregando columna: {$columna}\n";
        try {
            $pdo->exec($sql);
            echo "✓ Columna {$columna} agregada\n";
        } catch (Exception $e) {
            echo "✗ Error agregando {$columna}: " . $e->getMessage() . "\n";
        }
    } else {
        echo "✓ Columna {$columna} ya existe\n";
    }
}

// Actualizar folios_disponibles para los registros existentes
echo "\nActualizando folios_disponibles...\n";
$pdo->exec("UPDATE folios SET folios_disponibles = (folio_hasta - folio_desde + 1) WHERE folios_disponibles = 0");

// Verificar registros
$stmt = $pdo->query("SELECT * FROM folios WHERE tipo_dte = 41");
$folios = $stmt->fetchAll();

echo "\nFolios BHE después de corrección:\n";
foreach ($folios as $folio) {
    echo "- RUT: {$folio['rut_empresa']}\n";
    echo "  Rango: {$folio['folio_desde']}-{$folio['folio_hasta']}\n";
    echo "  Disponibles: {$folio['folios_disponibles']}\n";
    echo "  Activo: " . ($folio['activo'] ? 'SÍ' : 'NO') . "\n";
    echo "  Vencimiento: {$folio['fecha_vencimiento']}\n\n";
}

echo "✅ TABLA FOLIOS CORREGIDA\n";
?>
