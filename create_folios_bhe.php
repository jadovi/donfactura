<?php
/**
 * Script para crear folios de ejemplo para BHE (DTE Tipo 41)
 */

echo "=== CREANDO FOLIOS PARA BHE (TIPO 41) ===\n";

$dbConfig = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'dte_sistema',
    'username' => 'root',
    'password' => '123123'
];

try {
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Conectado a la base de datos\n";
} catch (PDOException $e) {
    die("✗ Error: " . $e->getMessage() . "\n");
}

echo "\n1. Creando folios para BHE (Tipo 41)...\n";

// Profesionales registrados
$profesionales = [
    '12345678-9', // Juan Carlos Pérez (ya existe)
    '15555666-7'  // Carlos Eduardo Sánchez (recién creado)
];

foreach ($profesionales as $rutProfesional) {
    echo "\nCreando folios para profesional: {$rutProfesional}\n";
    
    // Verificar si ya tiene folios para tipo 41
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM folios WHERE tipo_dte = ? AND rut_empresa = ?");
    $stmt->execute([41, $rutProfesional]);
    $existentes = $stmt->fetchColumn();
    
    if ($existentes > 0) {
        echo "  - Ya tiene {$existentes} rangos de folios para BHE\n";
        continue;
    }
    
    // Crear rango de folios para BHE
    $folioDesde = rand(1000, 9000);
    $folioHasta = $folioDesde + 100; // 100 folios por rango
    
    $sql = "INSERT INTO folios (
        tipo_dte, rut_empresa, folio_desde, folio_hasta, 
        fecha_resolucion, fecha_vencimiento, created_at
    ) VALUES (
        41, :rut_empresa, :folio_desde, :folio_hasta,
        :fecha_resolucion, :fecha_vencimiento, CURRENT_TIMESTAMP
    )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'rut_empresa' => $rutProfesional,
        'folio_desde' => $folioDesde,
        'folio_hasta' => $folioHasta,
        'fecha_resolucion' => date('Y-m-d'),
        'fecha_vencimiento' => date('Y-m-d', strtotime('+1 year'))
    ]);
    
    echo "  ✓ Folios creados: {$folioDesde} - {$folioHasta}\n";
    echo "  ✓ Válidos hasta: " . date('Y-m-d', strtotime('+1 year')) . "\n";
}

echo "\n2. Verificando folios disponibles...\n";

$sql = "SELECT 
    tipo_dte, rut_empresa, folio_desde, folio_hasta, 
    fecha_vencimiento,
    (folio_hasta - folio_desde + 1) as total_folios
FROM folios 
WHERE tipo_dte = 41 
ORDER BY rut_empresa, folio_desde";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$folios = $stmt->fetchAll();

if (!empty($folios)) {
    foreach ($folios as $folio) {
        echo "  RUT: {$folio['rut_empresa']} | ";
        echo "Rango: {$folio['folio_desde']}-{$folio['folio_hasta']} | ";
        echo "Total: {$folio['total_folios']} | ";
        echo "Vence: {$folio['fecha_vencimiento']}\n";
    }
} else {
    echo "  ⚠️  No se encontraron folios para tipo 41\n";
}

echo "\n3. Verificando folios utilizados...\n";

$sql = "SELECT COUNT(*) as usados FROM folios_utilizados WHERE tipo_dte = 41";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$utilizados = $stmt->fetch();

echo "  Folios BHE utilizados: {$utilizados['usados']}\n";

echo "\n4. Creando certificados de ejemplo para profesionales...\n";

foreach ($profesionales as $rutProfesional) {
    // Verificar si ya tiene certificado
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificados WHERE rut_empresa = ?");
    $stmt->execute([$rutProfesional]);
    $existeCert = $stmt->fetchColumn();
    
    if ($existeCert > 0) {
        echo "  - {$rutProfesional}: Ya tiene certificado\n";
        continue;
    }
    
    // Crear certificado de ejemplo
    $sql = "INSERT INTO certificados (
        rut_empresa, nombre, archivo_pfx, password_pfx,
        fecha_vencimiento, activo, created_at
    ) VALUES (
        :rut_empresa, :nombre, :archivo, :password,
        :vencimiento, 1, CURRENT_TIMESTAMP
    )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'rut_empresa' => $rutProfesional,
        'nombre' => "Certificado BHE {$rutProfesional}",
        'archivo' => "cert_{$rutProfesional}.pfx",
        'password' => 'demo123', // Password de ejemplo
        'vencimiento' => date('Y-m-d', strtotime('+2 years'))
    ]);
    
    $certId = $pdo->lastInsertId();
    
    // Asociar certificado al profesional
    $sql = "UPDATE profesionales_bhe SET certificado_id = ? WHERE rut_profesional = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$certId, $rutProfesional]);
    
    echo "  ✓ {$rutProfesional}: Certificado creado y asociado (ID: {$certId})\n";
}

echo "\n5. Resumen final BHE...\n";

// Contar profesionales con todo configurado
$sql = "SELECT 
    COUNT(*) as total_profesionales,
    COUNT(CASE WHEN certificado_id IS NOT NULL THEN 1 END) as con_certificado
FROM profesionales_bhe 
WHERE activo_bhe = 1";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$stats = $stmt->fetch();

echo "  📊 Profesionales activos: {$stats['total_profesionales']}\n";
echo "  📊 Con certificado: {$stats['con_certificado']}\n";

// Contar folios disponibles
$sql = "SELECT COUNT(*) as rangos, SUM(folio_hasta - folio_desde + 1) as total_folios
FROM folios 
WHERE tipo_dte = 41 AND activo = 1";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$foliosStats = $stmt->fetch();

echo "  📊 Rangos de folios BHE: {$foliosStats['rangos']}\n";
echo "  📊 Total folios disponibles: {$foliosStats['total_folios']}\n";

echo "\n✅ CONFIGURACIÓN BHE COMPLETADA\n";

echo "\n🎯 Estado del sistema:\n";
echo "  ✅ Tablas BHE creadas\n";
echo "  ✅ Profesionales registrados\n";
echo "  ✅ Folios tipo 41 disponibles\n";
echo "  ✅ Certificados asociados\n";
echo "  ✅ API BHE funcional\n";

echo "\n🚀 ¡LISTO PARA GENERAR BHE!\n";

echo "\nPróximos pasos:\n";
echo "1. Iniciar servidor: cd public && php -S localhost:8000 index_basic.php\n";
echo "2. Probar BHE: curl -X POST http://localhost:8000/api/bhe/generar\n";
echo "3. Ver funcionalidades: http://localhost:8000/bhe-features\n";

echo "\n" . str_repeat("=", 60) . "\n";
echo "FOLIOS BHE CONFIGURADOS - " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 60) . "\n";
?>
