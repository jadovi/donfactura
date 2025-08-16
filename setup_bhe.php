<?php
/**
 * Script para configurar Boletas de Honorarios Electrónicas (BHE DTE 41)
 */

echo "=== CONFIGURANDO BOLETAS DE HONORARIOS ELECTRÓNICAS (BHE) ===\n";

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

echo "\n1. Actualizando configuración DTE para incluir BHE...\n";

// Actualizar tipos de DTE
$updateConfig = "
UPDATE empresas_config 
SET updated_at = CURRENT_TIMESTAMP 
WHERE rut_empresa = '76543210-9'
";

try {
    $pdo->exec($updateConfig);
    echo "✓ Configuración actualizada\n";
} catch (PDOException $e) {
    echo "- Configuración: " . $e->getMessage() . "\n";
}

echo "\n2. Creando tablas específicas para BHE...\n";

// Tablas para Boletas de Honorarios Electrónicas
$tablasBHE = [
    // Tabla principal para BHE
    "CREATE TABLE IF NOT EXISTS boletas_honorarios_electronicas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dte_id INT NOT NULL,
        
        -- Datos del profesional (emisor BHE)
        rut_profesional VARCHAR(12) NOT NULL,
        nombre_profesional VARCHAR(255) NOT NULL,
        apellido_paterno VARCHAR(100) NOT NULL,
        apellido_materno VARCHAR(100),
        profesion VARCHAR(255),
        direccion_profesional VARCHAR(255),
        comuna_profesional VARCHAR(100),
        codigo_comuna_profesional VARCHAR(5),
        
        -- Datos del pagador (receptor/cliente)
        rut_pagador VARCHAR(12) NOT NULL,
        nombre_pagador VARCHAR(255) NOT NULL,
        direccion_pagador VARCHAR(255),
        comuna_pagador VARCHAR(100),
        codigo_comuna_pagador VARCHAR(5),
        
        -- Período de prestación de servicios
        periodo_desde DATE NOT NULL,
        periodo_hasta DATE NOT NULL,
        descripcion_servicios TEXT NOT NULL,
        
        -- Montos específicos BHE
        monto_bruto DECIMAL(15,2) NOT NULL,
        retencion_honorarios DECIMAL(15,2) NOT NULL DEFAULT 0,
        monto_liquido DECIMAL(15,2) NOT NULL,
        
        -- Configuración
        aplica_retencion BOOLEAN DEFAULT TRUE,
        porcentaje_retencion DECIMAL(5,2) DEFAULT 10.00,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        INDEX idx_profesional (rut_profesional),
        INDEX idx_pagador (rut_pagador),
        INDEX idx_periodo (periodo_desde, periodo_hasta),
        FOREIGN KEY (dte_id) REFERENCES documentos_dte(id) ON DELETE CASCADE,
        UNIQUE KEY unique_bhe_dte (dte_id)
    )" => "Tabla boletas_honorarios_electronicas",
    
    // Configuración de profesionales para BHE
    "CREATE TABLE IF NOT EXISTS profesionales_bhe (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rut_profesional VARCHAR(12) NOT NULL UNIQUE,
        
        -- Datos personales
        nombres VARCHAR(255) NOT NULL,
        apellido_paterno VARCHAR(100) NOT NULL,
        apellido_materno VARCHAR(100),
        fecha_nacimiento DATE,
        
        -- Datos profesionales
        profesion VARCHAR(255),
        titulo_profesional VARCHAR(255),
        universidad VARCHAR(255),
        
        -- Datos de contacto
        telefono VARCHAR(50),
        email VARCHAR(100),
        direccion VARCHAR(255),
        comuna VARCHAR(100),
        codigo_comuna VARCHAR(5),
        region VARCHAR(100),
        
        -- Configuración BHE
        activo_bhe BOOLEAN DEFAULT TRUE,
        porcentaje_retencion_default DECIMAL(5,2) DEFAULT 10.00,
        
        -- Certificado digital
        certificado_id INT NULL,
        
        -- Metadatos
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_activo (activo_bhe),
        INDEX idx_comuna (codigo_comuna)
    )" => "Tabla profesionales_bhe",
    
    // Códigos de comunas chilenas para BHE
    "CREATE TABLE IF NOT EXISTS comunas_chile (
        codigo VARCHAR(5) PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        region_codigo VARCHAR(3) NOT NULL,
        region_nombre VARCHAR(100) NOT NULL,
        provincia VARCHAR(100),
        activa BOOLEAN DEFAULT TRUE,
        
        INDEX idx_region (region_codigo),
        INDEX idx_activa (activa)
    )" => "Tabla comunas_chile",
    
    // Plantillas específicas para PDF BHE
    "CREATE TABLE IF NOT EXISTS plantillas_bhe_pdf (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rut_profesional VARCHAR(12) NOT NULL,
        nombre_plantilla VARCHAR(255) NOT NULL,
        tipo_formato ENUM('carta', '80mm', 'email') NOT NULL DEFAULT 'carta',
        
        -- Template HTML específico para BHE
        html_template TEXT NOT NULL,
        css_styles TEXT,
        
        -- Configuración específica BHE
        incluir_retencion BOOLEAN DEFAULT TRUE,
        mostrar_periodo BOOLEAN DEFAULT TRUE,
        mostrar_descripcion_detallada BOOLEAN DEFAULT TRUE,
        
        -- Estados
        activa BOOLEAN DEFAULT TRUE,
        es_default BOOLEAN DEFAULT FALSE,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_profesional_formato (rut_profesional, tipo_formato),
        INDEX idx_activa (activa)
    )" => "Tabla plantillas_bhe_pdf"
];

