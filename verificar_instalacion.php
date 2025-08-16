<?php
/**
 * Script de Verificación Post-Instalación
 * Sistema BHE - Boletas de Honorarios Electrónicas
 */

echo "🔍 VERIFICACIÓN INSTALACIÓN SISTEMA BHE 🔍\n";
echo str_repeat("=", 60) . "\n\n";

$checks = [];
$warnings = [];
$errors = [];

// Función auxiliar para checks
function addCheck($name, $status, $message = '', $type = 'info') {
    global $checks, $warnings, $errors;
    
    $icon = $status ? '✅' : '❌';
    $checks[] = "{$icon} {$name}" . ($message ? " - {$message}" : "");
    
    if (!$status) {
        if ($type === 'warning') {
            $warnings[] = $name;
        } else {
            $errors[] = $name;
        }
    }
}

// 1. VERIFICAR PHP Y EXTENSIONES
echo "1. VERIFICANDO PHP Y EXTENSIONES\n";
echo str_repeat("-", 40) . "\n";

$phpVersion = PHP_VERSION;
$phpOk = version_compare($phpVersion, '8.0.0', '>=');
addCheck("PHP Version ({$phpVersion})", $phpOk, $phpOk ? 'Compatible' : 'Requiere PHP 8.0+');

$extensions = [
    'pdo' => 'PDO Database',
    'pdo_mysql' => 'MySQL PDO Driver',
    'openssl' => 'OpenSSL',
    'curl' => 'cURL',
    'simplexml' => 'SimpleXML',
    'json' => 'JSON',
    'mbstring' => 'Multibyte String',
    'gd' => 'GD (para códigos QR)'
];

foreach ($extensions as $ext => $name) {
    $loaded = extension_loaded($ext);
    addCheck($name, $loaded, '', $loaded ? 'info' : 'warning');
}

echo "\n";

// 2. VERIFICAR ESTRUCTURA DE DIRECTORIOS
echo "2. VERIFICANDO ESTRUCTURA DE DIRECTORIOS\n";
echo str_repeat("-", 40) . "\n";

$directories = [
    'config' => 'Configuración',
    'src' => 'Código fuente',
    'src/Core' => 'Core classes',
    'src/Models' => 'Modelos',
    'src/Services' => 'Servicios',
    'src/Controllers' => 'Controladores',
    'public' => 'Archivos públicos',
    'storage' => 'Almacenamiento',
    'storage/certificates' => 'Certificados',
    'storage/generated' => 'Archivos generados',
    'storage/temp' => 'Archivos temporales',
    'storage/logs' => 'Logs del sistema',
    'vendor' => 'Dependencias',
    'examples' => 'Ejemplos'
];

foreach ($directories as $dir => $description) {
    $exists = is_dir($dir);
    $writable = $exists && is_writable($dir);
    
    if (strpos($dir, 'storage') === 0) {
        addCheck("{$description} ({$dir})", $exists && $writable, 
                $writable ? 'Escribible' : 'Sin permisos de escritura');
    } else {
        addCheck("{$description} ({$dir})", $exists);
    }
}

echo "\n";

// 3. VERIFICAR ARCHIVOS ESENCIALES
echo "3. VERIFICANDO ARCHIVOS ESENCIALES\n";
echo str_repeat("-", 40) . "\n";

$files = [
    'config/database.php' => 'Configuración BD',
    'vendor/autoload.php' => 'Autoloader',
    'public/.htaccess' => 'Configuración Apache',
    'public/index_basic.php' => 'Entry point API'
];

foreach ($files as $file => $description) {
    $exists = file_exists($file);
    addCheck("{$description} ({$file})", $exists);
}

echo "\n";

// 4. VERIFICAR CONFIGURACIÓN DE BASE DE DATOS
echo "4. VERIFICANDO CONFIGURACIÓN BASE DE DATOS\n";
echo str_repeat("-", 40) . "\n";

