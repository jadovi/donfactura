<?php
/**
 * Punto de entrada principal de la API DTE
 * Facturación Electrónica Chile
 */

declare(strict_types=1);

use DonFactura\DTE\Core\Application;
use DonFactura\DTE\Core\Database;
use DonFactura\DTE\Controllers\DTEController;
use DonFactura\DTE\Controllers\FoliosController;
use DonFactura\DTE\Controllers\BoletasController;
use DonFactura\DTE\Controllers\CertificadosController;
use DonFactura\DTE\Middleware\ValidationMiddleware;
use DonFactura\DTE\Middleware\CorsMiddleware;
use Slim\Factory\AppFactory;
use Slim\Middleware\ContentLengthMiddleware;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

require __DIR__ . '/../vendor/autoload.php';

// Cargar configuración
$config = require __DIR__ . '/../config/database.php';

// Crear directorios necesarios
$paths = $config['paths'];
foreach ($paths as $path) {
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
}

// Configurar logger
$logger = new Logger('dte-api');
$logger->pushHandler(new StreamHandler($paths['logs'] . 'app.log', Logger::DEBUG));

// Inicializar base de datos
$database = new Database($config['database']);
$pdo = $database->getConnection();

// Crear aplicación Slim
$app = AppFactory::create();

// Middleware global
$app->addMiddleware(new CorsMiddleware());
$app->addMiddleware(new ContentLengthMiddleware());
$app->add(new ValidationMiddleware($logger));

// Middleware de parsing del body
$app->addBodyParsingMiddleware();

// Middleware de manejo de errores
$errorMiddleware = $app->addErrorMiddleware(true, true, true, $logger);

// Inicializar controladores
$dteController = new DTEController($pdo, $logger, $config);
$foliosController = new FoliosController($pdo, $logger, $config);
$boletasController = new BoletasController($pdo, $logger, $config);
$certificadosController = new CertificadosController($pdo, $logger, $config);

// Rutas principales
$app->get('/', function ($request, $response) {
    $data = [
        'message' => 'API DTE - Documentos Tributarios Electrónicos Chile',
        'version' => '1.0.0',
        'status' => 'active',
        'endpoints' => [
            'POST /api/dte/generar' => 'Generar DTE',
            'GET /api/dte/{id}' => 'Obtener DTE por ID',
            'POST /api/folios/solicitar' => 'Solicitar folios CAF',
            'GET /api/folios/disponibles' => 'Consultar folios disponibles',
            'POST /api/boletas/generar' => 'Generar boleta electrónica',
            'POST /api/certificados/upload' => 'Subir certificado PFX',
            'GET /api/certificados' => 'Listar certificados'
        ]
    ];
    
    $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
    return $response->withHeader('Content-Type', 'application/json');
});

// Grupo de rutas de la API
$app->group('/api', function ($group) use ($dteController, $foliosController, $boletasController, $certificadosController) {
    
    // Rutas DTE generales
    $group->group('/dte', function ($dte) use ($dteController) {
        $dte->post('/generar', [$dteController, 'generar']);
        $dte->get('/{id:[0-9]+}', [$dteController, 'obtener']);
        $dte->get('/tipo/{tipo:[0-9]+}', [$dteController, 'listarPorTipo']);
        $dte->post('/firmar/{id:[0-9]+}', [$dteController, 'firmar']);
        $dte->post('/enviar/{id:[0-9]+}', [$dteController, 'enviarSII']);
        $dte->get('/estado/{id:[0-9]+}', [$dteController, 'consultarEstado']);
    });
    
    // Rutas para gestión de folios
    $group->group('/folios', function ($folios) use ($foliosController) {
        $folios->post('/solicitar', [$foliosController, 'solicitar']);
        $folios->get('/disponibles/{tipo:[0-9]+}', [$foliosController, 'disponibles']);
        $folios->get('/siguiente/{tipo:[0-9]+}', [$foliosController, 'siguienteFolio']);
        $folios->post('/cargar-caf', [$foliosController, 'cargarCAF']);
    });
    
    // Rutas específicas para boletas electrónicas
    $group->group('/boletas', function ($boletas) use ($boletasController) {
        $boletas->post('/generar', [$boletasController, 'generar']);
        $boletas->get('/{id:[0-9]+}', [$boletasController, 'obtener']);
        $boletas->post('/envio-masivo', [$boletasController, 'envioMasivo']);
        $boletas->get('/reporte/{fecha}', [$boletasController, 'reporteDiario']);
    });
    
    // Rutas para gestión de certificados
    $group->group('/certificados', function ($certs) use ($certificadosController) {
        $certs->post('/upload', [$certificadosController, 'upload']);
        $certs->get('', [$certificadosController, 'listar']);
        $certs->get('/{id:[0-9]+}', [$certificadosController, 'obtener']);
        $certs->delete('/{id:[0-9]+}', [$certificadosController, 'eliminar']);
        $certs->post('/validar/{id:[0-9]+}', [$certificadosController, 'validar']);
    });
});

// Ruta de health check
$app->get('/health', function ($request, $response) use ($pdo) {
    try {
        $stmt = $pdo->query('SELECT 1');
        $dbStatus = $stmt ? 'connected' : 'disconnected';
    } catch (Exception $e) {
        $dbStatus = 'error: ' . $e->getMessage();
    }
    
    $health = [
        'status' => 'ok',
        'timestamp' => date('c'),
        'database' => $dbStatus,
        'php_version' => PHP_VERSION,
        'memory_usage' => memory_get_usage(true),
        'memory_peak' => memory_get_peak_usage(true)
    ];
    
    $response->getBody()->write(json_encode($health));
    return $response->withHeader('Content-Type', 'application/json');
});

// Ejecutar aplicación
$app->run();
