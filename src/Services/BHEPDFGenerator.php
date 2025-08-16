<?php

declare(strict_types=1);

namespace DonFactura\DTE\Services;

/**
 * Generador de PDF para Boletas de Honorarios Electrónicas (BHE - DTE Tipo 41)
 */
class BHEPDFGenerator
{
    private array $config;
    private QRCodeGenerator $qrGenerator;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->qrGenerator = new QRCodeGenerator();
    }

    public function generar(array $bheData, string $formato = 'carta'): array
    {
        try {
            switch ($formato) {
                case 'carta':
                    return $this->generarFormatoCarta($bheData);
                case '80mm':
                    return $this->generarFormato80mm($bheData);
                default:
                    throw new \InvalidArgumentException("Formato no soportado: {$formato}");
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al generar PDF BHE: ' . $e->getMessage()
            ];
        }
    }

    private function generarFormatoCarta(array $bheData): array
    {
        $html = $this->generarHTMLCarta($bheData);
        
        // Simular generación de PDF (en implementación real usar mPDF o Dompdf)
        $nombreArchivo = "BHE_{$bheData['bhe']['folio']}_{$bheData['bhe']['rut_profesional']}_carta.pdf";
        $rutaArchivo = $this->config['paths']['xml_generated'] . $nombreArchivo;
        
        // Aquí iría la lógica real de generación de PDF
        file_put_contents($rutaArchivo, $html); // Temporal para demo
        
        return [
            'success' => true,
            'archivo' => $nombreArchivo,
            'ruta' => $rutaArchivo,
            'formato' => 'carta',
            'tipo' => 'BHE',
            'size' => filesize($rutaArchivo)
        ];
    }

    private function generarFormato80mm(array $bheData): array
    {
        $html = $this->generarHTML80mm($bheData);
        
        // Simular generación de PDF para impresora térmica
        $nombreArchivo = "BHE_{$bheData['bhe']['folio']}_{$bheData['bhe']['rut_profesional']}_80mm.pdf";
        $rutaArchivo = $this->config['paths']['xml_generated'] . $nombreArchivo;
        
        // Aquí iría la lógica real de generación de PDF
        file_put_contents($rutaArchivo, $html); // Temporal para demo
        
        return [
            'success' => true,
            'archivo' => $nombreArchivo,
            'ruta' => $rutaArchivo,
            'formato' => '80mm',
            'tipo' => 'BHE',
            'size' => filesize($rutaArchivo)
        ];
    }

    private function generarHTMLCarta(array $bheData): string
    {
        $bhe = $bheData['bhe'];
        $profesional = $bheData['profesional'];
        
        // Generar código QR específico para BHE
        $qrData = $this->prepararDatosQR($bhe);
        $qrCode = $this->qrGenerator->generar($qrData);

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Boleta de Honorarios Electrónica - {$bhe['folio']}</title>
            <style>
                body { 
                    font-family: Arial, sans-serif; 
                    font-size: 12px;
                    margin: 0;
                    padding: 20px;
                }
                .header {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin-bottom: 30px;
                    border-bottom: 2px solid #2c3e50;
                    padding-bottom: 20px;
                }
                .logo-section {
                    flex: 1;
                }
                .document-info {
                    flex: 1;
                    text-align: center;
                    background: #ecf0f1;
                    padding: 15px;
                    border-radius: 8px;
                }
                .qr-section {
                    flex: 1;
                    text-align: right;
                }
                .document-title {
                    font-size: 18px;
                    font-weight: bold;
                    color: #2c3e50;
                    margin-bottom: 10px;
                }
                .document-number {
                    font-size: 16px;
                    color: #e74c3c;
                    font-weight: bold;
                }
                .parties {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 30px;
                }
                .profesional, .pagador {
                    flex: 1;
                    margin: 0 10px;
                    padding: 15px;
                    border: 1px solid #bdc3c7;
                    border-radius: 5px;
                }
                .party-title {
                    font-weight: bold;
                    color: #2c3e50;
                    margin-bottom: 10px;
                    font-size: 14px;
                    border-bottom: 1px solid #ecf0f1;
                    padding-bottom: 5px;
                }
                .servicios {
                    margin-bottom: 30px;
                    padding: 20px;
                    background: #f8f9fa;
                    border-radius: 5px;
                }
                .servicios-title {
                    font-weight: bold;
                    color: #2c3e50;
                    margin-bottom: 15px;
                    font-size: 14px;
                }
                .periodo {
                    background: #e8f4fd;
                    padding: 10px;
                    border-radius: 3px;
                    margin-bottom: 15px;
                    border-left: 4px solid #3498db;
                }
                .totales {
                    margin-top: 30px;
                    padding: 20px;
                    border: 2px solid #2c3e50;
                    border-radius: 5px;
                }
                .totales-title {
                    font-weight: bold;
                    font-size: 16px;
                    color: #2c3e50;
                    margin-bottom: 15px;
                    text-align: center;
                }
                .monto-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 8px 0;
                    border-bottom: 1px solid #ecf0f1;
                }
                .monto-row:last-child {
                    border-bottom: none;
                    font-weight: bold;
                    font-size: 14px;
                    background: #e8f5e8;
                    padding: 12px;
                    margin-top: 10px;
                    border-radius: 3px;
                }
                .retencion-info {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    padding: 15px;
                    border-radius: 5px;
                    margin-top: 20px;
                }
                .footer {
                    margin-top: 40px;
                    text-align: center;
                    color: #7f8c8d;
                    font-size: 10px;
                    border-top: 1px solid #ecf0f1;
                    padding-top: 20px;
                }
                .sii-info {
                    background: #f8f9fa;
                    padding: 10px;
                    border-radius: 3px;
                    margin-top: 10px;
                    font-size: 10px;
                    color: #6c757d;
                }
            </style>
        </head>
        <body>
            <div class='header'>
                <div class='logo-section'>
                    <h2 style='margin: 0; color: #2c3e50;'>{$profesional['nombres']} {$profesional['apellido_paterno']}</h2>
                    <p style='margin: 5px 0;'>{$profesional['profesion']}</p>
                    <p style='margin: 5px 0; font-size: 11px;'>RUT: {$bhe['rut_profesional']}</p>
                </div>
                <div class='document-info'>
                    <div class='document-title'>BOLETA DE HONORARIOS ELECTRÓNICA</div>
                    <div class='document-number'>N° {$bhe['folio']}</div>
                    <div style='margin-top: 10px; font-size: 12px;'>
                        Fecha: {$bhe['fecha_emision']}<br>
                        DTE Tipo: 41
                    </div>
                </div>
                <div class='qr-section'>
                    <div style='margin-bottom: 10px;'>
                        <img src='data:image/png;base64,{$qrCode}' width='100' height='100' alt='Código QR'>
                    </div>
                    <div style='font-size: 9px; color: #7f8c8d;'>
                        Código SII<br>
                        Verificación
                    </div>
                </div>
            </div>

            <div class='parties'>
                <div class='profesional'>
                    <div class='party-title'>DATOS DEL PROFESIONAL</div>
                    <div><strong>Nombre:</strong> {$profesional['nombres']} {$profesional['apellido_paterno']} {$profesional['apellido_materno']}</div>
                    <div><strong>RUT:</strong> {$bhe['rut_profesional']}</div>
                    <div><strong>Profesión:</strong> {$profesional['profesion']}</div>
                    <div><strong>Dirección:</strong> {$profesional['direccion']}</div>
                    <div><strong>Comuna:</strong> {$profesional['comuna']}</div>
                    <div><strong>Email:</strong> {$profesional['email']}</div>
                    <div><strong>Teléfono:</strong> {$profesional['telefono']}</div>
                </div>
                <div class='pagador'>
                    <div class='party-title'>DATOS DEL PAGADOR</div>
                    <div><strong>Nombre:</strong> {$bhe['nombre_pagador']}</div>
                    <div><strong>RUT:</strong> {$bhe['rut_pagador']}</div>
                    <div><strong>Dirección:</strong> {$bhe['direccion_pagador']}</div>
                    <div><strong>Comuna:</strong> {$bhe['comuna_pagador']}</div>
                </div>
            </div>

            <div class='servicios'>
                <div class='servicios-title'>DETALLE DE SERVICIOS PROFESIONALES</div>
                <div class='periodo'>
                    <strong>Período de Servicios:</strong> {$bhe['periodo_desde']} al {$bhe['periodo_hasta']}
                </div>
                <div><strong>Descripción:</strong></div>
                <div style='margin-top: 10px; line-height: 1.5;'>{$bhe['descripcion_servicios']}</div>
            </div>

            <div class='totales'>
                <div class='totales-title'>RESUMEN DE MONTOS</div>
                <div class='monto-row'>
                    <span>Monto Bruto de Honorarios:</span>
                    <span>$ " . number_format($bhe['monto_bruto'], 0, ',', '.') . "</span>
                </div>
                <div class='monto-row'>
                    <span>Retención {$bhe['porcentaje_retencion']}% (Segunda Categoría):</span>
                    <span>$ " . number_format($bhe['retencion_honorarios'], 0, ',', '.') . "</span>
                </div>
                <div class='monto-row'>
                    <span>TOTAL LÍQUIDO A PAGAR:</span>
                    <span>$ " . number_format($bhe['monto_liquido'], 0, ',', '.') . "</span>
                </div>
            </div>

            <div class='retencion-info'>
                <strong>Información sobre Retención:</strong><br>
                Se ha aplicado retención del {$bhe['porcentaje_retencion']}% sobre los honorarios brutos, correspondiente al impuesto de segunda categoría según lo establecido en la Ley de Impuesto a la Renta. Esta retención será considerada como pago provisional anual del profesional.
            </div>

            <div class='sii-info'>
                <strong>Información SII:</strong> Esta Boleta de Honorarios Electrónica ha sido generada y firmada digitalmente conforme a la normativa del Servicio de Impuestos Internos de Chile. Para verificar su autenticidad, escanee el código QR o visite www.sii.cl
            </div>

            <div class='footer'>
                <div>DOCUMENTO TRIBUTARIO ELECTRÓNICO</div>
                <div>Resolución SII N° 40 del 24/04/2014</div>
                <div>Boleta de Honorarios Electrónica - DTE Tipo 41</div>
                <div style='margin-top: 10px;'>Estado: {$bhe['estado']} | Folio: {$bhe['folio']} | Fecha: {$bhe['fecha_emision']}</div>
            </div>
        </body>
        </html>";
    }

    private function generarHTML80mm(array $bheData): string
    {
        $bhe = $bheData['bhe'];
        $profesional = $bheData['profesional'];
        
        // Generar código QR específico para BHE
        $qrData = $this->prepararDatosQR($bhe);
        $qrCode = $this->qrGenerator->generar($qrData);

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>BHE {$bhe['folio']} - Formato Térmico</title>
            <style>
                body { 
                    font-family: 'Courier New', monospace;
                    font-size: 11px;
                    margin: 0;
                    padding: 10px;
                    width: 70mm;
                    line-height: 1.3;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                    border-bottom: 1px dashed #000;
                    padding-bottom: 15px;
                }
                .title {
                    font-weight: bold;
                    font-size: 12px;
                    margin-bottom: 5px;
                }
                .document-info {
                    font-size: 10px;
                    margin-bottom: 10px;
                }
                .section {
                    margin-bottom: 15px;
                    border-bottom: 1px dashed #ccc;
                    padding-bottom: 10px;
                }
                .section-title {
                    font-weight: bold;
                    font-size: 10px;
                    margin-bottom: 8px;
                    text-decoration: underline;
                }
                .row {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 3px;
                }
                .total-row {
                    font-weight: bold;
                    border-top: 1px solid #000;
                    margin-top: 8px;
                    padding-top: 5px;
                }
                .qr-section {
                    text-align: center;
                    margin: 20px 0;
                }
                .footer {
                    text-align: center;
                    font-size: 8px;
                    margin-top: 20px;
                    color: #666;
                }
                .text-small {
                    font-size: 9px;
                }
                .text-center {
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <div class='header'>
                <div class='title'>BOLETA HONORARIOS ELECTRONICA</div>
                <div class='document-info'>
                    DTE Tipo 41 - N° {$bhe['folio']}<br>
                    Fecha: {$bhe['fecha_emision']}
                </div>
            </div>

            <div class='section'>
                <div class='section-title'>PROFESIONAL</div>
                <div class='text-small'>
                    {$profesional['nombres']} {$profesional['apellido_paterno']}<br>
                    RUT: {$bhe['rut_profesional']}<br>
                    {$profesional['profesion']}<br>
                    {$profesional['direccion']}<br>
                    {$profesional['comuna']}
                </div>
            </div>

            <div class='section'>
                <div class='section-title'>PAGADOR</div>
                <div class='text-small'>
                    {$bhe['nombre_pagador']}<br>
                    RUT: {$bhe['rut_pagador']}<br>
                    {$bhe['direccion_pagador']}<br>
                    {$bhe['comuna_pagador']}
                </div>
            </div>

            <div class='section'>
                <div class='section-title'>SERVICIOS</div>
                <div class='text-small'>
                    Período: {$bhe['periodo_desde']} al {$bhe['periodo_hasta']}<br><br>
                    " . wordwrap($bhe['descripcion_servicios'], 35, '<br>') . "
                </div>
            </div>

            <div class='section'>
                <div class='section-title'>MONTOS</div>
                <div class='row'>
                    <span>Honorarios Brutos:</span>
                    <span>$" . number_format($bhe['monto_bruto'], 0, ',', '.') . "</span>
                </div>
                <div class='row'>
                    <span>Retención {$bhe['porcentaje_retencion']}%:</span>
                    <span>$" . number_format($bhe['retencion_honorarios'], 0, ',', '.') . "</span>
                </div>
                <div class='row total-row'>
                    <span>TOTAL LÍQUIDO:</span>
                    <span>$" . number_format($bhe['monto_liquido'], 0, ',', '.') . "</span>
                </div>
            </div>

            <div class='qr-section'>
                <img src='data:image/png;base64,{$qrCode}' width='80' height='80' alt='QR'>
                <div class='text-small' style='margin-top: 5px;'>
                    Código de Verificación SII
                </div>
            </div>

            <div class='section text-small'>
                <strong>Retención:</strong> Se aplicó retención del {$bhe['porcentaje_retencion']}% correspondiente al impuesto de segunda categoría.
            </div>

            <div class='footer'>
                <div>DOCUMENTO TRIBUTARIO ELECTRONICO</div>
                <div>Resolución SII N° 40/2014</div>
                <div>Estado: {$bhe['estado']}</div>
                <div style='margin-top: 10px;'>
                    " . date('d/m/Y H:i:s') . "
                </div>
            </div>
        </body>
        </html>";
    }

    private function prepararDatosQR(array $bhe): string
    {
        // Formato específico para código QR de BHE según SII
        return implode(';', [
            $bhe['rut_profesional'],        // RUT Emisor
            '41',                           // Tipo DTE (BHE)
            $bhe['folio'],                  // Folio
            $bhe['fecha_emision'],          // Fecha
            $bhe['rut_pagador'],            // RUT Receptor
            $bhe['monto_liquido']           // Monto Total (líquido)
        ]);
    }

    public function obtenerPlantillaProfesional(string $rutProfesional, string $formato = 'carta'): ?array
    {
        // Simular obtención de plantilla personalizada
        return [
            'id' => 1,
            'rut_profesional' => $rutProfesional,
            'formato' => $formato,
            'incluir_retencion' => true,
            'mostrar_periodo' => true,
            'mostrar_descripcion_detallada' => true,
            'colores' => [
                'primario' => '#2c3e50',
                'secundario' => '#3498db',
                'acento' => '#e74c3c'
            ]
        ];
    }

    public function validarFormatoBHE(string $formato): bool
    {
        return in_array($formato, ['carta', '80mm']);
    }

    public function obtenerTamañosDisponibles(): array
    {
        return [
            'carta' => [
                'tamaño' => '21.5 x 27.9 cm',
                'orientacion' => 'vertical',
                'uso' => 'Boletas de honorarios para archivo e impresión estándar'
            ],
            '80mm' => [
                'tamaño' => '80mm ancho x auto alto',
                'orientacion' => 'vertical',
                'uso' => 'Boletas para impresoras térmicas de punto de venta'
            ]
        ];
    }
}