$configFile = 'config/database.php';
if (file_exists($configFile)) {
    try {
        $config = require $configFile;
        addCheck("Archivo de configuración", true, 'Cargado correctamente');
        
        if (isset($config['database'])) {
            $dbConfig = $config['database'];
            
            // Intentar conexión
            try {
                $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};charset={$dbConfig['charset']}";
                $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
                addCheck("Conexión a MySQL", true, "Host: {$dbConfig['host']}");
                
                // Verificar base de datos
                $stmt = $pdo->query("SHOW DATABASES LIKE '{$dbConfig['database']}'");
                $dbExists = $stmt->fetch() !== false;
                addCheck("Base de datos '{$dbConfig['database']}'", $dbExists);
                
                if ($dbExists) {
                    // Conectar a la base de datos específica
                    $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
                    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
                    
                    // Verificar tablas BHE
                    $tables = [
                        'boletas_honorarios_electronicas' => 'BHE principal',
                        'profesionales_bhe' => 'Profesionales',
                        'comunas_chile' => 'Comunas',
                        'folios' => 'Folios',
                        'certificados' => 'Certificados'
                    ];
                    
                    foreach ($tables as $table => $description) {
                        try {
                            $stmt = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
                            $count = $stmt->fetchColumn();
                            addCheck("Tabla {$description} ({$table})", true, "{$count} registros");
                        } catch (PDOException $e) {
                            addCheck("Tabla {$description} ({$table})", false, 'No existe');
                        }
                    }
                }
                
            } catch (PDOException $e) {
                addCheck("Conexión a MySQL", false, $e->getMessage());
            }
        } else {
            addCheck("Configuración de BD", false, 'Sección database no encontrada');
        }
        
    } catch (Exception $e) {
        addCheck("Configuración de BD", false, $e->getMessage());
    }
} else {
    addCheck("Archivo de configuración", false, 'No existe');
}

echo "\n";

// 5. VERIFICAR AUTOLOADER Y CLASES
echo "5. VERIFICANDO AUTOLOADER Y CLASES\n";
echo str_repeat("-", 40) . "\n";

if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
    addCheck("Autoloader", true, 'Cargado');
    
    $classes = [
        'DonFactura\\DTE\\Core\\Database' => 'src/Core/Database.php',
        'DonFactura\\DTE\\Models\\BHEModel' => 'src/Models/BHEModel.php',
        'DonFactura\\DTE\\Services\\BHEService' => 'src/Services/BHEService.php',
        'DonFactura\\DTE\\Controllers\\BHEController' => 'src/Controllers/BHEController.php'
    ];
    
    foreach ($classes as $class => $file) {
        $exists = file_exists($file);
        if ($exists) {
            try {
                require_once $file;
                addCheck("Clase {$class}", true);
            } catch (Exception $e) {
                addCheck("Clase {$class}", false, $e->getMessage());
            }
        } else {
            addCheck("Clase {$class}", false, 'Archivo no existe');
        }
    }
} else {
    addCheck("Autoloader", false, 'No existe');
}

echo "\n";

// 6. VERIFICAR CONFIGURACIÓN WEB
echo "6. VERIFICANDO CONFIGURACIÓN WEB\n";
echo str_repeat("-", 40) . "\n";

// Verificar si estamos en servidor web
$isWebServer = isset($_SERVER['SERVER_NAME']);
addCheck("Servidor web", $isWebServer, $isWebServer ? $_SERVER['SERVER_NAME'] : 'Ejecutando en CLI');

if ($isWebServer) {
    $documentRoot = $_SERVER['DOCUMENT_ROOT'];
    addCheck("Document Root", true, $documentRoot);
    
    $currentPath = dirname($_SERVER['SCRIPT_FILENAME']);
    $isInPublic = basename($currentPath) === 'public';
    addCheck("Ejecutándose desde /public", $isInPublic, $isInPublic ? 'Correcto' : 'Mover a /public', 'warning');
}

// Verificar mod_rewrite (si es Apache)
if (function_exists('apache_get_modules')) {
    $modRewrite = in_array('mod_rewrite', apache_get_modules());
    addCheck("mod_rewrite", $modRewrite, '', 'warning');
}