foreach ($tablasBHE as $sql => $description) {
    try {
        $pdo->exec($sql);
        echo "✓ {$description}\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'already exists') === false) {
            echo "✗ Error en '{$description}': " . $e->getMessage() . "\n";
        } else {
            echo "- {$description} (ya existe)\n";
        }
    }
}

echo "\n3. Insertando comunas de ejemplo de la Región Metropolitana...\n";

$comunasRM = [
    ['13101', 'SANTIAGO', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13102', 'CERRILLOS', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13103', 'CERRO NAVIA', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13104', 'CONCHALÍ', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13105', 'EL BOSQUE', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13106', 'ESTACIÓN CENTRAL', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13107', 'HUECHURABA', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13108', 'INDEPENDENCIA', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13109', 'LA CISTERNA', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13110', 'LA FLORIDA', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13111', 'LA GRANJA', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13112', 'LA PINTANA', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13113', 'LA REINA', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13114', 'LAS CONDES', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13115', 'LO BARNECHEA', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13116', 'LO ESPEJO', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13117', 'LO PRADO', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13118', 'MACUL', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13119', 'MAIPÚ', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13120', 'ÑUÑOA', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13121', 'PEDRO AGUIRRE CERDA', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13122', 'PEÑALOLÉN', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13123', 'PROVIDENCIA', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13124', 'PUDAHUEL', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13125', 'QUILICURA', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13126', 'QUINTA NORMAL', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13127', 'RECOLETA', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13128', 'RENCA', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13129', 'SAN JOAQUÍN', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13130', 'SAN MIGUEL', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13131', 'SAN RAMÓN', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO'],
    ['13132', 'VITACURA', '13', 'REGIÓN METROPOLITANA DE SANTIAGO', 'SANTIAGO']
];

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM comunas_chile");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $sql = "INSERT INTO comunas_chile (codigo, nombre, region_codigo, region_nombre, provincia) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        
        foreach ($comunasRM as $comuna) {
            $stmt->execute($comuna);
        }
        
        echo "✓ " . count($comunasRM) . " comunas de la RM insertadas\n";
    } else {
        echo "✓ Ya existen {$count} comunas en la base de datos\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Error insertando comunas: " . $e->getMessage() . "\n";
}

echo "\n4. Creando profesional de ejemplo para BHE...\n";

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM profesionales_bhe");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        $sql = "INSERT INTO profesionales_bhe (
            rut_profesional, nombres, apellido_paterno, apellido_materno, 
            profesion, titulo_profesional, telefono, email, 
            direccion, comuna, codigo_comuna, region
        ) VALUES (
            '12345678-9', 'JUAN CARLOS', 'PÉREZ', 'GONZÁLEZ',
            'INGENIERO INFORMÁTICO', 'INGENIERO CIVIL EN INFORMÁTICA', 
            '+56912345678', 'juan.perez@email.com',
            'AV. PROVIDENCIA 1234', 'PROVIDENCIA', '13123', 'REGIÓN METROPOLITANA'
        )";
        
        $pdo->exec($sql);
        echo "✓ Profesional de ejemplo creado (RUT: 12345678-9)\n";
    } else {
        echo "✓ Ya existen {$count} profesionales registrados\n";
    }
    
} catch (PDOException $e) {
    echo "✗ Error creando profesional: " . $e->getMessage() . "\n";
}

echo "\n5. Verificando tablas BHE creadas...\n";

$tablasBHE = ['boletas_honorarios_electronicas', 'profesionales_bhe', 'comunas_chile', 'plantillas_bhe_pdf'];

foreach ($tablasBHE as $tabla) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM `{$tabla}`");
        $count = $stmt->fetchColumn();
        echo "  ✓ {$tabla} ({$count} registros)\n";
    } catch (PDOException $e) {
        echo "  ✗ {$tabla}: " . $e->getMessage() . "\n";
    }
}

echo "\n🎉 ¡Configuración BHE completada!\n";
echo "Funcionalidades BHE disponibles:\n";
echo "- ✅ DTE Tipo 41 (Boleta de Honorarios Electrónica)\n";
echo "- ✅ Firma electrónica obligatoria\n";
echo "- ✅ Retención 10% segunda categoría\n";
echo "- ✅ Gestión de profesionales independientes\n";
echo "- ✅ Códigos de comunas oficiales\n";
echo "- ✅ PDF específicos para BHE\n";
echo "- ✅ Plantillas personalizables por profesional\n";

echo "\nPróximos pasos:\n";
echo "1. Configurar profesionales con certificados digitales\n";
echo "2. Solicitar folios CAF para tipo DTE 41\n";
echo "3. Generar primeras BHE de prueba\n";
echo "4. Configurar plantillas PDF específicas\n";

echo "\n🚀 ¡Sistema BHE listo para usar!\n";
?>
