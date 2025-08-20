<?php
/**
 * API DTE en PHP puro - Sin frameworks
 * Manejo de certificados y DTE
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

// Función para log simple mejorada
function logMessage($message, $level = 'INFO') {
    $logFile = __DIR__ . '/../storage/logs/app.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] [{$level}] {$message}\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Función para log de errores específicos
function logError($message, $exception = null) {
    $errorDetails = $message;
    if ($exception) {
        $errorDetails .= " - Exception: " . $exception->getMessage();
        $errorDetails .= " - File: " . $exception->getFile() . ":" . $exception->getLine();
        $errorDetails .= " - Trace: " . $exception->getTraceAsString();
    }
    logMessage($errorDetails, 'ERROR');
}

// Función para crear respuesta JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Función para conectar a base de datos
function getDatabase() {
    global $config;
    try {
        $dsn = "mysql:host={$config['database']['host']};dbname={$config['database']['database']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['database']['username'], $config['database']['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        logError('Database connection error', $e);
        jsonResponse(['error' => 'Database connection failed'], 500);
    }
}

// Routing básico
$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

logMessage("Request: {$method} {$uri}");

// Obtener la ruta sin el directorio base
$basePath = '/api.php';
$path = str_replace($basePath, '', $uri);

// Debug: Log the processed path (commented out for production)
// logMessage("Processed path: '{$path}' from URI: '{$uri}'");

// Función para extraer parámetros de rutas dinámicas
function extractRouteParams($pattern, $path) {
    $patternParts = explode('/', trim($pattern, '/'));
    $pathParts = explode('/', trim($path, '/'));
    
    if (count($patternParts) !== count($pathParts)) {
        return null;
    }
    
    $params = [];
    for ($i = 0; $i < count($patternParts); $i++) {
        if (strpos($patternParts[$i], '{') === 0 && strpos($patternParts[$i], '}') === strlen($patternParts[$i]) - 1) {
            $paramName = substr($patternParts[$i], 1, -1);
            $params[$paramName] = $pathParts[$i];
        } elseif ($patternParts[$i] !== $pathParts[$i]) {
            return null;
        }
    }
    
    return $params;
}

// Endpoints
switch (true) {
    case $path === '/':
        jsonResponse([
            'message' => 'API DTE - Documentos Tributarios Electrónicos Chile',
            'version' => '1.0.0 (PHP Puro)',
            'status' => 'active',
            'endpoints' => [
                'GET /health' => 'Estado del sistema',
                'GET /certificados' => 'Listar certificados',
                'POST /certificados/upload' => 'Subir certificado PFX',
                'POST /dte/generar' => 'Generar DTE',
                'GET /bhe-features' => 'Funcionalidades BHE',
                'GET /pdf-features' => 'Funcionalidades PDF'
            ]
        ]);
        break;

    case $path === '/health':
        try {
            $pdo = getDatabase();
            $stmt = $pdo->query('SELECT 1');
            $dbStatus = $stmt ? 'connected' : 'disconnected';
        } catch (Exception $e) {
            $dbStatus = 'error: ' . $e->getMessage();
        }
        
        jsonResponse([
            'status' => 'ok',
            'mode' => 'php-pure',
            'timestamp' => date('c'),
            'database' => $dbStatus,
            'php_version' => PHP_VERSION,
            'memory_usage' => memory_get_usage(true),
            'extensions' => [
                'pdo' => extension_loaded('pdo'),
                'pdo_mysql' => extension_loaded('pdo_mysql'),
                'openssl' => extension_loaded('openssl'),
                'curl' => extension_loaded('curl'),
                'json' => extension_loaded('json'),
                'mbstring' => extension_loaded('mbstring'),
                'fileinfo' => extension_loaded('fileinfo')
            ]
        ]);
        break;

    case $path === '/certificados':
        if ($method === 'GET') {
            try {
                $pdo = getDatabase();
                $stmt = $pdo->query('SELECT id, nombre, rut_empresa, razon_social, fecha_vencimiento, activo, created_at FROM certificados WHERE activo = 1 ORDER BY created_at DESC');
                $certificados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                jsonResponse([
                    'success' => true,
                    'data' => [
                        'total' => count($certificados),
                        'certificados' => $certificados
                    ]
                ]);
            } catch (Exception $e) {
                logError('Error listando certificados', $e);
                jsonResponse(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()], 500);
            }
        } else {
            jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        break;

    case $path === '/certificados/upload':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        
        try {
            logMessage("Iniciando upload de certificado");
            
            // Validar que se haya subido un archivo
            if (empty($_FILES['certificado'])) {
                logMessage("Error: No se subió archivo de certificado", 'WARNING');
                jsonResponse(['success' => false, 'error' => 'Debe subir un archivo de certificado'], 400);
            }
            
            $uploadedFile = $_FILES['certificado'];
            logMessage("Archivo recibido: " . $uploadedFile['name'] . " (" . $uploadedFile['size'] . " bytes)");
            
            // Validar que no haya errores en la subida
            if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
                $uploadError = "Error de upload: " . $uploadedFile['error'];
                logMessage($uploadError, 'ERROR');
                jsonResponse(['success' => false, 'error' => 'Error al subir el archivo: ' . $uploadedFile['error']], 400);
            }
            
            // Validar extensión del archivo
            $nombreArchivo = $uploadedFile['name'];
            if (!$nombreArchivo || !str_ends_with(strtolower($nombreArchivo), '.pfx')) {
                logMessage("Error: Archivo no es .pfx: " . $nombreArchivo, 'WARNING');
                jsonResponse(['success' => false, 'error' => 'El archivo debe ser un certificado .pfx'], 400);
            }
            
            // Validar que se proporcione la contraseña
            if (empty($_POST['password'])) {
                logMessage("Error: No se proporcionó contraseña", 'WARNING');
                jsonResponse(['success' => false, 'error' => 'Debe proporcionar la contraseña del certificado'], 400);
            }
            
            // Validar campos requeridos
            if (empty($_POST['rut_empresa'])) {
                logMessage("Error: No se proporcionó RUT de empresa", 'WARNING');
                jsonResponse(['success' => false, 'error' => 'Debe proporcionar el RUT de la empresa'], 400);
            }
            
            if (empty($_POST['razon_social'])) {
                logMessage("Error: No se proporcionó razón social", 'WARNING');
                jsonResponse(['success' => false, 'error' => 'Debe proporcionar la razón social'], 400);
            }
            
            // Validar fecha de vencimiento si se proporciona
            if (!empty($_POST['fecha_vencimiento'])) {
                $fechaVencimiento = $_POST['fecha_vencimiento'];
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVencimiento)) {
                    logMessage("Error: Formato de fecha inválido: " . $fechaVencimiento, 'WARNING');
                    jsonResponse(['success' => false, 'error' => 'Formato de fecha inválido. Use YYYY-MM-DD'], 400);
                }
                
                // Verificar que la fecha no sea anterior a hoy
                if (strtotime($fechaVencimiento) < strtotime(date('Y-m-d'))) {
                    logMessage("Error: Fecha de vencimiento anterior a hoy: " . $fechaVencimiento, 'WARNING');
                    jsonResponse(['success' => false, 'error' => 'La fecha de vencimiento no puede ser anterior a hoy'], 400);
                }
            }
            
            // Leer contenido del archivo
            $contenidoArchivo = file_get_contents($uploadedFile['tmp_name']);
            if ($contenidoArchivo === false) {
                logMessage("Error: No se pudo leer el archivo temporal", 'ERROR');
                jsonResponse(['success' => false, 'error' => 'Error al leer el archivo'], 500);
            }
            
            logMessage("Archivo leído correctamente: " . strlen($contenidoArchivo) . " bytes");
            
            // Validar certificado básico (verificar que sea un archivo válido)
            if (strlen($contenidoArchivo) < 100) {
                logMessage("Error: Archivo muy pequeño, no parece ser certificado válido", 'WARNING');
                jsonResponse(['success' => false, 'error' => 'El archivo parece no ser un certificado válido'], 400);
            }
            
            $pdo = getDatabase();
            logMessage("Conexión a base de datos establecida");
            
            // Preparar datos para guardar (CORREGIDO: sin emisor_certificado)
            $datosCertificado = [
                'nombre' => $_POST['nombre'] ?? 'Certificado ' . date('Y-m-d'),
                'rut_empresa' => $_POST['rut_empresa'] ?? '',
                'razon_social' => $_POST['razon_social'] ?? '',
                'archivo_pfx' => $contenidoArchivo,
                'password_pfx' => $_POST['password'],
                'fecha_vencimiento' => $_POST['fecha_vencimiento'] ?? date('Y-m-d', strtotime('+2 years'))
            ];
            
            logMessage("Datos preparados para inserción: " . json_encode([
                'nombre' => $datosCertificado['nombre'],
                'rut_empresa' => $datosCertificado['rut_empresa'],
                'razon_social' => $datosCertificado['razon_social'],
                'fecha_vencimiento' => $datosCertificado['fecha_vencimiento']
            ]));
            
            // Guardar en base de datos (CORREGIDO: sin emisor_certificado)
            $stmt = $pdo->prepare('
                INSERT INTO certificados (nombre, rut_empresa, razon_social, archivo_pfx, password_pfx, fecha_vencimiento, activo, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ');
            
            $stmt->execute([
                $datosCertificado['nombre'],
                $datosCertificado['rut_empresa'],
                $datosCertificado['razon_social'],
                $datosCertificado['archivo_pfx'],
                $datosCertificado['password_pfx'],
                $datosCertificado['fecha_vencimiento']
            ]);
            
            $certificadoId = $pdo->lastInsertId();
            logMessage("Certificado guardado en BD con ID: {$certificadoId}");
            
            // Guardar archivo físico
            $rutaCertificados = $config['paths']['certificates'];
            if (!is_dir($rutaCertificados)) {
                mkdir($rutaCertificados, 0755, true);
                logMessage("Directorio de certificados creado: {$rutaCertificados}");
            }
            $nombreArchivoFisico = "cert_{$certificadoId}.pfx";
            $rutaCompleta = $rutaCertificados . $nombreArchivoFisico;
            $archivoGuardado = file_put_contents($rutaCompleta, $contenidoArchivo);
            
            if ($archivoGuardado === false) {
                logMessage("Error: No se pudo guardar archivo físico en: {$rutaCompleta}", 'ERROR');
            } else {
                logMessage("Archivo físico guardado: {$rutaCompleta} ({$archivoGuardado} bytes)");
            }
            
            logMessage("Certificado subido exitosamente: ID {$certificadoId}", 'SUCCESS');
            
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
            logError('Error en upload de certificados', $e);
            jsonResponse(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()], 500);
        }
        break;

    case $path === '/dte/generar':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        
        try {
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
            
            $pdo = getDatabase();
            
            // Obtener siguiente folio (simular por ahora)
            $folio = rand(1000, 9999);
            
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
            
            // Guardar DTE
            $stmt = $pdo->prepare('
                INSERT INTO documentos_dte (tipo_dte, folio, fecha_emision, rut_emisor, razon_social_emisor, rut_receptor, razon_social_receptor, monto_neto, monto_iva, monto_total, observaciones, estado, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ');
            
            $stmt->execute([
                $data['tipo_dte'],
                $folio,
                $data['fecha_emision'] ?? date('Y-m-d'),
                $data['emisor']['rut'],
                $data['emisor']['razon_social'] ?? '',
                $data['receptor']['rut'],
                $data['receptor']['razon_social'] ?? '',
                $montoNeto,
                $montoIVA,
                $montoTotal,
                $data['observaciones'] ?? '',
                'generado'
            ]);
            
            $dteId = $pdo->lastInsertId();
            
            // Guardar detalles
            foreach ($data['detalles'] as $detalle) {
                $stmt = $pdo->prepare('
                    INSERT INTO dte_detalles (dte_id, nombre_item, descripcion, cantidad, unidad_medida, precio_unitario, descuento_porcentaje, codigo_item) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ');
                
                $stmt->execute([
                    $dteId,
                    $detalle['nombre_item'] ?? '',
                    $detalle['descripcion'] ?? '',
                    $detalle['cantidad'] ?? 1,
                    $detalle['unidad_medida'] ?? 'UN',
                    $detalle['precio_unitario'] ?? 0,
                    $detalle['descuento_porcentaje'] ?? 0,
                    $detalle['codigo_item'] ?? ''
                ]);
            }
            
            // GENERAR XML DEL DTE
            try {
                // Cargar el generador de XML
                require_once __DIR__ . '/../vendor/autoload.php';
                $xmlGenerator = new \DonFactura\DTE\Services\DTEXMLGenerator($config);
                
                // Generar XML
                $xmlContent = $xmlGenerator->generar((int)$data['tipo_dte'], $data, $folio);
                
                // Guardar XML en archivo
                $xmlFileName = "dte_{$data['tipo_dte']}_{$folio}_{$dteId}.xml";
                $xmlPath = $config['paths']['xml'] . $xmlFileName;
                
                // Asegurar que el directorio existe
                if (!is_dir($config['paths']['xml'])) {
                    mkdir($config['paths']['xml'], 0755, true);
                }
                
                // Guardar archivo XML
                $xmlSaved = file_put_contents($xmlPath, $xmlContent);
                
                if ($xmlSaved === false) {
                    logMessage("Error: No se pudo guardar archivo XML en: {$xmlPath}", 'ERROR');
                } else {
                    logMessage("XML guardado exitosamente: {$xmlPath} ({$xmlSaved} bytes)");
                    
                    // Actualizar registro en BD con XML generado
                    $stmt = $pdo->prepare('
                        UPDATE documentos_dte SET xml_dte = ? WHERE id = ?
                    ');
                    $stmt->execute([$xmlContent, $dteId]);
                }
                
            } catch (Exception $xmlError) {
                logError('Error generando XML del DTE', $xmlError);
                // No fallar la generación del DTE por error en XML
            }
            
            logMessage("DTE generado exitosamente: Tipo {$data['tipo_dte']}, Folio {$folio}, ID {$dteId}");
            
            jsonResponse([
                'success' => true,
                'data' => [
                    'id' => $dteId,
                    'tipo_dte' => $data['tipo_dte'],
                    'folio' => $folio,
                    'fecha_emision' => $data['fecha_emision'] ?? date('Y-m-d'),
                    'monto_total' => $montoTotal,
                    'estado' => 'generado',
                    'xml_generado' => isset($xmlContent),
                    'xml_path' => $xmlPath ?? null,
                    'mensaje' => 'DTE generado exitosamente'
                ]
            ]);
            
        } catch (Exception $e) {
            logError('Error DTE generar', $e);
            jsonResponse(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()], 500);
        }
        break;

    case $path === '/bhe-features':
        jsonResponse([
            'success' => true,
            'data' => [
                'tipo_dte' => 41,
                'nombre' => 'Boleta de Honorarios Electrónica',
                'caracteristicas' => [
                    'Retención obligatoria del 10%',
                    'Sin IVA',
                    'Requiere certificado profesional',
                    'Categoría de impuesto: Segunda Categoría'
                ],
                'endpoints' => [
                    'POST /bhe/generar' => 'Generar BHE',
                    'GET /bhe/{id}' => 'Obtener BHE',
                    'POST /bhe/{id}/pdf' => 'Generar PDF'
                ]
            ]
        ]);
        break;

    case $path === '/pdf-features':
        jsonResponse([
            'success' => true,
            'data' => [
                'formatos_disponibles' => [
                    'carta' => 'Formato carta estándar',
                    '80mm' => 'Formato térmico 80mm'
                ],
                'caracteristicas' => [
                    'Código QR 2D incluido',
                    'Cumple especificaciones SII',
                    'Firma digital integrada',
                    'Múltiples formatos de salida'
                ],
                'endpoints' => [
                    'POST /dte/{id}/pdf' => 'Generar PDF de DTE',
                    'GET /pdf/{id}/download' => 'Descargar PDF'
                ]
            ]
        ]);
        break;

    case ($params = extractRouteParams('/dte/{id}/pdf', $path)) !== null:
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        
        try {
            // Extraer ID del DTE de la URL
            $dteId = (int) $params['id'];
            $formato = $_GET['formato'] ?? 'carta';
            
            // Validar formato
            if (!in_array($formato, ['carta', '80mm'])) {
                jsonResponse(['success' => false, 'error' => 'Formato debe ser "carta" o "80mm"'], 400);
            }
            
            logMessage("Generando PDF para DTE ID: {$dteId}, Formato: {$formato}");
            
            // Cargar generador de PDF
            require_once __DIR__ . '/../vendor/autoload.php';
            $pdfGenerator = new \DonFactura\DTE\Services\PDFGenerator($pdo, $config);
            
            // Generar PDF
            $resultado = $pdfGenerator->generarPDF($dteId, $formato);
            
            if (!$resultado['success']) {
                logError('Error generando PDF', new Exception($resultado['error']));
                jsonResponse(['success' => false, 'error' => $resultado['error']], 500);
            }
            
            logMessage("PDF generado exitosamente: ID {$resultado['pdf_id']}");
            
            jsonResponse([
                'success' => true,
                'data' => [
                    'pdf_id' => $resultado['pdf_id'],
                    'formato' => $resultado['formato'],
                    'nombre_archivo' => $resultado['nombre_archivo'],
                    'url_descarga' => "/api.php/pdf/{$resultado['pdf_id']}/download",
                    'codigo_qr' => $resultado['codigo_qr'],
                    'mensaje' => 'PDF generado exitosamente'
                ]
            ]);
            
        } catch (Exception $e) {
            logError('Error en generación de PDF', $e);
            jsonResponse(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()], 500);
        }
        break;

    case ($params = extractRouteParams('/pdf/{id}/download', $path)) !== null:
        if ($method !== 'GET') {
            jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        
        try {
            // Extraer ID del PDF de la URL
            $pdfId = (int) $params['id'];
            
            logMessage("Descargando PDF ID: {$pdfId}");
            
            // Obtener PDF de la base de datos
            $stmt = $pdo->prepare('
                SELECT dp.*, d.tipo_dte, d.folio 
                FROM documentos_pdf dp 
                INNER JOIN documentos_dte d ON dp.dte_id = d.id 
                WHERE dp.id = ?
            ');
            $stmt->execute([$pdfId]);
            $pdf = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pdf) {
                jsonResponse(['success' => false, 'error' => 'PDF no encontrado'], 404);
            }
            
            // Verificar si existe el archivo físico
            $rutaArchivo = $pdf['ruta_archivo'];
            if (!file_exists($rutaArchivo)) {
                logMessage("Archivo PDF no encontrado: {$rutaArchivo}", 'WARNING');
                jsonResponse(['success' => false, 'error' => 'Archivo PDF no encontrado en el servidor'], 404);
            }
            
            // Preparar headers para descarga
            $nombreArchivo = $pdf['nombre_archivo'];
            $contenido = file_get_contents($rutaArchivo);
            
            if ($contenido === false) {
                logError('Error leyendo archivo PDF', new Exception("No se pudo leer: {$rutaArchivo}"));
                jsonResponse(['success' => false, 'error' => 'Error al leer archivo PDF'], 500);
            }
            
            logMessage("PDF descargado exitosamente: {$nombreArchivo} (" . strlen($contenido) . " bytes)");
            
            // Enviar archivo como descarga
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
            header('Content-Length: ' . strlen($contenido));
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            
            echo $contenido;
            exit;
            
        } catch (Exception $e) {
            logError('Error en descarga de PDF', $e);
            jsonResponse(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()], 500);
        }
        break;

    case ($params = extractRouteParams('/dte/{id}/qr', $path)) !== null:
        if ($method !== 'GET') {
            jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        
        try {
            // Extraer ID del DTE de la URL
            $dteId = (int) $params['id'];
            
            logMessage("Generando QR para DTE ID: {$dteId}");
            
            // Obtener datos del DTE
            $stmt = $pdo->prepare('
                SELECT tipo_dte, folio, fecha_emision, rut_emisor, rut_receptor, monto_total 
                FROM documentos_dte 
                WHERE id = ?
            ');
            $stmt->execute([$dteId]);
            $dte = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$dte) {
                jsonResponse(['success' => false, 'error' => 'DTE no encontrado'], 404);
            }
            
            // Cargar generador de QR
            require_once __DIR__ . '/../vendor/autoload.php';
            $qrGenerator = new \DonFactura\DTE\Services\QRCodeGenerator();
            
            // Generar código QR según especificaciones SII
            $qrData = sprintf(
                "%s;%s;%d;%s;%s;%d",
                str_replace(['.', '-'], '', $dte['rut_emisor']),
                $dte['tipo_dte'],
                $dte['folio'],
                date('Ymd', strtotime($dte['fecha_emision'])),
                str_replace(['.', '-'], '', $dte['rut_receptor']),
                (int)$dte['monto_total']
            );
            
            // Validar formato SII
            if (!$qrGenerator->validarFormatoSII($qrData)) {
                logMessage("Formato QR inválido para DTE {$dteId}: {$qrData}", 'WARNING');
                jsonResponse(['success' => false, 'error' => 'Formato QR inválido'], 500);
            }
            
            // Generar imagen QR
            $qrImageBase64 = $qrGenerator->generarQR($qrData);
            
            logMessage("QR generado exitosamente para DTE {$dteId}");
            
            jsonResponse([
                'success' => true,
                'data' => [
                    'dte_id' => $dteId,
                    'qr_data' => $qrData,
                    'qr_image' => 'data:image/png;base64,' . $qrImageBase64,
                    'formato_sii' => true,
                    'mensaje' => 'Código QR generado según especificaciones SII'
                ]
            ]);
            
        } catch (Exception $e) {
            logError('Error generando QR', $e);
            jsonResponse(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()], 500);
        }
        break;

    case ($params = extractRouteParams('/dte/{id}/firmar', $path)) !== null:
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        
        try {
            $dteId = (int) $params['id'];
            $pdo = getDatabase();
            
            logMessage("Firmando DTE ID: {$dteId}");
            
            // Obtener DTE
            $stmt = $pdo->prepare('
                SELECT id, tipo_dte, folio, rut_emisor, xml_dte, estado 
                FROM documentos_dte 
                WHERE id = ?
            ');
            $stmt->execute([$dteId]);
            $dte = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$dte) {
                jsonResponse(['success' => false, 'error' => 'DTE no encontrado'], 404);
            }
            
            if ($dte['estado'] !== 'generado') {
                jsonResponse(['success' => false, 'error' => 'DTE debe estar en estado "generado" para ser firmado'], 400);
            }
            
            if (empty($dte['xml_dte'])) {
                jsonResponse(['success' => false, 'error' => 'DTE no tiene XML generado'], 400);
            }
            
            // Cargar servicios de firma digital
            require_once __DIR__ . '/../vendor/autoload.php';
            $digitalSignature = new \DonFactura\DTE\Services\DigitalSignature($config, $pdo);
            
            // Firmar XML
            $xmlFirmado = $digitalSignature->firmarDTE($dte['xml_dte'], $dte['rut_emisor']);
            
            if (!$xmlFirmado) {
                logError('Error al firmar DTE', new Exception("No se pudo firmar DTE {$dteId}"));
                jsonResponse(['success' => false, 'error' => 'Error al firmar el DTE'], 500);
            }
            
            // Guardar XML firmado en archivo
            $xmlFirmadoFileName = "dte_firmado_{$dte['tipo_dte']}_{$dte['folio']}_{$dteId}.xml";
            $xmlFirmadoPath = $config['paths']['xml'] . $xmlFirmadoFileName;
            
            $xmlFirmadoSaved = file_put_contents($xmlFirmadoPath, $xmlFirmado);
            
            if ($xmlFirmadoSaved === false) {
                logMessage("Error: No se pudo guardar XML firmado en: {$xmlFirmadoPath}", 'ERROR');
            } else {
                logMessage("XML firmado guardado: {$xmlFirmadoPath} ({$xmlFirmadoSaved} bytes)");
            }
            
            // Actualizar estado en BD
            $stmt = $pdo->prepare('
                UPDATE documentos_dte 
                SET xml_firmado = ?, estado = ?, fecha_firma = NOW() 
                WHERE id = ?
            ');
            $stmt->execute([$xmlFirmado, 'firmado', $dteId]);
            
            logMessage("DTE firmado exitosamente: ID {$dteId}");
            
            jsonResponse([
                'success' => true,
                'data' => [
                    'id' => $dteId,
                    'estado' => 'firmado',
                    'xml_firmado' => $xmlFirmado,
                    'xml_firmado_path' => $xmlFirmadoPath,
                    'fecha_firma' => date('Y-m-d H:i:s'),
                    'mensaje' => 'DTE firmado digitalmente exitosamente'
                ]
            ]);
            
        } catch (Exception $e) {
            logError('Error firmando DTE', $e);
            jsonResponse(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()], 500);
        }
        break;

    case ($params = extractRouteParams('/dte/{id}/enviar-sii', $path)) !== null:
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        
        try {
            $dteId = (int) $params['id'];
            $pdo = getDatabase();
            
            logMessage("Enviando DTE al SII - ID: {$dteId}");
            
            // Obtener DTE
            $stmt = $pdo->prepare('
                SELECT id, tipo_dte, folio, rut_emisor, xml_firmado, estado 
                FROM documentos_dte 
                WHERE id = ?
            ');
            $stmt->execute([$dteId]);
            $dte = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$dte) {
                jsonResponse(['success' => false, 'error' => 'DTE no encontrado'], 404);
            }
            
            if ($dte['estado'] !== 'firmado') {
                jsonResponse(['success' => false, 'error' => 'DTE debe estar firmado para ser enviado al SII'], 400);
            }
            
            if (empty($dte['xml_firmado'])) {
                jsonResponse(['success' => false, 'error' => 'DTE no tiene XML firmado'], 400);
            }
            
            // Cargar servicio SII
            require_once __DIR__ . '/../vendor/autoload.php';
            $siiService = new \DonFactura\DTE\Services\SIIService($config, $pdo);
            
            // Enviar al SII
            $respuestaSII = $siiService->enviarDTE($dte['xml_firmado'], $dte['rut_emisor']);
            
            if (!$respuestaSII['success']) {
                logError('Error enviando al SII', new Exception($respuestaSII['error']));
                jsonResponse(['success' => false, 'error' => 'Error al enviar al SII: ' . $respuestaSII['error']], 500);
            }
            
            // Actualizar estado en BD
            $stmt = $pdo->prepare('
                UPDATE documentos_dte 
                SET estado = ?, fecha_envio_sii = NOW(), respuesta_sii = ? 
                WHERE id = ?
            ');
            $stmt->execute(['enviado_sii', json_encode($respuestaSII), $dteId]);
            
            logMessage("DTE enviado al SII exitosamente: ID {$dteId}");
            
            jsonResponse([
                'success' => true,
                'data' => [
                    'id' => $dteId,
                    'estado' => 'enviado_sii',
                    'respuesta_sii' => $respuestaSII,
                    'fecha_envio' => date('Y-m-d H:i:s'),
                    'mensaje' => 'DTE enviado al SII exitosamente'
                ]
            ]);
            
        } catch (Exception $e) {
            logError('Error enviando al SII', $e);
            jsonResponse(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()], 500);
        }
        break;

    case ($params = extractRouteParams('/dte/{id}/estado-sii', $path)) !== null:
        if ($method !== 'GET') {
            jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        
        try {
            $dteId = (int) $params['id'];
            $pdo = getDatabase();
            
            logMessage("Consultando estado SII para DTE ID: {$dteId}");
            
            // Obtener DTE
            $stmt = $pdo->prepare('
                SELECT id, tipo_dte, folio, rut_emisor, estado 
                FROM documentos_dte 
                WHERE id = ?
            ');
            $stmt->execute([$dteId]);
            $dte = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$dte) {
                jsonResponse(['success' => false, 'error' => 'DTE no encontrado'], 404);
            }
            
            // Cargar servicio SII
            require_once __DIR__ . '/../vendor/autoload.php';
            $siiService = new \DonFactura\DTE\Services\SIIService($config, $pdo);
            
            // Consultar estado en SII
            $estadoSII = $siiService->consultarEstadoDTE($dte['tipo_dte'], $dte['folio'], $dte['rut_emisor']);
            
            if (!$estadoSII['success']) {
                logError('Error consultando estado SII', new Exception($estadoSII['error']));
                jsonResponse(['success' => false, 'error' => 'Error al consultar estado: ' . $estadoSII['error']], 500);
            }
            
            // Actualizar estado en BD si cambió
            if ($estadoSII['data']['estado_sii'] !== $dte['estado']) {
                $stmt = $pdo->prepare('
                    UPDATE documentos_dte 
                    SET estado = ?, fecha_actualizacion_sii = NOW() 
                    WHERE id = ?
                ');
                $stmt->execute([$estadoSII['data']['estado_sii'], $dteId]);
            }
            
            logMessage("Estado SII consultado para DTE {$dteId}: " . $estadoSII['data']['estado_sii']);
            
            jsonResponse([
                'success' => true,
                'data' => [
                    'dte_id' => $dteId,
                    'estado_actual' => $dte['estado'],
                    'estado_sii' => $estadoSII['data'],
                    'fecha_consulta' => date('Y-m-d H:i:s'),
                    'mensaje' => 'Estado consultado exitosamente'
                ]
            ]);
            
        } catch (Exception $e) {
            logError('Error consultando estado SII', $e);
            jsonResponse(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()], 500);
        }
        break;

    // ===== ENDPOINTS DE GESTIÓN CAF =====
    case $path === '/caf/disponibles':
        if ($method !== 'GET') {
            jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        try {
            $tipoDte = $_GET['tipo_dte'] ?? null;
            $rutEmisor = $_GET['rut_emisor'] ?? null;
            
            $pdo = getDatabase();
            require_once __DIR__ . '/../vendor/autoload.php';
            $cafService = new \DonFactura\DTE\Services\CAFService($config, $pdo);
            $resultado = $cafService->obtenerCAFDisponibles($tipoDte ? (int)$tipoDte : null, $rutEmisor);
            
            jsonResponse($resultado);
        } catch (Exception $e) {
            logError('Error obteniendo CAF disponibles', $e);
            jsonResponse(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()], 500);
        }
        break;

    case $path === '/caf/solicitar':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                jsonResponse(['success' => false, 'error' => 'Datos JSON inválidos'], 400);
            }
            
            if (empty($data['tipo_dte']) || empty($data['rut_emisor']) || empty($data['cantidad_folios'])) {
                jsonResponse(['success' => false, 'error' => 'tipo_dte, rut_emisor y cantidad_folios son requeridos'], 400);
            }
            
            $pdo = getDatabase();
            require_once __DIR__ . '/../vendor/autoload.php';
            $cafService = new \DonFactura\DTE\Services\CAFService($config, $pdo);
            $resultado = $cafService->solicitarCAF((int)$data['tipo_dte'], $data['rut_emisor'], (int)$data['cantidad_folios']);
            
            jsonResponse($resultado);
        } catch (Exception $e) {
            logError('Error solicitando CAF', $e);
            jsonResponse(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()], 500);
        }
        break;

    case $path === '/caf/verificar-estado':
        if ($method !== 'POST') {
            jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
        }
        try {
            $pdo = getDatabase();
            require_once __DIR__ . '/../vendor/autoload.php';
            $cafService = new \DonFactura\DTE\Services\CAFService($config, $pdo);
            $resultado = $cafService->verificarEstadoCAF();
            
            jsonResponse($resultado);
        } catch (Exception $e) {
            logError('Error verificando estado CAF', $e);
            jsonResponse(['success' => false, 'error' => 'Error interno: ' . $e->getMessage()], 500);
        }
        break;

    default:
        jsonResponse(['error' => 'Endpoint no encontrado'], 404);
        break;
}
?>