echo "\n";

// 7. MOSTRAR RESUMEN
echo "7. RESUMEN DE VERIFICACIÓN\n";
echo str_repeat("-", 40) . "\n";

$totalChecks = count($checks);
$totalErrors = count($errors);
$totalWarnings = count($warnings);
$successCount = $totalChecks - $totalErrors - $totalWarnings;

echo "Total verificaciones: {$totalChecks}\n";
echo "✅ Exitosas: {$successCount}\n";
echo "⚠️  Advertencias: {$totalWarnings}\n";
echo "❌ Errores: {$totalErrors}\n\n";

if ($totalErrors === 0 && $totalWarnings === 0) {
    echo "🎉 ¡INSTALACIÓN PERFECTA!\n";
    echo "El sistema BHE está completamente configurado.\n\n";
} elseif ($totalErrors === 0) {
    echo "✅ INSTALACIÓN BUENA\n";
    echo "El sistema BHE está funcional con algunas advertencias menores.\n\n";
} else {
    echo "❌ INSTALACIÓN INCOMPLETA\n";
    echo "Se encontraron errores que deben corregirse.\n\n";
}

// Mostrar errores críticos
if (!empty($errors)) {
    echo "🚨 ERRORES CRÍTICOS A CORREGIR:\n";
    foreach ($errors as $error) {
        echo "   • {$error}\n";
    }
    echo "\n";
}

// Mostrar advertencias
if (!empty($warnings)) {
    echo "⚠️  ADVERTENCIAS (OPCIONALES):\n";
    foreach ($warnings as $warning) {
        echo "   • {$warning}\n";
    }
    echo "\n";
}

// 8. PRÓXIMOS PASOS
echo "8. PRÓXIMOS PASOS\n";
echo str_repeat("-", 40) . "\n";

if ($totalErrors === 0) {
    echo "✅ Ejecutar configuración BHE:\n";
    echo "   php setup_bhe.php\n";
    echo "   php create_folios_bhe.php\n";
    echo "   php create_certificados_bhe.php\n\n";
    
    echo "✅ Verificar funcionamiento:\n";
    if ($isWebServer) {
        $protocol = isset($_SERVER['HTTPS']) ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $basePath = dirname($_SERVER['SCRIPT_NAME']);
        echo "   {$protocol}://{$host}{$basePath}/bhe-features\n\n";
    } else {
        echo "   http://localhost/donfactura/public/bhe-features\n\n";
    }
    
    echo "✅ Probar generación BHE:\n";
    echo "   php test_bhe_system.php\n\n";
} else {
    echo "❌ Corregir errores críticos antes de continuar\n";
    echo "❌ Verificar instalación de dependencias\n";
    echo "❌ Revisar configuración de base de datos\n";
    echo "❌ Verificar permisos de directorios\n\n";
}

echo "📋 DOCUMENTACIÓN COMPLETA:\n";
echo "   • MANUAL_INSTALACION_BHE.md\n";
echo "   • FUNCIONALIDADES_BHE_IMPLEMENTADAS.md\n";
echo "   • RESUMEN_SISTEMA_BHE_COMPLETO.md\n\n";

// 9. INFORMACIÓN DEL SISTEMA
echo "9. INFORMACIÓN DEL SISTEMA\n";
echo str_repeat("-", 40) . "\n";

echo "📊 Sistema Operativo: " . PHP_OS . "\n";
echo "📊 PHP Version: " . PHP_VERSION . "\n";
echo "📊 SAPI: " . php_sapi_name() . "\n";
echo "📊 Directorio actual: " . getcwd() . "\n";
echo "📊 Memoria disponible: " . ini_get('memory_limit') . "\n";
echo "📊 Tiempo ejecución máx: " . ini_get('max_execution_time') . "s\n";
echo "📊 Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

echo str_repeat("=", 60) . "\n";
echo "VERIFICACIÓN COMPLETADA\n";
echo str_repeat("=", 60) . "\n";
?>
