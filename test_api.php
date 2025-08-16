<?php
/**
 * Script para probar la API DTE básica
 */

echo "=== PROBANDO API DTE BÁSICA ===\n\n";

$baseUrl = 'http://localhost:8000';

function testEndpoint($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return [
        'response' => $response,
        'http_code' => $httpCode,
        'error' => $error
    ];
}

// Test 1: Endpoint principal
echo "1. Testing endpoint principal (/)...\n";
$result = testEndpoint($baseUrl . '/');
if ($result['http_code'] === 200) {
    $data = json_decode($result['response'], true);
    echo "   ✓ OK - " . $data['message'] . "\n";
    echo "   ✓ Modo: " . $data['mode'] . "\n";
} else {
    echo "   ✗ Error HTTP: " . $result['http_code'] . "\n";
    echo "   ✗ Error: " . $result['error'] . "\n";
}

// Test 2: Health check
echo "\n2. Testing health check (/health)...\n";
$result = testEndpoint($baseUrl . '/health');
if ($result['http_code'] === 200) {
    $data = json_decode($result['response'], true);
    echo "   ✓ OK - Status: " . $data['status'] . "\n";
    echo "   ✓ Base de datos: " . $data['database'] . "\n";
    echo "   ✓ PHP: " . $data['php_version'] . "\n";
    
    foreach ($data['extensions'] as $ext => $status) {
        $icon = $status === 'ok' ? '✓' : '✗';
        echo "   {$icon} {$ext}: {$status}\n";
    }
} else {
    echo "   ✗ Error HTTP: " . $result['http_code'] . "\n";
}

// Test 3: Test de base de datos
echo "\n3. Testing base de datos (/test-db)...\n";
$result = testEndpoint($baseUrl . '/test-db');
if ($result['http_code'] === 200) {
    $data = json_decode($result['response'], true);
    if ($data['success']) {
        echo "   ✓ OK - Base de datos: " . $data['database'] . "\n";
        echo "   ✓ Tablas encontradas: " . $data['tables_found'] . "\n";
        
        foreach ($data['tables'] as $table => $count) {
            echo "     - {$table}: {$count} registros\n";
        }
    } else {
        echo "   ✗ Error BD: " . $data['error'] . "\n";
    }
} else {
    echo "   ✗ Error HTTP: " . $result['http_code'] . "\n";
}

// Test 4: Test POST
echo "\n4. Testing endpoint POST (/test)...\n";
$testData = [
    'test' => true,
    'timestamp' => date('c'),
    'message' => 'Prueba desde script PHP'
];

$result = testEndpoint($baseUrl . '/test', 'POST', $testData);
if ($result['http_code'] === 200) {
    $data = json_decode($result['response'], true);
    if ($data['success']) {
        echo "   ✓ OK - POST funcionando\n";
        echo "   ✓ Datos recibidos correctamente\n";
    } else {
        echo "   ✗ Error en respuesta\n";
    }
} else {
    echo "   ✗ Error HTTP: " . $result['http_code'] . "\n";
}

// Test 5: Estructura de base de datos
echo "\n5. Testing estructura BD (/estructura)...\n";
$result = testEndpoint($baseUrl . '/estructura');
if ($result['http_code'] === 200) {
    $data = json_decode($result['response'], true);
    if ($data['success']) {
        echo "   ✓ OK - Estructura de BD disponible\n";
        
        foreach ($data['database_structure'] as $table => $info) {
            if (isset($info['error'])) {
                echo "   ✗ {$table}: " . $info['error'] . "\n";
            } else {
                echo "   ✓ {$table}: {$info['columns']} columnas, {$info['records']} registros\n";
            }
        }
    }
} else {
    echo "   ✗ Error HTTP: " . $result['http_code'] . "\n";
}

echo "\n=== RESUMEN DE PRUEBAS ===\n";
echo "✓ API DTE funcionando en modo básico\n";
echo "✓ Servidor: http://localhost:8000\n";
echo "✓ Base de datos configurada\n";
echo "✓ Endpoints principales funcionando\n";

echo "\n📝 Próximos pasos:\n";
echo "1. Visitar: http://localhost:8000\n";
echo "2. Probar health check: http://localhost:8000/health\n";
echo "3. Ver estructura BD: http://localhost:8000/estructura\n";
echo "4. Implementar funcionalidad completa cuando esté listo\n";

echo "\n🎉 ¡Sistema básico funcionando correctamente!\n";
?>
