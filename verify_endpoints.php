<?php
/**
 * Script de verificación de endpoints API DonFactura
 * Verifica que todos los directorios y endpoints estén correctamente configurados
 */

echo "🔍 VERIFICACIÓN DE ENDPOINTS Y DIRECTORIOS API DONFACTURA\n";
echo "=" . str_repeat("=", 60) . "\n\n";

// Cargar configuración
$config = require __DIR__ . '/config/database.php';

echo "1. VERIFICACIÓN DE DIRECTORIOS\n";
echo "-" . str_repeat("-", 30) . "\n";

$directoriesOK = true;

foreach ($config['paths'] as $name => $path) {
    if (is_dir($path)) {
        echo "✅ $name: $path\n";
        
        // Verificar permisos de escritura
        if (is_writable($path)) {
            echo "   📝 Permisos de escritura: OK\n";
        } else {
            echo "   ⚠️  Permisos de escritura: NO\n";
            $directoriesOK = false;
        }
    } else {
        echo "❌ $name: $path (NO EXISTE)\n";
        $directoriesOK = false;
        
        // Intentar crear el directorio
        if (mkdir($path, 0755, true)) {
            echo "   ✅ Directorio creado automáticamente\n";
        } else {
            echo "   ❌ No se pudo crear el directorio\n";
        }
    }
}

echo "\n2. VERIFICACIÓN DE ENDPOINTS\n";
echo "-" . str_repeat("-", 30) . "\n";

$baseUrl = 'http://localhost:8000';
$endpoints = [
    'GET /' => 'Información principal de la API',
    'GET /health' => 'Estado de salud del sistema',
    'GET /bhe-features' => 'Funcionalidades BHE',
    'GET /pdf-features' => 'Funcionalidades PDF',
    'GET /api/certificados' => 'Listar certificados',
    'POST /api/dte/generar' => 'Generar DTE',
    'POST /api/bhe/generar' => 'Generar BHE',
    'GET /api/profesionales' => 'Listar profesionales'
];

function testEndpoint($url, $method = 'GET') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'success' => !$error && $httpCode < 500,
        'http_code' => $httpCode,
        'error' => $error,
        'response' => $response
    ];
}

$endpointsOK = true;

foreach ($endpoints as $endpoint => $description) {
    list($method, $path) = explode(' ', $endpoint, 2);
    $url = $baseUrl . $path;
    
    echo "Testing $endpoint\n";
    $result = testEndpoint($url, $method);
    
    if ($result['success']) {
        echo "✅ $description\n";
        echo "   HTTP {$result['http_code']}\n";
        
        // Verificar que la respuesta sea JSON válida
        $json = json_decode($result['response'], true);
        if ($json !== null) {
            echo "   📄 Respuesta JSON válida\n";
        } else {
            echo "   ⚠️  Respuesta no es JSON válido\n";
        }
    } else {
        echo "❌ $description\n";
        echo "   HTTP {$result['http_code']}\n";
        if ($result['error']) {
            echo "   Error: {$result['error']}\n";
        }
        $endpointsOK = false;
    }
    echo "\n";
}

echo "3. VERIFICACIÓN DE ARCHIVOS DE CONFIGURACIÓN\n";
echo "-" . str_repeat("-", 40) . "\n";

$configFiles = [
    'config/database.php' => 'Configuración de base de datos',
    'frontend/config.js' => 'Configuración del frontend',
    'public/index_basic.php' => 'API básica',
    'frontend/index.html' => 'Frontend principal',
    'frontend/demo.html' => 'Demo y testing'
];

$configOK = true;

foreach ($configFiles as $file => $description) {
    if (file_exists($file)) {
        echo "✅ $description: $file\n";
        
        // Verificar tamaño del archivo
        $size = filesize($file);
        echo "   📊 Tamaño: " . number_format($size) . " bytes\n";
        
        // Verificar permisos de lectura
        if (is_readable($file)) {
            echo "   📖 Permisos de lectura: OK\n";
        } else {
            echo "   ⚠️  Permisos de lectura: NO\n";
            $configOK = false;
        }
    } else {
        echo "❌ $description: $file (NO EXISTE)\n";
        $configOK = false;
    }
}

echo "\n4. VERIFICACIÓN DE DEPENDENCIAS PHP\n";
echo "-" . str_repeat("-", 35) . "\n";

$extensions = [
    'pdo' => 'Base de datos PDO',
    'pdo_mysql' => 'MySQL para PDO',
    'openssl' => 'Firma digital SSL',
    'curl' => 'Comunicación HTTP',
    'json' => 'Manejo de JSON',
    'mbstring' => 'Cadenas multibyte',
    'fileinfo' => 'Información de archivos'
];

$extensionsOK = true;

foreach ($extensions as $ext => $description) {
    if (extension_loaded($ext)) {
        echo "✅ $description: $ext\n";
    } else {
        echo "❌ $description: $ext (NO DISPONIBLE)\n";
        $extensionsOK = false;
    }
}

echo "\n5. RESUMEN FINAL\n";
echo "-" . str_repeat("-", 15) . "\n";

if ($directoriesOK) {
    echo "✅ Directorios: OK\n";
} else {
    echo "❌ Directorios: CON PROBLEMAS\n";
}

if ($endpointsOK) {
    echo "✅ Endpoints: OK\n";
} else {
    echo "❌ Endpoints: CON PROBLEMAS\n";
}

if ($configOK) {
    echo "✅ Configuración: OK\n";
} else {
    echo "❌ Configuración: CON PROBLEMAS\n";
}

if ($extensionsOK) {
    echo "✅ Extensiones PHP: OK\n";
} else {
    echo "❌ Extensiones PHP: CON PROBLEMAS\n";
}

echo "\n";

if ($directoriesOK && $endpointsOK && $configOK && $extensionsOK) {
    echo "🎉 SISTEMA COMPLETAMENTE FUNCIONAL\n";
    echo "✅ Frontend disponible en: http://localhost:3000\n";
    echo "✅ API disponible en: http://localhost:8000\n";
    echo "✅ Demo disponible en: http://localhost:3000/demo.html\n";
    echo "✅ Test rápido en: http://localhost:3000/test-api.html\n";
} else {
    echo "⚠️  SISTEMA CON PROBLEMAS - Revisar errores arriba\n";
    
    echo "\n📋 ACCIONES RECOMENDADAS:\n";
    
    if (!$directoriesOK) {
        echo "• Crear directorios faltantes en storage/\n";
        echo "• Ajustar permisos de escritura (755)\n";
    }
    
    if (!$endpointsOK) {
        echo "• Verificar que el servidor PHP esté corriendo\n";
        echo "• Ejecutar: cd public && php -S localhost:8000 index_basic.php\n";
    }
    
    if (!$configOK) {
        echo "• Verificar archivos de configuración\n";
        echo "• Restaurar archivos faltantes\n";
    }
    
    if (!$extensionsOK) {
        echo "• Instalar extensiones PHP faltantes\n";
        echo "• Verificar configuración de PHP\n";
    }
}

echo "\n";
echo "=" . str_repeat("=", 60) . "\n";
echo "Verificación completada: " . date('Y-m-d H:i:s') . "\n";
?>
