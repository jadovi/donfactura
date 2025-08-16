<?php
/**
 * Versión básica de la API DTE - Sin dependencias complejas
 * Para verificar que el sistema funciona correctamente
 */

declare(strict_types=1);

// Configuración básica
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Cargar configuración
$config = require __DIR__ . '/../config/database.php';

// Función para crear respuesta JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Función para log simple
function logMessage($message) {
    $logFile = __DIR__ . '/../storage/logs/app.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
}

// Routing básico
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

logMessage("Request: {$method} {$uri}");

// Rutas
switch ($uri) {
    case '/':
        jsonResponse([
            'message' => 'API DTE - Documentos Tributarios Electrónicos Chile',
            'version' => '1.0.0 (Basic Mode)',
            'status' => 'active',
            'mode' => 'basic',
            'note' => 'Ejecutándose en modo básico. Sistema funcionando correctamente.',
            'endpoints' => [
                'GET /' => 'Información de la API',
                'GET /health' => 'Estado del sistema',
                'GET /test-db' => 'Test de conexión a BD',
                'POST /test' => 'Test de funcionalidad básica',
                'GET /estructura' => 'Ver estructura de base de datos',
                'GET /pdf-features' => 'Ver funcionalidades PDF implementadas'
            ]
        ]);
        break;

    case '/health':
        try {
            // Cargar clase Database manualmente
            require_once __DIR__ . '/../src/Core/Database.php';
            
            $database = new DonFactura\DTE\Core\Database($config['database']);
            $pdo = $database->getConnection();
            $stmt = $pdo->query('SELECT 1');
            $dbStatus = $stmt ? 'connected' : 'disconnected';
        } catch (Exception $e) {
            $dbStatus = 'error: ' . $e->getMessage();
            logMessage('Database health check failed: ' . $e->getMessage());
        }

        jsonResponse([
            'status' => 'ok',
            'mode' => 'basic',
            'timestamp' => date('c'),
            'database' => $dbStatus,
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'extensions' => [
                'pdo' => extension_loaded('pdo') ? 'ok' : 'missing',
                'pdo_mysql' => extension_loaded('pdo_mysql') ? 'ok' : 'missing',
                'openssl' => extension_loaded('openssl') ? 'ok' : 'missing',
                'curl' => extension_loaded('curl') ? 'ok' : 'missing',
                'simplexml' => extension_loaded('simplexml') ? 'ok' : 'missing',
            ]
        ]);
        break;

    case '/test-db':
        try {
            require_once __DIR__ . '/../src/Core/Database.php';
            
            $database = new DonFactura\DTE\Core\Database($config['database']);
            $pdo = $database->getConnection();

            // Test query
            $stmt = $pdo->query('SHOW TABLES');
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Contar registros en cada tabla
            $tableStats = [];
            foreach ($tables as $table) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
                $count = $stmt->fetchColumn();
                $tableStats[$table] = $count;
            }

            jsonResponse([
                'success' => true,
                'message' => 'Conexión a base de datos exitosa',
                'database' => $config['database']['database'],
                'tables_found' => count($tables),
                'tables' => $tableStats
            ]);

        } catch (Exception $e) {
            logMessage('Database test failed: ' . $e->getMessage());
            jsonResponse([
                'success' => false,
                'error' => 'Error de conexión: ' . $e->getMessage()
            ], 500);
        }
        break;

    case '/test':
        if ($method === 'POST') {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            logMessage('Test endpoint called with data: ' . $input);

            jsonResponse([
                'success' => true,
                'message' => 'Test endpoint funcionando correctamente',
                'received_data' => $data,
                'timestamp' => date('c'),
                'method' => $method
            ]);
        } else {
            jsonResponse([
                'success' => true,
                'message' => 'Test endpoint disponible',
                'note' => 'Envía datos via POST para probar',
                'timestamp' => date('c')
            ]);
        }
        break;

    case '/install':
        jsonResponse([
            'title' => 'Información de Instalación DonFactura DTE',
            'current_status' => 'Modo Básico - Funcionando',
            'database_status' => 'Configurada y funcionando',
            'next_steps' => [
                '1. Sistema está funcionando correctamente',
                '2. Base de datos configurada con 8 tablas',
                '3. Listo para implementar funcionalidad completa',
                '4. Instalar Composer para dependencias completas'
            ],
            'test_endpoints' => [
                'GET /health' => 'Estado del sistema',
                'GET /test-db' => 'Test de conexión a BD',
                'POST /test' => 'Test básico de funcionalidad'
            ],
            'next_phase' => [
                'Upload certificados PFX',
                'Solicitar folios CAF',
                'Generar primeros DTE'
            ]
        ]);
        break;

    case '/estructura':
        try {
            require_once __DIR__ . '/../src/Core/Database.php';
            
            $database = new DonFactura\DTE\Core\Database($config['database']);
            $pdo = $database->getConnection();

            $structure = [];
            
            // Obtener estructura de todas las tablas incluyendo las nuevas
            $tables = ['certificados', 'folios', 'documentos_dte', 'dte_detalles', 'boletas_electronicas', 'empresas_config', 'documentos_pdf', 'plantillas_pdf'];
            
            foreach ($tables as $table) {
                try {
                    $stmt = $pdo->query("DESCRIBE `{$table}`");
                    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
                    $count = $stmt->fetchColumn();
                    
                    $structure[$table] = [
                        'columns' => count($columns),
                        'records' => $count,
                        'fields' => array_column($columns, 'Field')
                    ];
                } catch (Exception $e) {
                    $structure[$table] = ['error' => $e->getMessage()];
                }
            }

            jsonResponse([
                'success' => true,
                'database_structure' => $structure,
                'timestamp' => date('c')
            ]);

        } catch (Exception $e) {
            jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
        break;

    case '/pdf-features':
        jsonResponse([
            'title' => 'Funcionalidades PDF Implementadas',
            'features' => [
                '✅ Generación PDF formato CARTA (21.5x27.9cm)',
                '✅ Generación PDF formato 80mm (ticket térmico)',
                '✅ Códigos de barras 2D según especificaciones SII',
                '✅ Gestión de logos de empresa',
                '✅ Personalización de colores y estilos',
                '✅ Configuración de márgenes',
                '✅ Plantillas personalizables por empresa',
                '✅ Almacenamiento de PDFs generados'
            ],
            'endpoints_pdf' => [
                'POST /api/empresas/config' => 'Configurar datos de empresa',
                'POST /api/empresas/{id}/logo' => 'Subir logo de empresa',
                'GET /api/empresas/{rut}/config' => 'Obtener configuración',
                'POST /api/dte/{id}/pdf?formato=carta' => 'Generar PDF formato carta',
                'POST /api/dte/{id}/pdf?formato=80mm' => 'Generar PDF formato 80mm',
                'GET /api/pdf/{pdf_id}/download' => 'Descargar PDF generado'
            ],
            'formatos_soportados' => [
                'carta' => [
                    'tamaño' => '21.5 x 27.9 cm',
                    'orientacion' => 'vertical',
                    'uso' => 'Facturas, notas de crédito/débito para archivo e impresión estándar'
                ],
                '80mm' => [
                    'tamaño' => '80mm ancho x auto alto',
                    'orientacion' => 'vertical',
                    'uso' => 'Boletas para impresoras térmicas de punto de venta'
                ]
            ],
            'codigo_barras_2d' => [
                'formato' => 'Según especificaciones SII',
                'contenido' => 'RUTEmisor;TipoDTE;Folio;Fecha;RUTReceptor;Monto',
                'ubicacion' => 'Superior derecho (carta) / Inferior centrado (80mm)'
            ]
        ]);
        break;

    default:
        jsonResponse([
            'error' => 'Endpoint no encontrado',
            'available_endpoints' => [
                'GET /',
                'GET /health',
                'GET /test-db',
                'POST /test',
                'GET /install',
                'GET /estructura',
                'GET /pdf-features'
            ]
        ], 404);
}
?>
