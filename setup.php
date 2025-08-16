<?php
/**
 * Script de configuración inicial para DonFactura DTE
 */

echo "\n=== CONFIGURACIÓN INICIAL DONFACTURA DTE ===\n";

// Cargar autoloader
require 'vendor/autoload.php';

// Configuración de base de datos
$dbConfig = [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'dte_sistema',
    'username' => 'root',
    'password' => '123123'
];

echo "\n1. Verificando conexión a MariaDB...\n";

try {
    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Conexión a MariaDB exitosa\n";
} catch (PDOException $e) {
    die("✗ Error de conexión a MariaDB: " . $e->getMessage() . "\n");
}

echo "\n2. Creando base de datos...\n";

try {
    // Crear base de datos si no existe
    $pdo->exec("CREATE DATABASE IF NOT EXISTS {$dbConfig['database']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Base de datos '{$dbConfig['database']}' creada/verificada\n";
    
    // Seleccionar base de datos
    $pdo->exec("USE {$dbConfig['database']}");
    
} catch (PDOException $e) {
    die("✗ Error creando base de datos: " . $e->getMessage() . "\n");
}

echo "\n3. Creando tablas...\n";

$sqlFile = 'database/create_database.sql';
if (!file_exists($sqlFile)) {
    die("✗ No se encontró el archivo SQL: {$sqlFile}\n");
}

$sql = file_get_contents($sqlFile);

// Dividir en statements individuales
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function($stmt) {
        return !empty($stmt) && !str_starts_with($stmt, '--') && !str_starts_with($stmt, 'CREATE DATABASE');
    }
);

$tablesCreated = 0;
foreach ($statements as $statement) {
    try {
        if (trim($statement)) {
            $pdo->exec($statement);
            
            // Contar tablas creadas
            if (stripos($statement, 'CREATE TABLE') !== false) {
                $tablesCreated++;
                
                // Extraer nombre de tabla
                if (preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?([a-zA-Z_]+)`?/i', $statement, $matches)) {
                    echo "  ✓ Tabla '{$matches[1]}' creada\n";
                }
            }
        }
    } catch (PDOException $e) {
        // Ignorar errores de tablas que ya existen
        if (strpos($e->getMessage(), 'already exists') === false) {
            echo "  ✗ Error ejecutando statement: " . $e->getMessage() . "\n";
        }
    }
}

echo "✓ {$tablesCreated} tablas procesadas\n";

echo "\n4. Verificando estructura de base de datos...\n";

try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $expectedTables = [
        'certificados',
        'folios',
        'folios_utilizados',
        'documentos_dte',
        'dte_detalles',
        'dte_referencias',
        'sii_transacciones',
        'boletas_electronicas'
    ];
    
    $foundTables = [];
    foreach ($expectedTables as $expectedTable) {
        if (in_array($expectedTable, $tables)) {
            $foundTables[] = $expectedTable;
            echo "  ✓ {$expectedTable}\n";
        } else {
            echo "  ✗ {$expectedTable} - NO ENCONTRADA\n";
        }
    }
    
    echo "\n✓ Base de datos configurada: " . count($foundTables) . "/" . count($expectedTables) . " tablas\n";
    
} catch (PDOException $e) {
    echo "✗ Error verificando tablas: " . $e->getMessage() . "\n";
}

echo "\n5. Creando datos de ejemplo...\n";

try {
    // Verificar si ya hay datos
    $stmt = $pdo->query("SELECT COUNT(*) FROM documentos_dte");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $pdo->exec("INSERT INTO documentos_dte (tipo_dte, folio, fecha_emision, rut_emisor, razon_social_emisor, rut_receptor, razon_social_receptor, monto_total, estado) VALUES (33, 0, '2024-01-01', '11111111-1', 'EMPRESA EJEMPLO LTDA', '22222222-2', 'CLIENTE EJEMPLO S.A.', 119000.00, 'borrador')");
        echo "✓ Datos de ejemplo insertados\n";
    } else {
        echo "✓ Ya existen datos en la base de datos ({$count} documentos)\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Error insertando datos de ejemplo: " . $e->getMessage() . "\n";
}

echo "\n6. Verificando directorios...\n";

$directories = [
    'storage',
    'storage/certificates',
    'storage/temp',
    'storage/generated',
    'storage/logs'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "  ✓ Creado: {$dir}\n";
        } else {
            echo "  ✗ Error creando: {$dir}\n";
        }
    } else {
        echo "  ✓ Existe: {$dir}\n";
    }
}

echo "\n=== CONFIGURACIÓN COMPLETADA ===\n";
echo "\nPróximos pasos:\n";
echo "1. Iniciar servidor: cd public && php -S localhost:8000 index_simple.php\n";
echo "2. Visitar: http://localhost:8000\n";
echo "3. Probar health check: http://localhost:8000/health\n";
echo "4. Test de BD: http://localhost:8000/api/test-db\n";
echo "\nPara funcionalidad completa:\n";
echo "1. Instalar Composer: https://getcomposer.org/\n";
echo "2. Ejecutar: composer install\n";
echo "3. Usar: public/index.php\n";

echo "\n🚀 ¡Sistema DTE listo para usar!\n\n";
?>
