<?php
/**
 * Configuración de la base de datos para el sistema DTE
 * Facturación Electrónica Chile
 */

return [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'dte_sistema',
        'username' => 'root',
        'password' => '123123',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],
    
    'sii' => [
        // URLs de certificación SII
        'cert_url_solicitud_folios' => 'https://maullin.sii.cl/DTEWS/GetTokenFromSeed.jws',
        'cert_url_upload_dte' => 'https://maullin.sii.cl/DTEWS/services/wsdte',
        
        // URLs de producción SII (usar cuando esté certificado)
        'prod_url_solicitud_folios' => 'https://palena.sii.cl/DTEWS/GetTokenFromSeed.jws',
        'prod_url_upload_dte' => 'https://palena.sii.cl/DTEWS/services/wsdte',
        
        'environment' => 'certification', // 'certification' o 'production'
    ],
    
    'paths' => [
        'certificates' => __DIR__ . '/../storage/certificates/',
        'xml_temp' => __DIR__ . '/../storage/temp/',
        'xml_generated' => __DIR__ . '/../storage/generated/',
        'logs' => __DIR__ . '/../storage/logs/',
    ],
    
    'dte_types' => [
        33 => 'Factura Electrónica',
        34 => 'Factura Electrónica Exenta',
        39 => 'Boleta Electrónica',
        41 => 'Boleta de Honorarios Electrónica (BHE)',
        45 => 'Factura de Compra Electrónica',
        56 => 'Nota de Débito Electrónica',
        61 => 'Nota de Crédito Electrónica',
    ]
];
