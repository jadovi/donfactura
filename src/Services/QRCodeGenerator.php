<?php

declare(strict_types=1);

namespace DonFactura\DTE\Services;

/**
 * Generador de códigos QR para documentos DTE
 * Implementa especificaciones del SII para códigos de barras 2D
 */
class QRCodeGenerator
{
    public function generarQR(string $data): string
    {
        // Generar código QR usando biblioteca interna simple
        // Formato: BASE64 de imagen PNG
        
        // Por ahora simulamos con un placeholder
        // En implementación real usar: endroid/qr-code o similar
        
        $qrSize = 200;
        $qrImage = $this->crearImagenQRSimple($data, $qrSize);
        
        return base64_encode($qrImage);
    }

    public function generarQRParaDTE(array $dte): string
    {
        // Formato específico SII: RUTEmisor;TipoDTE;Folio;Fecha;RUTReceptor;Monto
        $rutEmisor = str_replace(['.', '-'], '', $dte['rut_emisor']);
        $rutReceptor = str_replace(['.', '-'], '', $dte['rut_receptor']);
        
        $qrData = sprintf(
            "%s;%s;%d;%s;%s;%d",
            $rutEmisor,
            $dte['tipo_dte'],
            $dte['folio'],
            date('Ymd', strtotime($dte['fecha_emision'])),
            $rutReceptor,
            (int)$dte['monto_total']
        );

        return $this->generarQR($qrData);
    }

    private function crearImagenQRSimple(string $data, int $size): string
    {
        // Crear imagen simple de placeholder para QR
        // En implementación real, usar biblioteca QR apropiada
        
        $image = imagecreate($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        
        // Fondo blanco
        imagefill($image, 0, 0, $white);
        
        // Simular patrón QR simple
        $moduleSize = $size / 25; // 25x25 módulos
        
        for ($i = 0; $i < 25; $i++) {
            for ($j = 0; $j < 25; $j++) {
                // Simular patrón basado en hash del data
                $hash = md5($data . $i . $j);
                if (hexdec(substr($hash, 0, 1)) % 2 === 0) {
                    imagefilledrectangle(
                        $image,
                        (int)($j * $moduleSize),
                        (int)($i * $moduleSize),
                        (int)(($j + 1) * $moduleSize - 1),
                        (int)(($i + 1) * $moduleSize - 1),
                        $black
                    );
                }
            }
        }
        
        // Agregar esquinas de posicionamiento (finder patterns)
        $this->agregarFinderPattern($image, 0, 0, $moduleSize, $black, $white);
        $this->agregarFinderPattern($image, 18 * $moduleSize, 0, $moduleSize, $black, $white);
        $this->agregarFinderPattern($image, 0, 18 * $moduleSize, $moduleSize, $black, $white);
        
        // Convertir a PNG
        ob_start();
        imagepng($image);
        $imageData = ob_get_clean();
        imagedestroy($image);
        
        return $imageData;
    }

    private function agregarFinderPattern($image, int $x, int $y, float $moduleSize, $black, $white): void
    {
        // Patrón 7x7 de finder pattern
        $pattern = [
            [1,1,1,1,1,1,1],
            [1,0,0,0,0,0,1],
            [1,0,1,1,1,0,1],
            [1,0,1,1,1,0,1],
            [1,0,1,1,1,0,1],
            [1,0,0,0,0,0,1],
            [1,1,1,1,1,1,1]
        ];
        
        for ($i = 0; $i < 7; $i++) {
            for ($j = 0; $j < 7; $j++) {
                $color = $pattern[$i][$j] ? $black : $white;
                imagefilledrectangle(
                    $image,
                    (int)($x + $j * $moduleSize),
                    (int)($y + $i * $moduleSize),
                    (int)($x + ($j + 1) * $moduleSize - 1),
                    (int)($y + ($i + 1) * $moduleSize - 1),
                    $color
                );
            }
        }
    }

    public function validarFormatoSII(string $qrData): bool
    {
        // Validar formato según especificaciones SII
        $parts = explode(';', $qrData);
        
        // Debe tener exactamente 6 partes
        if (count($parts) !== 6) {
            return false;
        }
        
        list($rutEmisor, $tipoDte, $folio, $fecha, $rutReceptor, $monto) = $parts;
        
        // Validar RUT emisor (8-9 dígitos sin puntos ni guión)
        if (!preg_match('/^\d{8,9}$/', $rutEmisor)) {
            return false;
        }
        
        // Validar tipo DTE
        if (!in_array($tipoDte, ['33', '34', '39', '45', '56', '61'])) {
            return false;
        }
        
        // Validar folio (número positivo)
        if (!is_numeric($folio) || $folio <= 0) {
            return false;
        }
        
        // Validar fecha (formato YYYYMMDD)
        if (!preg_match('/^\d{8}$/', $fecha)) {
            return false;
        }
        
        // Validar RUT receptor
        if (!preg_match('/^\d{8,9}$/', $rutReceptor)) {
            return false;
        }
        
        // Validar monto (número positivo)
        if (!is_numeric($monto) || $monto <= 0) {
            return false;
        }
        
        return true;
    }

    public function obtenerInformacionQR(string $qrData): ?array
    {
        if (!$this->validarFormatoSII($qrData)) {
            return null;
        }
        
        list($rutEmisor, $tipoDte, $folio, $fecha, $rutReceptor, $monto) = explode(';', $qrData);
        
        return [
            'rut_emisor' => $rutEmisor,
            'tipo_dte' => (int)$tipoDte,
            'folio' => (int)$folio,
            'fecha' => $fecha,
            'rut_receptor' => $rutReceptor,
            'monto' => (int)$monto
        ];
    }
}
