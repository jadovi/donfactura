<?php
/**
 * Script para actualizar la base de datos con campos para PDF
 */

echo "=== ACTUALIZANDO BD PARA FUNCIONALIDAD PDF ===\n";

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

// Nuevas tablas y campos para PDF
$updates = [
    // Agregar campos de empresa para PDF
    "ALTER TABLE certificados ADD COLUMN IF NOT EXISTS logo_empresa LONGBLOB NULL" => "Logo empresa en certificados",
    "ALTER TABLE certificados ADD COLUMN IF NOT EXISTS datos_empresa JSON NULL" => "Datos empresa para PDF",
    
    // Tabla para configuración de empresa
    "CREATE TABLE IF NOT EXISTS empresas_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rut_empresa VARCHAR(12) NOT NULL UNIQUE,
        razon_social VARCHAR(255) NOT NULL,
        nombre_fantasia VARCHAR(255),
        giro VARCHAR(255),
        direccion VARCHAR(255),
        comuna VARCHAR(100),
        ciudad VARCHAR(100),
        region VARCHAR(100),
        telefono VARCHAR(50),
        email VARCHAR(100),
        website VARCHAR(100),
        logo_empresa LONGBLOB NULL,
        logo_nombre VARCHAR(255) NULL,
        logo_tipo VARCHAR(50) NULL,
        
        -- Configuración PDF
        formato_carta BOOLEAN DEFAULT TRUE,
        formato_80mm BOOLEAN DEFAULT TRUE,
        color_primario VARCHAR(7) DEFAULT '#000000',
        color_secundario VARCHAR(7) DEFAULT '#666666',
        
        -- Configuración de impresión
        margen_superior DECIMAL(5,2) DEFAULT 20.00,
        margen_inferior DECIMAL(5,2) DEFAULT 20.00,
        margen_izquierdo DECIMAL(5,2) DEFAULT 20.00,
        margen_derecho DECIMAL(5,2) DEFAULT 20.00,
        
        activo BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )" => "Configuración de empresas",
    
    // Tabla para almacenar PDFs generados
    "CREATE TABLE IF NOT EXISTS documentos_pdf (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dte_id INT NOT NULL,
        tipo_formato ENUM('carta', '80mm') NOT NULL,
        nombre_archivo VARCHAR(255) NOT NULL,
        ruta_archivo VARCHAR(500) NOT NULL,
        contenido_pdf LONGBLOB NULL,
        codigo_barras_2d TEXT NULL,
        fecha_generacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_dte_formato (dte_id, tipo_formato),
        FOREIGN KEY (dte_id) REFERENCES documentos_dte(id) ON DELETE CASCADE
    )" => "PDFs generados",
    
    // Tabla para plantillas PDF personalizables
    "CREATE TABLE IF NOT EXISTS plantillas_pdf (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rut_empresa VARCHAR(12) NOT NULL,
        tipo_dte INT NOT NULL,
        tipo_formato ENUM('carta', '80mm') NOT NULL,
        nombre_plantilla VARCHAR(255) NOT NULL,
        html_template TEXT NOT NULL,
        css_styles TEXT NULL,
        activa BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_empresa_tipo (rut_empresa, tipo_dte, tipo_formato)
    )" => "Plantillas PDF personalizables"
];

foreach ($updates as $sql => $description) {
    try {
        $pdo->exec($sql);
        echo "✓ {$description}\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') === false && 
            strpos($e->getMessage(), 'already exists') === false) {
            echo "✗ Error en '{$description}': " . $e->getMessage() . "\n";
        } else {
            echo "- {$description} (ya existe)\n";
        }
    }
}

// Insertar configuración de ejemplo
echo "\nInsertando configuración de ejemplo...\n";

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM empresas_config");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $sql = "INSERT INTO empresas_config (
            rut_empresa, razon_social, nombre_fantasia, giro, 
            direccion, comuna, ciudad, telefono, email
        ) VALUES (
            '76543210-9', 
            'EMPRESA EJEMPLO LIMITADA',
            'Empresa Ejemplo',
            'SERVICIOS DE CONSULTORÍA',
            'AV. PROVIDENCIA 1234',
            'PROVIDENCIA',
            'SANTIAGO',
            '+56912345678',
            'contacto@empresaejemplo.cl'
        )";
        
        $pdo->exec($sql);
        echo "✓ Configuración de empresa ejemplo insertada\n";
    } else {
        echo "✓ Ya existe configuración de empresa ({$count} registros)\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Error insertando configuración: " . $e->getMessage() . "\n";
}

// Verificar nuevas tablas
echo "\nVerificando nuevas tablas...\n";
$newTables = ['empresas_config', 'documentos_pdf', 'plantillas_pdf'];

foreach ($newTables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
        $count = $stmt->fetchColumn();
        echo "  ✓ {$table} ({$count} registros)\n";
    } catch (PDOException $e) {
        echo "  ✗ {$table}: " . $e->getMessage() . "\n";
    }
}

echo "\n🎉 ¡Base de datos actualizada para funcionalidad PDF!\n";
echo "Nuevas características:\n";
echo "- ✅ Almacenamiento de logos de empresa\n";
echo "- ✅ Configuración personalizable por empresa\n";
echo "- ✅ Soporte para formatos CARTA y 80mm\n";
echo "- ✅ Plantillas PDF personalizables\n";
echo "- ✅ Almacenamiento de códigos de barras 2D\n";
?>
