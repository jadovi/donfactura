<?php
/**
 * Versión simplificada de la API DTE sin dependencias de Composer
 * Para usar mientras se instalan las dependencias completas
 */

declare(strict_types=1);

// Autoloader básico
require __DIR__ . '/../vendor/autoload.php';

use DonFactura\DTE\Core\Database;

// Error handling
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Cargar configuración
$config = require __DIR__ . '/../config/database.php';

// Crear directorios si no existen
foreach ($config['paths'] as $path) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

// Logger simple
require_once __DIR__ . '/../src/Utils/SimpleFramework.php';

$logger = new DonFactura\DTE\Utils\SimpleLogger($config['paths']['logs'] . 'app.log');

// Framework simple
$app = new DonFactura\DTE\Utils\SimpleFramework();

// Ruta principal
$app->get('/', function($request, $response) use ($config) {
    $data = [
        'message' => 'API DTE - Documentos Tributarios Electrónicos Chile',
        'version' => '1.0.0 (Simple Mode)',
        'status' => 'active',
        'note' => 'Ejecutándose en modo simple. Instala Composer para funcionalidad completa.',
        'endpoints' => [
            'GET /' => 'Información de la API',
            'GET /health' => 'Estado del sistema',
            'POST /api/test' => 'Test de funcionalidad básica'
        ]
    ];
    
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// Health check
$app->get('/health', function($request, $response) use ($config, $logger) {
    try {
        // Probar conexión a base de datos
        $database = new Database($config['database']);
        $pdo = $database->getConnection();
        $stmt = $pdo->query('SELECT 1');
        $dbStatus = $stmt ? 'connected' : 'disconnected';
    } catch (Exception $e) {
        $dbStatus = 'error: ' . $e->getMessage();
        $logger->error('Database health check failed', ['error' => $e->getMessage()]);
    }
    
    $health = [
        'status' => 'ok',
        'mode' => 'simple',
        'timestamp' => date('c'),
        'database' => $dbStatus,
        'php_version' => PHP_VERSION,
        'memory_usage' => memory_get_usage(true),
        'extensions' => [
            'pdo' => extension_loaded('pdo'),
            'openssl' => extension_loaded('openssl'),
            'curl' => extension_loaded('curl'),
            'simplexml' => extension_loaded('simplexml'),
        ]
    ];
    
    $response->getBody()->write(json_encode($health, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// Test endpoint
$app->post('/api/test', function($request, $response) use ($logger) {
    $data = $request->getParsedBody();
    
    $logger->info('Test endpoint called', ['data' => $data]);
    
    $result = [
        'success' => true,
        'message' => 'Test endpoint funcionando correctamente',
        'received_data' => $data,
        'timestamp' => date('c')
    ];
    
    $response->getBody()->write(json_encode($result));
    return $response->withHeader('Content-Type', 'application/json');
});

// Test de conexión a BD
$app->get('/api/test-db', function($request, $response) use ($config, $logger) {
    try {
        $database = new Database($config['database']);
        $pdo = $database->getConnection();
        
        // Test query
        $stmt = $pdo->query('SHOW TABLES');
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $result = [
            'success' => true,
            'message' => 'Conexión a base de datos exitosa',
            'database' => $config['database']['database'],
            'tables_found' => count($tables),
            'tables' => $tables
        ];
        
        $logger->info('Database test successful', ['tables_count' => count($tables)]);
        
    } catch (Exception $e) {
        $result = [
            'success' => false,
            'error' => 'Error de conexión: ' . $e->getMessage()
        ];
        
        $logger->error('Database test failed', ['error' => $e->getMessage()]);
    }
    
    $response->getBody()->write(json_encode($result, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// Información sobre instalación
$app->get('/install', function($request, $response) {
    $info = [
        'title' => 'Información de Instalación DonFactura DTE',
        'current_status' => 'Modo Simple - Sin Composer',
        'next_steps' => [
            '1. Instalar Composer desde https://getcomposer.org/',
            '2. Ejecutar: composer install',
            '3. Configurar base de datos con el script SQL',
            '4. Cambiar a public/index.php para funcionalidad completa'
        ],
        'database_setup' => [
            'script' => 'database/create_database.sql',
            'command' => 'mysql -u root -p123123 < database/create_database.sql'
        ],
        'test_endpoints' => [
            'GET /health' => 'Estado del sistema',
            'GET /api/test-db' => 'Test de conexión a BD',
            'POST /api/test' => 'Test básico de funcionalidad'
        ]
    ];
    
    $response->getBody()->write(json_encode($info, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// Log de inicio
$logger->info('DTE API started in simple mode');

// Ejecutar aplicación
$app->run();
?>
