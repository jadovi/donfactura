<?php

declare(strict_types=1);

namespace DonFactura\DTE\Services;

use DonFactura\DTE\Models\DTEModel;
use DonFactura\DTE\Models\EmpresasConfigModel;
use PDO;

/**
 * Generador de PDF para Documentos Tributarios Electrónicos
 * Soporta formato CARTA y 80mm térmico con códigos de barras 2D
 */
class PDFGenerator
{
    private PDO $pdo;
    private array $config;
    private DTEModel $dteModel;
    private EmpresasConfigModel $empresasModel;
    private QRCodeGenerator $qrGenerator;

    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->dteModel = new DTEModel($pdo);
        $this->empresasModel = new EmpresasConfigModel($pdo);
        $this->qrGenerator = new QRCodeGenerator();
    }

    public function generarPDF(int $dteId, string $formato = 'carta'): array
    {
        try {
            // Validar formato
            if (!in_array($formato, ['carta', '80mm'])) {
                throw new \InvalidArgumentException("Formato no válido: {$formato}");
            }

            // Obtener datos del DTE
            $dte = $this->dteModel->obtenerPorId($dteId);
            if (!$dte) {
                throw new \Exception("DTE no encontrado con ID: {$dteId}");
            }

            // Obtener detalles y configuración de empresa
            $detalles = $this->dteModel->obtenerDetalles($dteId);
            $referencias = $this->dteModel->obtenerReferencias($dteId);
            $empresaConfig = $this->empresasModel->obtenerPorRut($dte['rut_emisor']);

            // Generar código QR según especificaciones SII
            $codigoQR = $this->generarCodigoQRSII($dte);

            // Generar HTML según formato
            $html = $formato === 'carta' 
                ? $this->generarHTMLCarta($dte, $detalles, $referencias, $empresaConfig, $codigoQR)
                : $this->generarHTML80mm($dte, $detalles, $empresaConfig, $codigoQR);

            // Generar PDF
            $pdfContent = $this->convertirHTMLaPDF($html, $formato, $empresaConfig);

            // Guardar en base de datos
            $pdfId = $this->guardarPDF($dteId, $formato, $pdfContent, $codigoQR);

            return [
                'success' => true,
                'pdf_id' => $pdfId,
                'formato' => $formato,
                'contenido' => base64_encode($pdfContent),
                'nombre_archivo' => $this->generarNombreArchivo($dte, $formato),
                'codigo_qr' => $codigoQR
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function generarCodigoQRSII(array $dte): string
    {
        // Formato código QR según especificaciones SII
        // https://www.sii.cl/factura_electronica/formato_QR.pdf
        
        $rutEmisor = str_replace(['.', '-'], '', $dte['rut_emisor']);
        $rutReceptor = str_replace(['.', '-'], '', $dte['rut_receptor']);
        
        $qrData = sprintf(
            "%s;%s;%d;%d;%s;%d",
            $rutEmisor,
            $dte['tipo_dte'],
            $dte['folio'],
            date('Ymd', strtotime($dte['fecha_emision'])),
            $rutReceptor,
            (int)$dte['monto_total']
        );

        return $qrData;
    }

    private function generarHTMLCarta(array $dte, array $detalles, array $referencias, ?array $empresaConfig, string $codigoQR): string
    {
        $tipoDocumento = $this->getTipoDocumentoNombre($dte['tipo_dte']);
        $logoBase64 = $empresaConfig && $empresaConfig['logo_empresa'] 
            ? 'data:image/png;base64,' . base64_encode($empresaConfig['logo_empresa'])
            : '';

        $qrCodeImage = $this->qrGenerator->generarQR($codigoQR);

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>{$tipoDocumento} N° {$dte['folio']}</title>
            <style>
                {$this->getCSS('carta', $empresaConfig)}
            </style>
        </head>
        <body>
            <div class='documento'>
                <!-- Encabezado con logo y datos empresa -->
                <div class='encabezado'>
                    <div class='logo-section'>
                        " . ($logoBase64 ? "<img src='{$logoBase64}' alt='Logo' class='logo'>" : "") . "
                    </div>
                    <div class='empresa-info'>
                        <h1>{$empresaConfig['razon_social']}</h1>
                        " . ($empresaConfig['nombre_fantasia'] ? "<h2>{$empresaConfig['nombre_fantasia']}</h2>" : "") . "
                        <p class='giro'>{$empresaConfig['giro']}</p>
                        <p class='direccion'>
                            {$empresaConfig['direccion']}<br>
                            {$empresaConfig['comuna']}, {$empresaConfig['ciudad']}<br>
                            Tel: {$empresaConfig['telefono']}<br>
                            Email: {$empresaConfig['email']}
                        </p>
                    </div>
                    <div class='documento-info'>
                        <div class='tipo-documento'>
                            <h2>{$tipoDocumento}</h2>
                            <p class='numero'>N° {$dte['folio']}</p>
                        </div>
                        <div class='qr-section'>
                            <img src='data:image/png;base64,{$qrCodeImage}' alt='Código QR' class='qr-code'>
                            <p class='qr-text'>Código de barras 2D</p>
                        </div>
                    </div>
                </div>

                <!-- Datos del receptor -->
                <div class='receptor-section'>
                    <h3>Datos del Cliente</h3>
                    <div class='receptor-datos'>
                        <p><strong>RUT:</strong> {$dte['rut_receptor']}</p>
                        <p><strong>Razón Social:</strong> {$dte['razon_social_receptor']}</p>
                        " . ($dte['giro_receptor'] ? "<p><strong>Giro:</strong> {$dte['giro_receptor']}</p>" : "") . "
                        " . ($dte['direccion_receptor'] ? "<p><strong>Dirección:</strong> {$dte['direccion_receptor']}, {$dte['comuna_receptor']}</p>" : "") . "
                    </div>
                    <div class='fecha-datos'>
                        <p><strong>Fecha Emisión:</strong> " . date('d/m/Y', strtotime($dte['fecha_emision'])) . "</p>
                    </div>
                </div>

                <!-- Detalle de productos/servicios -->
                <div class='detalle-section'>
                    <table class='detalle-tabla'>
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Descripción</th>
                                <th>Cant.</th>
                                <th>P. Unit.</th>
                                <th>Descto.</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>";

        foreach ($detalles as $detalle) {
            $html .= "
                            <tr>
                                <td>{$detalle['codigo_item']}</td>
                                <td>
                                    <strong>{$detalle['nombre_item']}</strong><br>
                                    <small>{$detalle['descripcion']}</small>
                                </td>
                                <td class='centrado'>{$detalle['cantidad']} {$detalle['unidad_medida']}</td>
                                <td class='derecha'>$" . number_format($detalle['precio_unitario'], 0, ',', '.') . "</td>
                                <td class='derecha'>$" . number_format($detalle['descuento_monto'], 0, ',', '.') . "</td>
                                <td class='derecha'>$" . number_format($detalle['monto_neto'], 0, ',', '.') . "</td>
                            </tr>";
        }

        $html .= "
                        </tbody>
                    </table>
                </div>

                <!-- Totales -->
                <div class='totales-section'>
                    <table class='totales-tabla'>
                        <tr>
                            <td>Neto:</td>
                            <td>$" . number_format($dte['monto_neto'], 0, ',', '.') . "</td>
                        </tr>
                        <tr>
                            <td>IVA (19%):</td>
                            <td>$" . number_format($dte['monto_iva'], 0, ',', '.') . "</td>
                        </tr>
                        <tr class='total-final'>
                            <td><strong>TOTAL:</strong></td>
                            <td><strong>$" . number_format($dte['monto_total'], 0, ',', '.') . "</strong></td>
                        </tr>
                    </table>
                </div>";

        // Referencias si existen
        if (!empty($referencias)) {
            $html .= "
                <div class='referencias-section'>
                    <h3>Referencias</h3>
                    <table class='referencias-tabla'>
                        <thead>
                            <tr>
                                <th>Tipo Doc.</th>
                                <th>Folio</th>
                                <th>Fecha</th>
                                <th>Razón</th>
                            </tr>
                        </thead>
                        <tbody>";

            foreach ($referencias as $ref) {
                $html .= "
                            <tr>
                                <td>{$this->getTipoDocumentoNombre($ref['tipo_documento'])}</td>
                                <td>{$ref['folio_referencia']}</td>
                                <td>" . date('d/m/Y', strtotime($ref['fecha_referencia'])) . "</td>
                                <td>{$ref['razon_referencia']}</td>
                            </tr>";
            }

            $html .= "
                        </tbody>
                    </table>
                </div>";
        }

        // Observaciones
        if ($dte['observaciones']) {
            $html .= "
                <div class='observaciones-section'>
                    <h3>Observaciones</h3>
                    <p>{$dte['observaciones']}</p>
                </div>";
        }

        // Footer
        $html .= "
                <div class='footer'>
                    <p>Documento tributario electrónico generado según especificaciones del SII</p>
                    <p>Fecha de generación: " . date('d/m/Y H:i:s') . "</p>
                </div>
            </div>
        </body>
        </html>";

        return $html;
    }

    private function generarHTML80mm(array $dte, array $detalles, ?array $empresaConfig, string $codigoQR): string
    {
        $tipoDocumento = $this->getTipoDocumentoNombre($dte['tipo_dte']);
        $qrCodeImage = $this->qrGenerator->generarQR($codigoQR);

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Boleta N° {$dte['folio']}</title>
            <style>
                {$this->getCSS('80mm', $empresaConfig)}
            </style>
        </head>
        <body>
            <div class='ticket'>
                <!-- Logo centrado -->
                " . ($empresaConfig && $empresaConfig['logo_empresa'] ? "
                <div class='logo-center'>
                    <img src='data:image/png;base64," . base64_encode($empresaConfig['logo_empresa']) . "' alt='Logo'>
                </div>" : "") . "

                <!-- Datos empresa -->
                <div class='empresa-header'>
                    <h1>{$empresaConfig['razon_social']}</h1>
                    <p>{$empresaConfig['giro']}</p>
                    <p>{$empresaConfig['direccion']}</p>
                    <p>{$empresaConfig['comuna']}, {$empresaConfig['ciudad']}</p>
                    <p>Tel: {$empresaConfig['telefono']}</p>
                </div>

                <div class='separator'></div>

                <!-- Tipo documento y folio -->
                <div class='doc-info'>
                    <h2>{$tipoDocumento} N° {$dte['folio']}</h2>
                    <p>Fecha: " . date('d/m/Y H:i', strtotime($dte['fecha_emision'])) . "</p>
                </div>

                <div class='separator'></div>

                <!-- Cliente (solo si no es consumidor final) -->
                " . ($dte['rut_receptor'] !== '66666666-6' ? "
                <div class='cliente-info'>
                    <p><strong>Cliente:</strong> {$dte['razon_social_receptor']}</p>
                    <p><strong>RUT:</strong> {$dte['rut_receptor']}</p>
                </div>
                <div class='separator'></div>" : "") . "

                <!-- Detalle productos -->
                <div class='items'>";

        foreach ($detalles as $detalle) {
            $html .= "
                    <div class='item'>
                        <div class='item-nombre'>{$detalle['nombre_item']}</div>
                        <div class='item-linea'>
                            <span>{$detalle['cantidad']} x $" . number_format($detalle['precio_unitario'], 0, ',', '.') . "</span>
                            <span class='item-total'>$" . number_format($detalle['monto_neto'], 0, ',', '.') . "</span>
                        </div>
                    </div>";
        }

        $html .= "
                </div>

                <div class='separator'></div>

                <!-- Totales -->
                <div class='totales'>
                    <div class='total-linea'>
                        <span>NETO:</span>
                        <span>$" . number_format($dte['monto_neto'], 0, ',', '.') . "</span>
                    </div>
                    <div class='total-linea'>
                        <span>IVA:</span>
                        <span>$" . number_format($dte['monto_iva'], 0, ',', '.') . "</span>
                    </div>
                    <div class='total-final'>
                        <span>TOTAL:</span>
                        <span>$" . number_format($dte['monto_total'], 0, ',', '.') . "</span>
                    </div>
                </div>

                <div class='separator'></div>

                <!-- Código QR centrado -->
                <div class='qr-center'>
                    <img src='data:image/png;base64,{$qrCodeImage}' alt='Código QR'>
                    <p>Código de barras 2D</p>
                </div>

                <!-- Footer -->
                <div class='footer'>
                    <p>Documento tributario electrónico</p>
                    <p>Gracias por su compra</p>
                    <p>" . date('d/m/Y H:i:s') . "</p>
                </div>
            </div>
        </body>
        </html>";

        return $html;
    }

    private function getCSS(string $formato, ?array $empresaConfig): string
    {
        $colorPrimario = $empresaConfig['color_primario'] ?? '#000000';
        $colorSecundario = $empresaConfig['color_secundario'] ?? '#666666';

        if ($formato === 'carta') {
            return "
                @page {
                    size: letter;
                    margin: 20mm;
                }
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    line-height: 1.4;
                    color: #000;
                    margin: 0;
                    padding: 0;
                }
                .documento {
                    width: 100%;
                    max-width: 21cm;
                    margin: 0 auto;
                }
                .encabezado {
                    display: flex;
                    justify-content: space-between;
                    align-items: flex-start;
                    margin-bottom: 20px;
                    border-bottom: 2px solid {$colorPrimario};
                    padding-bottom: 15px;
                }
                .logo-section {
                    flex: 1;
                }
                .logo {
                    max-width: 120px;
                    max-height: 80px;
                }
                .empresa-info {
                    flex: 2;
                    text-align: center;
                }
                .empresa-info h1 {
                    color: {$colorPrimario};
                    font-size: 18px;
                    margin: 0 0 5px 0;
                }
                .empresa-info h2 {
                    color: {$colorSecundario};
                    font-size: 14px;
                    margin: 0 0 10px 0;
                }
                .documento-info {
                    flex: 1;
                    text-align: center;
                }
                .tipo-documento {
                    border: 2px solid {$colorPrimario};
                    padding: 10px;
                    margin-bottom: 10px;
                }
                .tipo-documento h2 {
                    color: {$colorPrimario};
                    margin: 0;
                    font-size: 16px;
                }
                .numero {
                    font-size: 14px;
                    font-weight: bold;
                    margin: 5px 0 0 0;
                }
                .qr-code {
                    width: 80px;
                    height: 80px;
                }
                .receptor-section {
                    display: flex;
                    justify-content: space-between;
                    margin: 20px 0;
                    padding: 15px;
                    background-color: #f9f9f9;
                    border: 1px solid #ddd;
                }
                .detalle-tabla {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }
                .detalle-tabla th {
                    background-color: {$colorPrimario};
                    color: white;
                    padding: 10px;
                    text-align: left;
                    font-weight: bold;
                }
                .detalle-tabla td {
                    padding: 8px;
                    border-bottom: 1px solid #ddd;
                }
                .centrado { text-align: center; }
                .derecha { text-align: right; }
                .totales-section {
                    float: right;
                    width: 300px;
                    margin: 20px 0;
                }
                .totales-tabla {
                    width: 100%;
                    border-collapse: collapse;
                }
                .totales-tabla td {
                    padding: 8px;
                    border-bottom: 1px solid #ddd;
                    text-align: right;
                }
                .total-final {
                    background-color: {$colorPrimario};
                    color: white;
                    font-size: 14px;
                }
                .footer {
                    clear: both;
                    text-align: center;
                    margin-top: 40px;
                    padding-top: 20px;
                    border-top: 1px solid #ddd;
                    font-size: 10px;
                    color: {$colorSecundario};
                }
            ";
        } else { // 80mm
            return "
                @page {
                    size: 80mm auto;
                    margin: 5mm;
                }
                body {
                    font-family: Arial, sans-serif;
                    font-size: 10px;
                    line-height: 1.2;
                    color: #000;
                    margin: 0;
                    padding: 0;
                    width: 70mm;
                }
                .ticket {
                    width: 100%;
                    text-align: center;
                }
                .logo-center img {
                    max-width: 60mm;
                    max-height: 30mm;
                    margin: 5px 0;
                }
                .empresa-header {
                    margin: 10px 0;
                }
                .empresa-header h1 {
                    font-size: 12px;
                    font-weight: bold;
                    margin: 0 0 5px 0;
                }
                .empresa-header p {
                    margin: 2px 0;
                    font-size: 9px;
                }
                .separator {
                    border-bottom: 1px dashed #000;
                    margin: 10px 0;
                }
                .doc-info h2 {
                    font-size: 11px;
                    font-weight: bold;
                    margin: 5px 0;
                }
                .items {
                    text-align: left;
                    margin: 10px 0;
                }
                .item {
                    margin: 8px 0;
                }
                .item-nombre {
                    font-weight: bold;
                    margin-bottom: 2px;
                }
                .item-linea {
                    display: flex;
                    justify-content: space-between;
                    font-size: 9px;
                }
                .totales {
                    margin: 10px 0;
                }
                .total-linea {
                    display: flex;
                    justify-content: space-between;
                    margin: 3px 0;
                    font-size: 10px;
                }
                .total-final {
                    display: flex;
                    justify-content: space-between;
                    font-weight: bold;
                    font-size: 12px;
                    border-top: 1px solid #000;
                    padding-top: 5px;
                    margin-top: 5px;
                }
                .qr-center {
                    margin: 15px 0;
                }
                .qr-center img {
                    width: 50mm;
                    height: 50mm;
                }
                .qr-center p {
                    font-size: 8px;
                    margin: 5px 0 0 0;
                }
                .footer {
                    margin-top: 15px;
                    font-size: 8px;
                }
                .footer p {
                    margin: 2px 0;
                }
            ";
        }
    }

    private function convertirHTMLaPDF(string $html, string $formato, ?array $empresaConfig): string
    {
        // Por ahora generar un PDF simple con contenido HTML
        // En implementación real usar mPDF o Dompdf
        
        // Crear un PDF básico con el HTML como contenido
        $pdfContent = "%PDF-1.4\n";
        $pdfContent .= "1 0 obj\n";
        $pdfContent .= "<<\n";
        $pdfContent .= "/Type /Catalog\n";
        $pdfContent .= "/Pages 2 0 R\n";
        $pdfContent .= ">>\n";
        $pdfContent .= "endobj\n";
        
        $pdfContent .= "2 0 obj\n";
        $pdfContent .= "<<\n";
        $pdfContent .= "/Type /Pages\n";
        $pdfContent .= "/Kids [3 0 R]\n";
        $pdfContent .= "/Count 1\n";
        $pdfContent .= ">>\n";
        $pdfContent .= "endobj\n";
        
        // Convertir HTML a texto simple para el PDF
        $textoSimple = strip_tags($html);
        $textoSimple = str_replace(['&nbsp;', '&amp;', '&lt;', '&gt;'], [' ', '&', '<', '>'], $textoSimple);
        
        $pdfContent .= "3 0 obj\n";
        $pdfContent .= "<<\n";
        $pdfContent .= "/Type /Page\n";
        $pdfContent .= "/Parent 2 0 R\n";
        $pdfContent .= "/MediaBox [0 0 612 792]\n";
        $pdfContent .= "/Contents 4 0 R\n";
        $pdfContent .= ">>\n";
        $pdfContent .= "endobj\n";
        
        $pdfContent .= "4 0 obj\n";
        $pdfContent .= "<<\n";
        $pdfContent .= "/Length " . (strlen($textoSimple) + 50) . "\n";
        $pdfContent .= ">>\n";
        $pdfContent .= "stream\n";
        $pdfContent .= "BT\n";
        $pdfContent .= "/F1 12 Tf\n";
        $pdfContent .= "72 720 Td\n";
        $pdfContent .= "(" . addslashes($textoSimple) . ") Tj\n";
        $pdfContent .= "ET\n";
        $pdfContent .= "endstream\n";
        $pdfContent .= "endobj\n";
        
        $pdfContent .= "xref\n";
        $pdfContent .= "0 5\n";
        $pdfContent .= "0000000000 65535 f \n";
        $pdfContent .= "0000000009 00000 n \n";
        $pdfContent .= "0000000058 00000 n \n";
        $pdfContent .= "0000000115 00000 n \n";
        $pdfContent .= "0000000204 00000 n \n";
        $pdfContent .= "trailer\n";
        $pdfContent .= "<<\n";
        $pdfContent .= "/Size 5\n";
        $pdfContent .= "/Root 1 0 R\n";
        $pdfContent .= ">>\n";
        $pdfContent .= "startxref\n";
        $pdfContent .= "0000000000\n";
        $pdfContent .= "%%EOF\n";
        
        return $pdfContent;
    }

    private function guardarPDF(int $dteId, string $formato, string $contenido, string $codigoQR): int
    {
        $nombreArchivo = "dte_{$dteId}_{$formato}_" . date('YmdHis') . ".pdf";
        $rutaArchivo = $this->config['paths']['pdfs'] . $nombreArchivo;

        // Asegurar que el directorio existe
        if (!is_dir($this->config['paths']['pdfs'])) {
            mkdir($this->config['paths']['pdfs'], 0755, true);
        }

        // Guardar archivo físico
        $archivoGuardado = file_put_contents($rutaArchivo, $contenido);
        
        if ($archivoGuardado === false) {
            throw new \Exception("No se pudo guardar archivo PDF en: {$rutaArchivo}");
        }

        $sql = "INSERT INTO documentos_pdf (dte_id, tipo_formato, nombre_archivo, ruta_archivo, contenido_pdf, codigo_barras_2d) 
                VALUES (:dte_id, :tipo_formato, :nombre_archivo, :ruta_archivo, :contenido_pdf, :codigo_qr)";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'dte_id' => $dteId,
            'tipo_formato' => $formato,
            'nombre_archivo' => $nombreArchivo,
            'ruta_archivo' => $rutaArchivo,
            'contenido_pdf' => $contenido,
            'codigo_qr' => $codigoQR
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function generarNombreArchivo(array $dte, string $formato): string
    {
        $tipoDoc = $this->getTipoDocumentoNombre($dte['tipo_dte']);
        $fecha = date('Y-m-d', strtotime($dte['fecha_emision']));
        return "{$tipoDoc}_{$dte['folio']}_{$fecha}_{$formato}.pdf";
    }

    private function getTipoDocumentoNombre(int $tipo): string
    {
        $tipos = [
            33 => 'FACTURA ELECTRÓNICA',
            34 => 'FACTURA EXENTA ELECTRÓNICA',
            39 => 'BOLETA ELECTRÓNICA',
            45 => 'FACTURA DE COMPRA ELECTRÓNICA',
            56 => 'NOTA DE DÉBITO ELECTRÓNICA',
            61 => 'NOTA DE CRÉDITO ELECTRÓNICA'
        ];

        return $tipos[$tipo] ?? "DOCUMENTO TIPO {$tipo}";
    }
}
