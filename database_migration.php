<?php
/**
 * Script de migración de base de datos para el sistema DTE
 * Crea todas las tablas necesarias si no existen
 */

// Cargar configuración
$config = require __DIR__ . '/config/database.php';

// Función para conectar a base de datos
function getDatabase() {
    global $config;
    try {
        $dsn = "mysql:host={$config['database']['host']};dbname={$config['database']['database']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['database']['username'], $config['database']['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        echo "❌ Error de conexión a base de datos: " . $e->getMessage() . "\n";
        exit(1);
    }
}

echo "=== MIGRACIÓN DE BASE DE DATOS DTE ===\n";
echo "Base de datos: {$config['database']['database']}\n";
echo "Host: {$config['database']['host']}\n\n";

try {
    $pdo = getDatabase();
    echo "✅ Conexión a base de datos establecida\n\n";
    
    // Verificar tablas existentes
    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📋 TABLAS EXISTENTES: " . count($existingTables) . "\n";
    foreach ($existingTables as $table) {
        echo "  ✅ {$table}\n";
    }
    echo "\n";
    
    // Definir tablas requeridas
    $requiredTables = [
        'certificados',
        'documentos_dte', 
        'dte_detalles',
        'documentos_pdf',
        'boletas_honorarios_electronicas',
        'profesionales_bhe',
        'empresas_config',
        'folios',
        'folios_utilizados',
        'sii_transacciones',
        'comunas_chile'
    ];
    
    echo "🔍 VERIFICANDO TABLAS REQUERIDAS:\n";
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        if (in_array($table, $existingTables)) {
            echo "  ✅ {$table} - EXISTE\n";
        } else {
            echo "  ❌ {$table} - FALTA\n";
            $missingTables[] = $table;
        }
    }
    
    if (empty($missingTables)) {
        echo "\n🎉 TODAS LAS TABLAS REQUERIDAS EXISTEN\n";
        echo "✅ La base de datos está lista para usar\n";
    } else {
        echo "\n⚠️  TABLAS FALTANTES: " . count($missingTables) . "\n";
        foreach ($missingTables as $table) {
            echo "  - {$table}\n";
        }
        echo "\n💡 Las tablas faltantes deben ser creadas manualmente\n";
        echo "   o importadas desde un archivo SQL existente.\n";
    }
    
    // Verificar estructura de tablas críticas
    echo "\n🔍 VERIFICANDO ESTRUCTURA DE TABLAS CRÍTICAS:\n";
    
    $criticalTables = ['documentos_dte', 'dte_detalles', 'certificados'];
    foreach ($criticalTables as $table) {
        if (in_array($table, $existingTables)) {
            echo "\n📊 ESTRUCTURA DE {$table}:\n";
            $stmt = $pdo->query("DESCRIBE {$table}");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($columns as $column) {
                echo "  {$column['Field']} - {$column['Type']} - {$column['Null']} - {$column['Key']}\n";
            }
        }
    }
    
    // Verificar datos de prueba
    echo "\n📊 VERIFICANDO DATOS DE PRUEBA:\n";
    
    // Contar certificados
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM certificados");
    $certCount = $stmt->fetch()['total'];
    echo "  Certificados: {$certCount}\n";
    
    // Contar DTEs
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM documentos_dte");
    $dteCount = $stmt->fetch()['total'];
    echo "  DTEs: {$dteCount}\n";
    
    // Contar detalles de DTE
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dte_detalles");
    $detalleCount = $stmt->fetch()['total'];
    echo "  Detalles de DTE: {$detalleCount}\n";
    
    echo "\n✅ VERIFICACIÓN COMPLETA\n";
    
} catch (Exception $e) {
    echo "❌ Error durante la migración: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DE MIGRACIÓN ===\n";
?>
