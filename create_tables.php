<?php
/**
 * Script para crear las tablas de la base de datos directamente
 */

echo "=== CREANDO TABLAS DTE SISTEMA ===\n";

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

// Definir las tablas
$tables = [
    'certificados' => "
        CREATE TABLE IF NOT EXISTS certificados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(255) NOT NULL,
            archivo_pfx LONGBLOB NOT NULL,
            password_pfx VARCHAR(255) NOT NULL,
            rut_empresa VARCHAR(12) NOT NULL,
            razon_social VARCHAR(255) NOT NULL,
            fecha_vencimiento DATE NOT NULL,
            activo BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
    
    'folios' => "
        CREATE TABLE IF NOT EXISTS folios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo_dte INT NOT NULL,
            rut_empresa VARCHAR(12) NOT NULL,
            folio_desde INT NOT NULL,
            folio_hasta INT NOT NULL,
            fecha_resolucion DATE NOT NULL,
            fecha_vencimiento DATE NOT NULL,
            xml_caf TEXT NOT NULL,
            folios_disponibles INT NOT NULL,
            activo BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_tipo_empresa (tipo_dte, rut_empresa),
            INDEX idx_folios_range (folio_desde, folio_hasta)
        )",
    
    'folios_utilizados' => "
        CREATE TABLE IF NOT EXISTS folios_utilizados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            folio_caf_id INT NOT NULL,
            folio_numero INT NOT NULL,
            fecha_utilizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            dte_id INT NULL,
            INDEX idx_folio_caf (folio_caf_id),
            UNIQUE KEY unique_folio_caf (folio_caf_id, folio_numero)
        )",
    
    'documentos_dte' => "
        CREATE TABLE IF NOT EXISTS documentos_dte (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tipo_dte INT NOT NULL,
            folio INT NOT NULL,
            fecha_emision DATE NOT NULL,
            
            rut_emisor VARCHAR(12) NOT NULL,
            razon_social_emisor VARCHAR(255) NOT NULL,
            giro_emisor VARCHAR(255),
            direccion_emisor VARCHAR(255),
            comuna_emisor VARCHAR(100),
            ciudad_emisor VARCHAR(100),
            
            rut_receptor VARCHAR(12) NOT NULL,
            razon_social_receptor VARCHAR(255) NOT NULL,
            giro_receptor VARCHAR(255),
            direccion_receptor VARCHAR(255),
            comuna_receptor VARCHAR(100),
            ciudad_receptor VARCHAR(100),
            
            monto_neto DECIMAL(15,2) DEFAULT 0,
            monto_iva DECIMAL(15,2) DEFAULT 0,
            monto_total DECIMAL(15,2) NOT NULL,
            
            xml_dte TEXT,
            xml_firmado TEXT,
            estado ENUM('borrador', 'generado', 'firmado', 'enviado_sii', 'aceptado', 'rechazado') DEFAULT 'borrador',
            
            observaciones TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_dte (tipo_dte, folio, rut_emisor),
            INDEX idx_emisor (rut_emisor),
            INDEX idx_receptor (rut_receptor),
            INDEX idx_fecha (fecha_emision),
            INDEX idx_estado (estado)
        )",
    
    'dte_detalles' => "
        CREATE TABLE IF NOT EXISTS dte_detalles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dte_id INT NOT NULL,
            numero_linea INT NOT NULL,
            
            codigo_item VARCHAR(50),
            nombre_item VARCHAR(255) NOT NULL,
            descripcion TEXT,
            cantidad DECIMAL(10,3) NOT NULL DEFAULT 1,
            unidad_medida VARCHAR(10) DEFAULT 'UN',
            precio_unitario DECIMAL(15,2) NOT NULL,
            descuento_porcentaje DECIMAL(5,2) DEFAULT 0,
            descuento_monto DECIMAL(15,2) DEFAULT 0,
            
            monto_bruto DECIMAL(15,2) NOT NULL,
            monto_neto DECIMAL(15,2) NOT NULL,
            
            indica_exento BOOLEAN DEFAULT FALSE,
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_dte_linea (dte_id, numero_linea)
        )",
    
    'dte_referencias' => "
        CREATE TABLE IF NOT EXISTS dte_referencias (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dte_id INT NOT NULL,
            numero_linea INT NOT NULL,
            
            tipo_documento INT NOT NULL,
            folio_referencia INT NOT NULL,
            fecha_referencia DATE NOT NULL,
            codigo_referencia INT NOT NULL,
            razon_referencia VARCHAR(255),
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_dte_ref (dte_id, numero_linea)
        )",
    
    'sii_transacciones' => "
        CREATE TABLE IF NOT EXISTS sii_transacciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dte_id INT NOT NULL,
            tipo_transaccion ENUM('envio_dte', 'consulta_estado', 'solicitud_folios') NOT NULL,
            request_xml TEXT,
            response_xml TEXT,
            codigo_respuesta VARCHAR(10),
            descripcion_respuesta TEXT,
            fecha_transaccion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            INDEX idx_dte_transaccion (dte_id, tipo_transaccion)
        )",
    
    'boletas_electronicas' => "
        CREATE TABLE IF NOT EXISTS boletas_electronicas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            dte_id INT NOT NULL,
            
            numero_caja VARCHAR(10),
            cajero VARCHAR(100),
            forma_pago ENUM('efectivo', 'cheque', 'tarjeta_credito', 'tarjeta_debito', 'transferencia', 'otro') DEFAULT 'efectivo',
            
            periodo_desde DATE NULL,
            periodo_hasta DATE NULL,
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            
            UNIQUE KEY unique_boleta_dte (dte_id)
        )"
];

// Crear cada tabla
foreach ($tables as $tableName => $sql) {
    try {
        $pdo->exec($sql);
        echo "✓ Tabla '{$tableName}' creada/verificada\n";
    } catch (PDOException $e) {
        echo "✗ Error creando tabla '{$tableName}': " . $e->getMessage() . "\n";
    }
}

// Insertar datos de ejemplo
echo "\nInsertando datos de ejemplo...\n";

try {
    // Verificar si ya hay datos
    $stmt = $pdo->query("SELECT COUNT(*) FROM documentos_dte");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $sql = "INSERT INTO documentos_dte (tipo_dte, folio, fecha_emision, rut_emisor, razon_social_emisor, rut_receptor, razon_social_receptor, monto_total, estado) VALUES (33, 1, '2024-01-01', '11111111-1', 'EMPRESA EJEMPLO LTDA', '22222222-2', 'CLIENTE EJEMPLO S.A.', 119000.00, 'borrador')";
        $pdo->exec($sql);
        echo "✓ Datos de ejemplo insertados\n";
    } else {
        echo "✓ Ya existen {$count} documentos en la base de datos\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Error insertando datos: " . $e->getMessage() . "\n";
}

// Verificar tablas creadas
echo "\nVerificando tablas creadas...\n";
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
    $count = $stmt->fetchColumn();
    echo "  ✓ {$table} ({$count} registros)\n";
}

echo "\n🎉 ¡Base de datos configurada exitosamente!\n";
echo "Total de tablas: " . count($tables) . "\n";
?>
