<?php
$pdo = new PDO('mysql:host=localhost;dbname=dte_sistema', 'root', '123123');

echo "Verificando tablas del sistema:\n";

$tables = ['folios', 'folios_utilizados', 'certificados', 'profesionales_bhe'];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM {$table}");
        $count = $stmt->fetchColumn();
        echo "✓ {$table}: {$count} registros\n";
    } catch (Exception $e) {
        echo "✗ {$table}: " . $e->getMessage() . "\n";
    }
}

// Verificar folios para tipo 41
echo "\nFolios BHE (tipo 41):\n";
$stmt = $pdo->query("SELECT rut_empresa, folio_desde, folio_hasta FROM folios WHERE tipo_dte = 41");
while ($row = $stmt->fetch()) {
    echo "- {$row['rut_empresa']}: {$row['folio_desde']}-{$row['folio_hasta']}\n";
}
?>
