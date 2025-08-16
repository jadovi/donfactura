<?php
/**
 * Test específico para generar BHE
 */

echo "=== TEST GENERACIÓN BHE ===\n";

$url = 'http://localhost:8000/api/bhe/generar';

$data = [
    'profesional' => [
        'rut' => '12345678-9', // Juan Carlos Pérez (ya registrado)
        'observaciones' => 'Consultor en desarrollo de software'
    ],
    'pagador' => [
        'rut' => '96789012-3',
        'nombre' => 'CONSULTORA EMPRESARIAL SPA',
        'direccion' => 'CALLE COMERCIAL 789',
        'comuna' => 'SANTIAGO',
        'codigo_comuna' => '13101'
    ],
    'servicios' => [
        'descripcion' => 'Desarrollo de sistema de gestión empresarial personalizado. Incluye análisis, diseño, programación, testing y capacitación.',
        'periodo_desde' => '2024-12-01',
        'periodo_hasta' => '2024-12-15',
        'monto_bruto' => 1800000,
        'porcentaje_retencion' => 10.0,
        'observaciones' => 'Proyecto completado según especificaciones'
    ],
    'forma_pago' => 1,
    'metadata' => [
        'ip_emisor' => '192.168.1.100',
        'usuario_emisor' => 'juan.perez',
        'comentarios' => 'Primera BHE de prueba'
    ]
];

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        'content' => json_encode($data),
        'timeout' => 30
    ]
]);

echo "Enviando petición a: {$url}\n";
echo "Datos: " . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";

$response = @file_get_contents($url, false, $context);

if ($response === FALSE) {
    echo "❌ Error de conexión o timeout\n";
    echo "Verifique que el servidor esté corriendo en: http://localhost:8000\n";
} else {
    $resultado = json_decode($response, true);
    echo "Respuesta del servidor:\n";
    echo json_encode($resultado, JSON_PRETTY_PRINT) . "\n";
    
    if (isset($resultado['success']) && $resultado['success']) {
        echo "\n🎉 ¡BHE GENERADA EXITOSAMENTE!\n";
        
        if (isset($resultado['data'])) {
            $bheData = $resultado['data'];
            echo "\nDetalles BHE:\n";
            echo "- ID DTE: {$bheData['dte_id']}\n";
            echo "- Folio: {$bheData['folio']}\n";
            echo "- Profesional: {$bheData['nombre_profesional']} ({$bheData['rut_profesional']})\n";
            echo "- Monto Bruto: $" . number_format($bheData['monto_bruto'], 0, ',', '.') . "\n";
            echo "- Retención: $" . number_format($bheData['retencion'], 0, ',', '.') . "\n";
            echo "- Monto Líquido: $" . number_format($bheData['monto_liquido'], 0, ',', '.') . "\n";
            echo "- Estado: {$bheData['estado']}\n";
        }
    } else {
        echo "\n❌ Error al generar BHE:\n";
        if (isset($resultado['error'])) {
            echo "Error: {$resultado['error']}\n";
        }
        if (isset($resultado['errores'])) {
            echo "Errores específicos:\n";
            foreach ($resultado['errores'] as $error) {
                echo "- {$error}\n";
            }
        }
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "TEST BHE COMPLETADO\n";
echo str_repeat("=", 50) . "\n";
?>
