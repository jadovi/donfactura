-- Script para crear la base de datos del sistema DTE
-- Facturación Electrónica Chile

CREATE DATABASE IF NOT EXISTS dte_sistema CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE dte_sistema;

-- Tabla para almacenar certificados digitales PFX
CREATE TABLE certificados (
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
);

-- Tabla para gestión de folios CAF (Código de Autorización de Folios)
CREATE TABLE folios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_dte INT NOT NULL, -- 33, 34, 39, 45, 56, 61, etc.
    rut_empresa VARCHAR(12) NOT NULL,
    folio_desde INT NOT NULL,
    folio_hasta INT NOT NULL,
    fecha_resolucion DATE NOT NULL,
    fecha_vencimiento DATE NOT NULL,
    xml_caf TEXT NOT NULL, -- XML del CAF completo
    folios_disponibles INT NOT NULL,
    activo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tipo_empresa (tipo_dte, rut_empresa),
    INDEX idx_folios_range (folio_desde, folio_hasta)
);

-- Tabla para control de folios utilizados
CREATE TABLE folios_utilizados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folio_caf_id INT NOT NULL,
    folio_numero INT NOT NULL,
    fecha_utilizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    dte_id INT NULL, -- Referencia al DTE generado
    FOREIGN KEY (folio_caf_id) REFERENCES folios(id),
    UNIQUE KEY unique_folio_caf (folio_caf_id, folio_numero)
);

-- Tabla principal para documentos tributarios electrónicos
CREATE TABLE documentos_dte (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo_dte INT NOT NULL,
    folio INT NOT NULL,
    fecha_emision DATE NOT NULL,
    
    -- Datos del emisor
    rut_emisor VARCHAR(12) NOT NULL,
    razon_social_emisor VARCHAR(255) NOT NULL,
    giro_emisor VARCHAR(255),
    direccion_emisor VARCHAR(255),
    comuna_emisor VARCHAR(100),
    ciudad_emisor VARCHAR(100),
    
    -- Datos del receptor
    rut_receptor VARCHAR(12) NOT NULL,
    razon_social_receptor VARCHAR(255) NOT NULL,
    giro_receptor VARCHAR(255),
    direccion_receptor VARCHAR(255),
    comuna_receptor VARCHAR(100),
    ciudad_receptor VARCHAR(100),
    
    -- Montos y totales
    monto_neto DECIMAL(15,2) DEFAULT 0,
    monto_iva DECIMAL(15,2) DEFAULT 0,
    monto_total DECIMAL(15,2) NOT NULL,
    
    -- XML y estados
    xml_dte TEXT, -- XML del DTE generado
    xml_firmado TEXT, -- XML firmado digitalmente
    estado ENUM('borrador', 'generado', 'firmado', 'enviado_sii', 'aceptado', 'rechazado') DEFAULT 'borrador',
    
    -- Metadatos
    observaciones TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_dte (tipo_dte, folio, rut_emisor),
    INDEX idx_emisor (rut_emisor),
    INDEX idx_receptor (rut_receptor),
    INDEX idx_fecha (fecha_emision),
    INDEX idx_estado (estado)
);

-- Tabla para el detalle de productos/servicios en los DTE
CREATE TABLE dte_detalles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dte_id INT NOT NULL,
    numero_linea INT NOT NULL,
    
    -- Producto/Servicio
    codigo_item VARCHAR(50),
    nombre_item VARCHAR(255) NOT NULL,
    descripcion TEXT,
    cantidad DECIMAL(10,3) NOT NULL DEFAULT 1,
    unidad_medida VARCHAR(10) DEFAULT 'UN',
    precio_unitario DECIMAL(15,2) NOT NULL,
    descuento_porcentaje DECIMAL(5,2) DEFAULT 0,
    descuento_monto DECIMAL(15,2) DEFAULT 0,
    
    -- Montos línea
    monto_bruto DECIMAL(15,2) NOT NULL,
    monto_neto DECIMAL(15,2) NOT NULL,
    
    -- Impuestos
    indica_exento BOOLEAN DEFAULT FALSE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (dte_id) REFERENCES documentos_dte(id) ON DELETE CASCADE,
    INDEX idx_dte_linea (dte_id, numero_linea)
);

-- Tabla para referencias a otros documentos (notas de crédito/débito)
CREATE TABLE dte_referencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dte_id INT NOT NULL,
    numero_linea INT NOT NULL,
    
    tipo_documento INT NOT NULL, -- Tipo del documento referenciado
    folio_referencia INT NOT NULL,
    fecha_referencia DATE NOT NULL,
    codigo_referencia INT NOT NULL, -- 1=Anula, 2=Corrige texto, 3=Corrige montos
    razon_referencia VARCHAR(255),
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (dte_id) REFERENCES documentos_dte(id) ON DELETE CASCADE,
    INDEX idx_dte_ref (dte_id, numero_linea)
);

-- Tabla para log de transacciones con el SII
CREATE TABLE sii_transacciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dte_id INT NOT NULL,
    tipo_transaccion ENUM('envio_dte', 'consulta_estado', 'solicitud_folios') NOT NULL,
    request_xml TEXT,
    response_xml TEXT,
    codigo_respuesta VARCHAR(10),
    descripcion_respuesta TEXT,
    fecha_transaccion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (dte_id) REFERENCES documentos_dte(id),
    INDEX idx_dte_transaccion (dte_id, tipo_transaccion)
);

-- Tabla específica para boletas electrónicas (DTE tipo 39)
CREATE TABLE boletas_electronicas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dte_id INT NOT NULL,
    
    -- Campos específicos de boletas
    numero_caja VARCHAR(10),
    cajero VARCHAR(100),
    forma_pago ENUM('efectivo', 'cheque', 'tarjeta_credito', 'tarjeta_debito', 'transferencia', 'otro') DEFAULT 'efectivo',
    
    -- Para boletas de servicios periódicos
    periodo_desde DATE NULL,
    periodo_hasta DATE NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (dte_id) REFERENCES documentos_dte(id) ON DELETE CASCADE,
    UNIQUE KEY unique_boleta_dte (dte_id)
);

-- Insertar datos de ejemplo para tipos de DTE
INSERT INTO `documentos_dte` (`tipo_dte`, `folio`, `fecha_emision`, `rut_emisor`, `razon_social_emisor`, `rut_receptor`, `razon_social_receptor`, `monto_total`, `estado`) VALUES
(33, 0, '2024-01-01', '11111111-1', 'EMPRESA EMISORA LTDA', '22222222-2', 'CLIENTE RECEPTOR S.A.', 119000.00, 'borrador');

-- Crear usuario específico para la aplicación
CREATE USER IF NOT EXISTS 'dte_user'@'localhost' IDENTIFIED BY 'dte_pass_2024';
GRANT ALL PRIVILEGES ON dte_sistema.* TO 'dte_user'@'localhost';
FLUSH PRIVILEGES;
