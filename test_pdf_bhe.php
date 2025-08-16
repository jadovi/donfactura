<?php
/**
 * Test de generación de PDF para BHE
 */

echo "=== TEST PDF BHE ===\n";

$dteId = 3; // ID de la BHE que acabamos de crear

// Test PDF formato carta
$urlCarta = "http://localhost:8000/api/bhe/{$dteId}/pdf?formato=carta";
echo "1. Probando PDF formato CARTA...\n";
echo "URL: {$urlCarta}\n";

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        'timeout' => 30
    ]
]);

$response = @file_get_contents($urlCarta, false, $context);

if ($response === FALSE) {
    echo "❌ Error de conexión\n";
} else {
    $resultado = json_decode($response, true);
    echo "Resultado: " . json_encode($resultado, JSON_PRETTY_PRINT) . "\n";
}

echo "\n" . str_repeat("-", 50) . "\n";

// Test PDF formato 80mm
$url80mm = "http://localhost:8000/api/bhe/{$dteId}/pdf?formato=80mm";
echo "2. Probando PDF formato 80MM...\n";
echo "URL: {$url80mm}\n";

$response = @file_get_contents($url80mm, false, $context);

if ($response === FALSE) {
    echo "❌ Error de conexión\n";
} else {
    $resultado = json_decode($response, true);
    echo "Resultado: " . json_encode($resultado, JSON_PRETTY_PRINT) . "\n";
}

echo "\n" . str_repeat("-", 50) . "\n";

// Test obtener BHE creada
$urlObtener = "http://localhost:8000/api/bhe/{$dteId}";
echo "3. Obteniendo datos de la BHE creada...\n";
echo "URL: {$urlObtener}\n";

$contextGet = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            'Accept: application/json'
        ],
        'timeout' => 30
    ]
]);

$response = @file_get_contents($urlObtener, false, $contextGet);

if ($response === FALSE) {
    echo "❌ Error de conexión\n";
} else {
    $resultado = json_decode($response, true);
    echo "Resultado: " . json_encode($resultado, JSON_PRETTY_PRINT) . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "TEST PDF BHE COMPLETADO\n";
echo str_repeat("=", 50) . "\n";
?>
