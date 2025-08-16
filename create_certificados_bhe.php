<?php
/**
 * Script para crear certificados para profesionales BHE
 */

echo "=== CREANDO CERTIFICADOS PARA PROFESIONALES BHE ===\n";

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

// Obtener profesionales registrados
$stmt = $pdo->query("SELECT rut_profesional, nombres, apellido_paterno FROM profesionales_bhe WHERE activo_bhe = 1");
$profesionales = $stmt->fetchAll();

echo "\nProfesionales encontrados: " . count($profesionales) . "\n";

foreach ($profesionales as $prof) {
    $rutProfesional = $prof['rut_profesional'];
    $nombreCompleto = $prof['nombres'] . ' ' . $prof['apellido_paterno'];
    
    echo "\nProcesando: {$nombreCompleto} ({$rutProfesional})\n";
    
    // Verificar si ya tiene certificado
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM certificados WHERE rut_empresa = ?");
    $stmt->execute([$rutProfesional]);
    $existeCert = $stmt->fetchColumn();
    
    if ($existeCert > 0) {
        echo "  - Ya tiene certificado\n";
        continue;
    }
    
    // Crear certificado de ejemplo
    $sql = "INSERT INTO certificados (
        rut_empresa, nombre, razon_social, 
        archivo_pfx, password_pfx,
        fecha_vencimiento, activo, created_at
    ) VALUES (
        :rut_empresa, :nombre, :razon_social,
        :archivo, :password,
        :vencimiento, 1, CURRENT_TIMESTAMP
    )";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'rut_empresa' => $rutProfesional,
        'nombre' => "Certificado Digital BHE - {$nombreCompleto}",
        'razon_social' => $nombreCompleto,
        'archivo' => "cert_bhe_{$rutProfesional}.pfx", // Simulado
        'password' => 'demo123456', // Password de ejemplo
        'vencimiento' => date('Y-m-d', strtotime('+2 years'))
    ]);
    
    $certId = $pdo->lastInsertId();
    
    // Asociar certificado al profesional
    $sql = "UPDATE profesionales_bhe SET certificado_id = ? WHERE rut_profesional = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$certId, $rutProfesional]);
    
    echo "  ✓ Certificado creado y asociado (ID: {$certId})\n";
    echo "  ✓ Válido hasta: " . date('Y-m-d', strtotime('+2 years')) . "\n";
}

echo "\n=== RESUMEN FINAL ===\n";

// Verificar estado final
$stmt = $pdo->query("
    SELECT 
        p.rut_profesional,
        p.nombres,
        p.apellido_paterno,
        p.certificado_id,
        c.nombre as cert_nombre,
        (SELECT COUNT(*) FROM folios WHERE tipo_dte = 41 AND rut_empresa = p.rut_profesional) as folios_count
    FROM profesionales_bhe p
    LEFT JOIN certificados c ON p.certificado_id = c.id
    WHERE p.activo_bhe = 1
");

$resumen = $stmt->fetchAll();

echo "Estado de profesionales BHE:\n";
foreach ($resumen as $prof) {
    $status = $prof['certificado_id'] ? '✅' : '❌';
    $folios = $prof['folios_count'] > 0 ? '✅' : '❌';
    
    echo "  {$prof['nombres']} {$prof['apellido_paterno']} ({$prof['rut_profesional']})\n";
    echo "    Certificado: {$status}\n";
    echo "    Folios: {$folios}\n";
}

echo "\n🎉 ¡CERTIFICADOS CONFIGURADOS!\n";

echo "\nPróximos pasos:\n";
echo "1. Iniciar servidor API\n";
echo "2. Probar generación de BHE\n";
echo "3. Verificar firma electrónica\n";

echo "\n" . str_repeat("=", 50) . "\n";
echo "CERTIFICADOS BHE COMPLETADOS\n";
echo str_repeat("=", 50) . "\n";
?>
