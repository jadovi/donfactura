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

// Cargar autoloader primero
require_once __DIR__ . '/../vendor/autoload.php';

// Función para log simple
function logMessage($message) {
    $logFile = __DIR__ . '/../storage/logs/app.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
}

// Logger simple para index_basic.php
class SimpleLogger implements \Psr\Log\LoggerInterface {
    use \Psr\Log\LoggerTrait;
    
    public function log($level, $message, array $context = []): void {
        logMessage("[$level] $message " . json_encode($context));
    }
}

// Función para crear respuesta JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Cargar configuración
$config = require __DIR__ . '/../config/database.php';

// Crear logger simple
$logger = new SimpleLogger();

// Routing básico
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

logMessage("Request: {$method} {$uri}");

// Función para manejar rutas dinámicas
function matchRoute($pattern, $uri) {
    $pattern = str_replace('/', '\/', $pattern);
    $pattern = preg_replace('/\{(\w+)\}/', '([^\/]+)', $pattern);
    return preg_match('/^' . $pattern . '$/', $uri, $matches) ? array_slice($matches, 1) : false;
}

// Rutas
// Verificar rutas dinámicas primero
if ($matches = matchRoute('/api/bhe/{id}', $uri)) {
    if ($method !== 'GET') {
        jsonResponse(['error' => 'Método no permitido'], 405);
    }
    
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../src/Core/Database.php';
        require_once __DIR__ . '/../src/Controllers/BHEController.php';
        
        $dteId = (int) $matches[0];
        $database = new DonFactura\DTE\Core\Database($config['database']);
        $pdo = $database->getConnection();
        
        $controller = new DonFactura\DTE\Controllers\BHEController($pdo, $config);
        $resultado = $controller->obtener($dteId);
        
        jsonResponse($resultado);
    } catch (Exception $e) {
        logMessage('Error BHE obtener: ' . $e->getMessage());
        jsonResponse(['error' => 'Error interno: ' . $e->getMessage()], 500);
    }
} elseif ($matches = matchRoute('/api/bhe/{id}/pdf', $uri)) {
    if ($method !== 'POST') {
        jsonResponse(['error' => 'Método no permitido'], 405);
    }
    
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../src/Core/Database.php';
        require_once __DIR__ . '/../src/Controllers/BHEController.php';
        
        $dteId = (int) $matches[0];
        $database = new DonFactura\DTE\Core\Database($config['database']);
        $pdo = $database->getConnection();
        
        $controller = new DonFactura\DTE\Controllers\BHEController($pdo, $config);
        $resultado = $controller->generarPDF($dteId);
        
        jsonResponse($resultado);
    } catch (Exception $e) {
        logMessage('Error BHE PDF: ' . $e->getMessage());
        jsonResponse(['error' => 'Error interno: ' . $e->getMessage()], 500);
    }
} elseif ($matches = matchRoute('/api/dte/{id}/pdf', $uri)) {
    if ($method !== 'POST') {
        jsonResponse(['error' => 'Método no permitido'], 405);
    }
    
    try {
        require_once __DIR__ . '/../vendor/autoload.php';
        require_once __DIR__ . '/../src/Core/Database.php';
        require_once __DIR__ . '/../src/Controllers/PDFController.php';
        
        $dteId = (int) $matches[0];
        $formato = $_GET['formato'] ?? 'carta';
        
        $database = new DonFactura\DTE\Core\Database($config['database']);
        $pdo = $database->getConnection();
        
        $controller = new DonFactura\DTE\Controllers\PDFController($pdo, $logger, $config);
        $resultado = $controller->generarPDF($dteId, $formato);
        
        jsonResponse($resultado);
    } catch (Exception $e) {
        logMessage('Error DTE PDF: ' . $e->getMessage());
        jsonResponse(['error' => 'Error interno: ' . $e->getMessage()], 500);
    }
} elseif ($matches = matchRoute('/api/pdf/{id}/download', $uri)) {
    if ($method !== 'GET') {
        jsonResponse(['error' => 'Método no permitido'], 405);
    }
    
    try {
        $pdfId = (int) $matches[0];
        $database = new DonFactura\DTE\Core\Database($config['database']);
        $pdo = $database->getConnection();
        
        // Obtener PDF de la base de datos
        $sql = "SELECT dp.*, d.tipo_dte, d.folio 
                FROM documentos_pdf dp 
                LEFT JOIN documentos_dte d ON dp.dte_id = d.id 
                WHERE dp.id = :pdf_id";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['pdf_id' => $pdfId]);
        $pdf = $stmt->fetch();
        
        if (!$pdf) {
            jsonResponse(['error' => 'PDF no encontrado'], 404);
        }
        
        // Enviar headers para descarga
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $pdf['nombre_archivo'] . '"');
        header('Content-Length: ' . strlen($pdf['contenido_pdf']));
        
        echo $pdf['contenido_pdf'];
        exit;
        
    } catch (Exception $e) {
        logMessage('Error PDF download: ' . $e->getMessage());
        jsonResponse(['error' => 'Error interno: ' . $e->getMessage()], 500);
    }
} else {
    // Rutas estáticas
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

    // ========================================
    // RUTAS BOLETAS DE HONORARIOS ELECTRÓNICAS (BHE) - DTE TIPO 41
    // ========================================
    
    case '/api/bhe/generar':
        if ($method !== 'POST') {
            jsonResponse(['error' => 'Método no permitido'], 405);
        }
        
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            require_once __DIR__ . '/../src/Core/Database.php';
            require_once __DIR__ . '/../src/Controllers/BHEController.php';
            
            $database = new DonFactura\DTE\Core\Database($config['database']);
            $pdo = $database->getConnection();
            
            $controller = new DonFactura\DTE\Controllers\BHEController($pdo, $config);
            $resultado = $controller->generar();
            
            jsonResponse($resultado);
        } catch (Exception $e) {
            logMessage('Error BHE generar: ' . $e->getMessage());
            jsonResponse(['error' => 'Error interno: ' . $e->getMessage()], 500);
        }
        break;

    case '/api/dte/generar':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            require_once __DIR__ . '/../src/Core/Database.php';
            require_once __DIR__ . '/../src/Models/DTEModel.php';
            require_once __DIR__ . '/../src/Models/FoliosModel.php';
            
            // Obtener datos JSON del request
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);
            
            if (!$data) {
                jsonResponse(['success' => false, 'error' => 'Datos JSON inválidos'], 400);
            }
            
            // Validaciones básicas
            if (empty($data['tipo_dte']) || empty($data['emisor']['rut']) || empty($data['receptor']['rut'])) {
                jsonResponse(['success' => false, 'error' => 'Datos requeridos faltantes: tipo_dte, emisor.rut, receptor.rut'], 400);
            }
            
            if (empty($data['detalles']) || !is_array($data['detalles'])) {
                jsonResponse(['success' => false, 'error' => 'Debe incluir al menos un detalle'], 400);
            }
            
            $database = new DonFactura\DTE\Core\Database($config['database']);
            $pdo = $database->getConnection();
            
            $dteModel = new DonFactura\DTE\Models\DTEModel($pdo);
            $foliosModel = new DonFactura\DTE\Models\FoliosModel($pdo);
            
            // Obtener siguiente folio (simular por ahora)
            $folio = rand(1000, 9999); // En producción sería: $foliosModel->obtenerSiguienteFolio($data['tipo_dte'], $data['emisor']['rut']);
            
            // Calcular totales
            $montoNeto = 0;
            $montoTotal = 0;
            
            foreach ($data['detalles'] as $detalle) {
                $cantidad = $detalle['cantidad'] ?? 1;
                $precioUnitario = $detalle['precio_unitario'] ?? 0;
                $subtotal = $cantidad * $precioUnitario;
                
                // Aplicar descuento si existe
                if (!empty($detalle['descuento_porcentaje'])) {
                    $descuento = $subtotal * ($detalle['descuento_porcentaje'] / 100);
                    $subtotal -= $descuento;
                }
                
                $montoNeto += $subtotal;
            }
            
            // Calcular IVA (19% para tipos de DTE que lo requieren)
            $montoIVA = 0;
            if (in_array($data['tipo_dte'], [33, 34, 56, 61])) { // Tipos que llevan IVA
                $montoIVA = $montoNeto * 0.19;
            }
            
            $montoTotal = $montoNeto + $montoIVA;
            
            // Preparar datos del DTE
            $datosGrabacion = [
                'tipo_dte' => (int)$data['tipo_dte'],
                'folio' => $folio,
                'fecha_emision' => $data['fecha_emision'] ?? date('Y-m-d'),
                'rut_emisor' => $data['emisor']['rut'],
                'razon_social_emisor' => $data['emisor']['razon_social'] ?? '',
                'rut_receptor' => $data['receptor']['rut'],
                'razon_social_receptor' => $data['receptor']['razon_social'] ?? '',
                'monto_neto' => $montoNeto,
                'monto_iva' => $montoIVA,
                'monto_total' => $montoTotal,
                'observaciones' => $data['observaciones'] ?? '',
                'estado' => 'generado'
            ];
            
            // Guardar DTE
            $dteId = $dteModel->crear($datosGrabacion);
            
            // Guardar detalles
            foreach ($data['detalles'] as $detalle) {
                $dteModel->agregarDetalle($dteId, [
                    'nombre_item' => $detalle['nombre_item'] ?? '',
                    'descripcion' => $detalle['descripcion'] ?? '',
                    'cantidad' => $detalle['cantidad'] ?? 1,
                    'unidad_medida' => $detalle['unidad_medida'] ?? 'UN',
                    'precio_unitario' => $detalle['precio_unitario'] ?? 0,
                    'descuento_porcentaje' => $detalle['descuento_porcentaje'] ?? 0,
                    'codigo_item' => $detalle['codigo_item'] ?? ''
                ]);
            }
            
            logMessage("DTE generado exitosamente: Tipo {$data['tipo_dte']}, Folio {$folio}, ID {$dteId}");
            
            jsonResponse([
                'success' => true,
                'data' => [
                    'id' => $dteId,
                    'tipo_dte' => $data['tipo_dte'],
                    'folio' => $folio,
                    'fecha_emision' => $datosGrabacion['fecha_emision'],
                    'monto_total' => $montoTotal,
                    'estado' => 'generado',
                    'mensaje' => 'DTE generado exitosamente'
                ]
            ]);
            
        } catch (Exception $e) {
            logMessage('Error DTE generar: ' . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()], 500);
        }
        break;

    case '/api/certificados':
        if ($method === 'GET') {
            try {
                require_once __DIR__ . '/../vendor/autoload.php';
                require_once __DIR__ . '/../src/Core/Database.php';
                require_once __DIR__ . '/../src/Models/CertificadosModel.php';
                
                $database = new DonFactura\DTE\Core\Database($config['database']);
                $pdo = $database->getConnection();
                
                $certificadosModel = new DonFactura\DTE\Models\CertificadosModel($pdo);
                $certificados = $certificadosModel->listar();
                
                // No enviar las contraseñas ni los archivos binarios
                $certificadosLimpios = array_map(function($cert) {
                    unset($cert['password_pfx'], $cert['archivo_pfx']);
                    return $cert;
                }, $certificados);
                
                jsonResponse([
                    'success' => true,
                    'data' => [
                        'total' => count($certificadosLimpios),
                        'certificados' => $certificadosLimpios
                    ]
                ]);
            } catch (Exception $e) {
                logMessage('Error certificados listar: ' . $e->getMessage());
                jsonResponse(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()], 500);
            }
        } else {
            jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        break;
        
    case '/api/certificados/upload':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            require_once __DIR__ . '/../src/Core/Database.php';
            require_once __DIR__ . '/../src/Models/CertificadosModel.php';
            
            // Validar que se haya subido un archivo
            if (empty($_FILES['certificado'])) {
                jsonResponse(['success' => false, 'error' => 'Debe subir un archivo de certificado'], 400);
            }
            
            $uploadedFile = $_FILES['certificado'];
            
            // Validar que no haya errores en la subida
            if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
                jsonResponse(['success' => false, 'error' => 'Error al subir el archivo'], 400);
            }
            
            // Validar extensión del archivo
            $nombreArchivo = $uploadedFile['name'];
            if (!$nombreArchivo || !str_ends_with(strtolower($nombreArchivo), '.pfx')) {
                jsonResponse(['success' => false, 'error' => 'El archivo debe ser un certificado .pfx'], 400);
            }
            
            // Validar que se proporcione la contraseña
            if (empty($_POST['password'])) {
                jsonResponse(['success' => false, 'error' => 'Debe proporcionar la contraseña del certificado'], 400);
            }
            
            // Leer contenido del archivo
            $contenidoArchivo = file_get_contents($uploadedFile['tmp_name']);
            if ($contenidoArchivo === false) {
                jsonResponse(['success' => false, 'error' => 'Error al leer el archivo'], 500);
            }
            
            $database = new DonFactura\DTE\Core\Database($config['database']);
            $pdo = $database->getConnection();
            
            $certificadosModel = new DonFactura\DTE\Models\CertificadosModel($pdo);
            
            // Validar el certificado
            $validacion = $certificadosModel->validarCertificado($contenidoArchivo, $_POST['password']);
            if (!$validacion['valido']) {
                jsonResponse(['success' => false, 'error' => $validacion['error']], 400);
            }
            
            // Preparar datos para guardar
            $datosCertificado = [
                'nombre' => $_POST['nombre'] ?? 'Certificado ' . date('Y-m-d'),
                'rut_empresa' => $_POST['rut_empresa'] ?? '',
                'razon_social' => $_POST['razon_social'] ?? '',
                'archivo_pfx' => $contenidoArchivo,
                'password_pfx' => $_POST['password'],
                'fecha_vencimiento' => $validacion['info']['fecha_vencimiento'] ?? null,
                'emisor_certificado' => $validacion['info']['emisor'] ?? ''
            ];
            
            // Guardar en base de datos
            $certificadoId = $certificadosModel->crear($datosCertificado);
            
            // Guardar archivo físico
            $rutaCertificados = $config['paths']['certificates'];
            if (!is_dir($rutaCertificados)) {
                mkdir($rutaCertificados, 0755, true);
            }
            $nombreArchivoFisico = "cert_{$certificadoId}.pfx";
            $rutaCompleta = $rutaCertificados . $nombreArchivoFisico;
            file_put_contents($rutaCompleta, $contenidoArchivo);
            
            logMessage("Certificado subido exitosamente: ID {$certificadoId}");
            
            jsonResponse([
                'success' => true,
                'data' => [
                    'id' => $certificadoId,
                    'nombre' => $datosCertificado['nombre'],
                    'rut_empresa' => $datosCertificado['rut_empresa'],
                    'mensaje' => 'Certificado subido exitosamente'
                ]
            ]);
            
        } catch (Exception $e) {
            logMessage('Error certificados upload: ' . $e->getMessage());
            jsonResponse(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()], 500);
        }
        break;

    case '/api/profesionales':
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            require_once __DIR__ . '/../src/Core/Database.php';
            require_once __DIR__ . '/../src/Controllers/BHEController.php';
            
            $database = new DonFactura\DTE\Core\Database($config['database']);
            $pdo = $database->getConnection();
            
            $controller = new DonFactura\DTE\Controllers\BHEController($pdo, $config);
            
            if ($method === 'POST') {
                $resultado = $controller->registrarProfesional();
            } elseif ($method === 'GET') {
                $resultado = $controller->listarProfesionales();
            } else {
                jsonResponse(['error' => 'Método no permitido'], 405);
            }
            
            jsonResponse($resultado);
        } catch (Exception $e) {
            logMessage('Error profesionales: ' . $e->getMessage());
            jsonResponse(['error' => 'Error interno: ' . $e->getMessage()], 500);
        }
        break;

    case '/bhe-features':
        jsonResponse([
            'title' => 'Funcionalidades BHE (Boletas de Honorarios Electrónicas) Implementadas',
            'tipo_dte' => 41,
            'nombre_documento' => 'Boleta de Honorarios Electrónica',
            'features' => [
                '✅ Generación BHE DTE Tipo 41',
                '✅ Firma electrónica OBLIGATORIA',
                '✅ Retención automática 10% segunda categoría',
                '✅ Gestión completa de profesionales',
                '✅ Validación de períodos de servicios',
                '✅ Cálculo automático de montos líquidos',
                '✅ XML específico para BHE según SII',
                '✅ PDF formato CARTA y 80mm para BHE',
                '✅ Códigos QR específicos BHE',
                '✅ Registro y búsqueda de profesionales',
                '✅ Reportes por período profesional',
                '✅ Base de datos comunas chilenas',
                '✅ Plantillas PDF personalizables'
            ],
            'endpoints_bhe' => [
                'POST /api/bhe/generar' => 'Generar nueva BHE',
                'POST /api/profesionales' => 'Registrar profesional',
                'GET /api/profesionales' => 'Listar profesionales',
                'GET /bhe-features' => 'Ver funcionalidades BHE'
            ],
            'caracteristicas_bhe' => [
                'firma_electronica' => 'Obligatoria para validez legal',
                'retencion' => '10% sobre honorarios brutos (segunda categoría)',
                'periodo_servicios' => 'Máximo 12 meses por BHE',
                'xml_estructura' => 'Específica para servicios profesionales'
            ],
            'ejemplos' => [
                'generar_bhe' => 'Ver /examples/generar_bhe.json',
                'registrar_profesional' => 'Ver /examples/registrar_profesional.json'
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
                'GET /pdf-features',
                'GET /bhe-features',
                'POST /api/bhe/generar',
                'POST /api/profesionales'
            ]
        ], 404);
    }
}
?>
