<?php

declare(strict_types=1);

namespace DonFactura\DTE\Services;

use DOMDocument;
use DateTime;

/**
 * Generador de XML para Documentos Tributarios Electrónicos
 */
class DTEXMLGenerator
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function generar(int $tipoDte, array $data, int $folio, ?int $cafId = null): string
    {
        switch ($tipoDte) {
            case 33:
                return $this->generarFacturaElectronica($data, $folio);
            case 34:
                return $this->generarFacturaExenta($data, $folio);
            case 39:
                return $this->generarBoletaElectronica($data, $folio);
            case 45:
                return $this->generarFacturaCompra($data, $folio);
            case 56:
                return $this->generarNotaDebito($data, $folio);
            case 61:
                return $this->generarNotaCredito($data, $folio);
            default:
                throw new \InvalidArgumentException("Tipo de DTE no soportado: {$tipoDte}");
        }
    }

    private function generarFacturaElectronica(array $data, int $folio): string
    {
        $xml = new DOMDocument('1.0', 'ISO-8859-1');
        $xml->formatOutput = true;

        // Elemento raíz DTE
        $dte = $xml->createElement('DTE');
        $dte->setAttribute('version', '1.0');
        $xml->appendChild($dte);

        // Documento
        $documento = $xml->createElement('Documento');
        $documento->setAttribute('ID', "F{$folio}T33");
        $dte->appendChild($documento);

        // Encabezado
        $encabezado = $this->crearEncabezado($xml, $data, $folio, 33);
        $documento->appendChild($encabezado);

        // Detalles
        $detalles = $this->crearDetalles($xml, $data['detalles'] ?? []);
        foreach ($detalles as $detalle) {
            $documento->appendChild($detalle);
        }

        // Referencias (si existen)
        if (isset($data['referencias'])) {
            $referencias = $this->crearReferencias($xml, $data['referencias']);
            foreach ($referencias as $referencia) {
                $documento->appendChild($referencia);
            }
        }

        // TED (Timbre Electrónico de Documentos) - se añadirá después de la firma
        $ted = $this->crearTEDPlaceholder($xml, $folio, 33);
        $documento->appendChild($ted);

        return $xml->saveXML();
    }

    private function generarFacturaExenta(array $data, int $folio): string
    {
        $xml = new DOMDocument('1.0', 'ISO-8859-1');
        $xml->formatOutput = true;

        $dte = $xml->createElement('DTE');
        $dte->setAttribute('version', '1.0');
        $xml->appendChild($dte);

        $documento = $xml->createElement('Documento');
        $documento->setAttribute('ID', "F{$folio}T34");
        $dte->appendChild($documento);

        // Encabezado para factura exenta
        $encabezado = $this->crearEncabezado($xml, $data, $folio, 34, true);
        $documento->appendChild($encabezado);

        // Detalles
        $detalles = $this->crearDetalles($xml, $data['detalles'] ?? [], true);
        foreach ($detalles as $detalle) {
            $documento->appendChild($detalle);
        }

        // TED
        $ted = $this->crearTEDPlaceholder($xml, $folio, 34);
        $documento->appendChild($ted);

        return $xml->saveXML();
    }

    private function generarBoletaElectronica(array $data, int $folio): string
    {
        $xml = new DOMDocument('1.0', 'ISO-8859-1');
        $xml->formatOutput = true;

        $dte = $xml->createElement('DTE');
        $dte->setAttribute('version', '1.0');
        $xml->appendChild($dte);

        $documento = $xml->createElement('Documento');
        $documento->setAttribute('ID', "B{$folio}T39");
        $dte->appendChild($documento);

        // Encabezado específico para boletas
        $encabezado = $this->crearEncabezadoBoleta($xml, $data, $folio);
        $documento->appendChild($encabezado);

        // Detalles
        $detalles = $this->crearDetalles($xml, $data['detalles'] ?? []);
        foreach ($detalles as $detalle) {
            $documento->appendChild($detalle);
        }

        // TED
        $ted = $this->crearTEDPlaceholder($xml, $folio, 39);
        $documento->appendChild($ted);

        return $xml->saveXML();
    }

    private function generarNotaDebito(array $data, int $folio): string
    {
        $xml = new DOMDocument('1.0', 'ISO-8859-1');
        $xml->formatOutput = true;

        $dte = $xml->createElement('DTE');
        $dte->setAttribute('version', '1.0');
        $xml->appendChild($dte);

        $documento = $xml->createElement('Documento');
        $documento->setAttribute('ID', "D{$folio}T56");
        $dte->appendChild($documento);

        $encabezado = $this->crearEncabezado($xml, $data, $folio, 56);
        $documento->appendChild($encabezado);

        $detalles = $this->crearDetalles($xml, $data['detalles'] ?? []);
        foreach ($detalles as $detalle) {
            $documento->appendChild($detalle);
        }

        // Las notas de débito requieren referencias obligatorias
        if (isset($data['referencias'])) {
            $referencias = $this->crearReferencias($xml, $data['referencias']);
            foreach ($referencias as $referencia) {
                $documento->appendChild($referencia);
            }
        }

        $ted = $this->crearTEDPlaceholder($xml, $folio, 56);
        $documento->appendChild($ted);

        return $xml->saveXML();
    }

    private function generarNotaCredito(array $data, int $folio): string
    {
        $xml = new DOMDocument('1.0', 'ISO-8859-1');
        $xml->formatOutput = true;

        $dte = $xml->createElement('DTE');
        $dte->setAttribute('version', '1.0');
        $xml->appendChild($dte);

        $documento = $xml->createElement('Documento');
        $documento->setAttribute('ID', "C{$folio}T61");
        $dte->appendChild($documento);

        $encabezado = $this->crearEncabezado($xml, $data, $folio, 61);
        $documento->appendChild($encabezado);

        $detalles = $this->crearDetalles($xml, $data['detalles'] ?? []);
        foreach ($detalles as $detalle) {
            $documento->appendChild($detalle);
        }

        // Las notas de crédito requieren referencias obligatorias
        if (isset($data['referencias'])) {
            $referencias = $this->crearReferencias($xml, $data['referencias']);
            foreach ($referencias as $referencia) {
                $documento->appendChild($referencia);
            }
        }

        $ted = $this->crearTEDPlaceholder($xml, $folio, 61);
        $documento->appendChild($ted);

        return $xml->saveXML();
    }

    private function generarFacturaCompra(array $data, int $folio): string
    {
        $xml = new DOMDocument('1.0', 'ISO-8859-1');
        $xml->formatOutput = true;

        $dte = $xml->createElement('DTE');
        $dte->setAttribute('version', '1.0');
        $xml->appendChild($dte);

        $documento = $xml->createElement('Documento');
        $documento->setAttribute('ID', "FC{$folio}T45");
        $dte->appendChild($documento);

        // Encabezado específico para factura de compra
        $encabezado = $this->crearEncabezadoFacturaCompra($xml, $data, $folio);
        $documento->appendChild($encabezado);

        $detalles = $this->crearDetalles($xml, $data['detalles'] ?? []);
        foreach ($detalles as $detalle) {
            $documento->appendChild($detalle);
        }

        $ted = $this->crearTEDPlaceholder($xml, $folio, 45);
        $documento->appendChild($ted);

        return $xml->saveXML();
    }

    private function crearEncabezado(DOMDocument $xml, array $data, int $folio, int $tipoDte, bool $exenta = false): \DOMElement
    {
        $encabezado = $xml->createElement('Encabezado');

        // Identificación del documento
        $idDoc = $xml->createElement('IdDoc');
        $idDoc->appendChild($xml->createElement('TipoDTE', (string)$tipoDte));
        $idDoc->appendChild($xml->createElement('Folio', (string)$folio));
        $idDoc->appendChild($xml->createElement('FchEmis', $data['fecha_emision'] ?? date('Y-m-d')));
        if (isset($data['forma_pago'])) {
            $idDoc->appendChild($xml->createElement('FmaPago', $data['forma_pago']));
        }
        if (isset($data['fecha_vencimiento'])) {
            $idDoc->appendChild($xml->createElement('FchVenc', $data['fecha_vencimiento']));
        }
        $encabezado->appendChild($idDoc);

        // Emisor
        $emisor = $this->crearEmisor($xml, $data['emisor']);
        $encabezado->appendChild($emisor);

        // Receptor
        $receptor = $this->crearReceptor($xml, $data['receptor']);
        $encabezado->appendChild($receptor);

        // Totales
        $totales = $this->crearTotales($xml, $data, $exenta);
        $encabezado->appendChild($totales);

        return $encabezado;
    }

    private function crearEncabezadoBoleta(DOMDocument $xml, array $data, int $folio): \DOMElement
    {
        $encabezado = $xml->createElement('Encabezado');

        // Identificación del documento
        $idDoc = $xml->createElement('IdDoc');
        $idDoc->appendChild($xml->createElement('TipoDTE', '39'));
        $idDoc->appendChild($xml->createElement('Folio', (string)$folio));
        $idDoc->appendChild($xml->createElement('FchEmis', $data['fecha_emision'] ?? date('Y-m-d')));
        
        // Para boletas, el indicador de servicio periódico
        if (isset($data['boleta']['servicio_periodico']) && $data['boleta']['servicio_periodico']) {
            $idDoc->appendChild($xml->createElement('IndServicio', '1'));
            if (isset($data['boleta']['periodo_desde'])) {
                $idDoc->appendChild($xml->createElement('PeriodoDesde', $data['boleta']['periodo_desde']));
            }
            if (isset($data['boleta']['periodo_hasta'])) {
                $idDoc->appendChild($xml->createElement('PeriodoHasta', $data['boleta']['periodo_hasta']));
            }
        }
        
        $encabezado->appendChild($idDoc);

        // Emisor
        $emisor = $this->crearEmisor($xml, $data['emisor']);
        $encabezado->appendChild($emisor);

        // Receptor (para boletas puede ser consumidor final)
        $receptor = $this->crearReceptor($xml, $data['receptor']);
        $encabezado->appendChild($receptor);

        // Totales
        $totales = $this->crearTotales($xml, $data);
        $encabezado->appendChild($totales);

        return $encabezado;
    }

    private function crearEncabezadoFacturaCompra(DOMDocument $xml, array $data, int $folio): \DOMElement
    {
        $encabezado = $xml->createElement('Encabezado');

        // Identificación del documento
        $idDoc = $xml->createElement('IdDoc');
        $idDoc->appendChild($xml->createElement('TipoDTE', '45'));
        $idDoc->appendChild($xml->createElement('Folio', (string)$folio));
        $idDoc->appendChild($xml->createElement('FchEmis', $data['fecha_emision'] ?? date('Y-m-d')));
        $idDoc->appendChild($xml->createElement('TipoDespacho', $data['tipo_despacho'] ?? 1));
        $idDoc->appendChild($xml->createElement('IndTraslado', $data['ind_traslado'] ?? 1));
        $encabezado->appendChild($idDoc);

        // Emisor
        $emisor = $this->crearEmisor($xml, $data['emisor']);
        $encabezado->appendChild($emisor);

        // Receptor
        $receptor = $this->crearReceptor($xml, $data['receptor']);
        $encabezado->appendChild($receptor);

        // Totales
        $totales = $this->crearTotales($xml, $data);
        $encabezado->appendChild($totales);

        return $encabezado;
    }

    private function crearEmisor(DOMDocument $xml, array $emisorData): \DOMElement
    {
        $emisor = $xml->createElement('Emisor');
        $emisor->appendChild($xml->createElement('RUTEmisor', $emisorData['rut']));
        $emisor->appendChild($xml->createElement('RznSoc', $emisorData['razon_social']));
        
        if (isset($emisorData['giro'])) {
            $emisor->appendChild($xml->createElement('GiroEmis', $emisorData['giro']));
        }
        if (isset($emisorData['telefono'])) {
            $emisor->appendChild($xml->createElement('Telefono', $emisorData['telefono']));
        }
        if (isset($emisorData['email'])) {
            $emisor->appendChild($xml->createElement('CorreoEmisor', $emisorData['email']));
        }
        if (isset($emisorData['actividad_economica'])) {
            $emisor->appendChild($xml->createElement('Acteco', $emisorData['actividad_economica']));
        }
        if (isset($emisorData['direccion'])) {
            $emisor->appendChild($xml->createElement('DirOrigen', $emisorData['direccion']));
        }
        if (isset($emisorData['comuna'])) {
            $emisor->appendChild($xml->createElement('CmnaOrigen', $emisorData['comuna']));
        }
        if (isset($emisorData['ciudad'])) {
            $emisor->appendChild($xml->createElement('CiudadOrigen', $emisorData['ciudad']));
        }

        return $emisor;
    }

    private function crearReceptor(DOMDocument $xml, array $receptorData): \DOMElement
    {
        $receptor = $xml->createElement('Receptor');
        
        // Para consumidor final en boletas
        $rutReceptor = $receptorData['rut'] ?? '66666666-6';
        $razonSocial = $receptorData['razon_social'] ?? 'CONSUMIDOR FINAL';
        
        $receptor->appendChild($xml->createElement('RUTRecep', $rutReceptor));
        $receptor->appendChild($xml->createElement('RznSocRecep', $razonSocial));
        
        if (isset($receptorData['giro'])) {
            $receptor->appendChild($xml->createElement('GiroRecep', $receptorData['giro']));
        }
        if (isset($receptorData['direccion'])) {
            $receptor->appendChild($xml->createElement('DirRecep', $receptorData['direccion']));
        }
        if (isset($receptorData['comuna'])) {
            $receptor->appendChild($xml->createElement('CmnaRecep', $receptorData['comuna']));
        }
        if (isset($receptorData['ciudad'])) {
            $receptor->appendChild($xml->createElement('CiudadRecep', $receptorData['ciudad']));
        }
        if (isset($receptorData['email'])) {
            $receptor->appendChild($xml->createElement('CorreoRecep', $receptorData['email']));
        }

        return $receptor;
    }

    private function crearTotales(DOMDocument $xml, array $data, bool $exenta = false): \DOMElement
    {
        $totales = $xml->createElement('Totales');
        
        // Calcular totales
        $neto = 0;
        $iva = 0;
        $total = 0;

        if (isset($data['detalles'])) {
            foreach ($data['detalles'] as $detalle) {
                $cantidad = $detalle['cantidad'] ?? 1;
                $precio = $detalle['precio_unitario'];
                $descuento = $detalle['descuento_monto'] ?? 0;
                $montoLinea = ($cantidad * $precio) - $descuento;
                
                if ($exenta || ($detalle['indica_exento'] ?? false)) {
                    // Para productos exentos, se suma directo al total
                    $total += $montoLinea;
                } else {
                    $neto += $montoLinea;
                }
            }
        }

        if (!$exenta && $neto > 0) {
            $iva = round($neto * 0.19, 0); // IVA 19% redondeado
            $total = $neto + $iva;
            
            $totales->appendChild($xml->createElement('MntNeto', number_format($neto, 0, '', '')));
            $totales->appendChild($xml->createElement('TasaIVA', '19'));
            $totales->appendChild($xml->createElement('IVA', number_format($iva, 0, '', '')));
        } elseif ($exenta) {
            $totales->appendChild($xml->createElement('MntExe', number_format($total, 0, '', '')));
        }

        $totales->appendChild($xml->createElement('MntTotal', number_format($total, 0, '', '')));

        return $totales;
    }

    private function crearDetalles(DOMDocument $xml, array $detalles, bool $exenta = false): array
    {
        $elementosDetalle = [];

        foreach ($detalles as $index => $detalle) {
            $detalleElement = $xml->createElement('Detalle');
            
            $detalleElement->appendChild($xml->createElement('NroLinDet', (string)($index + 1)));
            
            if (isset($detalle['codigo_item'])) {
                $detalleElement->appendChild($xml->createElement('CdgItem', $detalle['codigo_item']));
            }
            
            $detalleElement->appendChild($xml->createElement('NmbItem', $detalle['nombre_item']));
            
            if (isset($detalle['descripcion'])) {
                $detalleElement->appendChild($xml->createElement('DscItem', $detalle['descripcion']));
            }
            
            $cantidad = $detalle['cantidad'] ?? 1;
            $detalleElement->appendChild($xml->createElement('QtyItem', (string)$cantidad));
            
            if (isset($detalle['unidad_medida'])) {
                $detalleElement->appendChild($xml->createElement('UnmdItem', $detalle['unidad_medida']));
            }
            
            $precio = $detalle['precio_unitario'];
            $detalleElement->appendChild($xml->createElement('PrcItem', (string)number_format($precio, 0, '', '')));
            
            if (isset($detalle['descuento_porcentaje']) && $detalle['descuento_porcentaje'] > 0) {
                $detalleElement->appendChild($xml->createElement('DescuentoPct', (string)$detalle['descuento_porcentaje']));
            }
            
            if (isset($detalle['descuento_monto']) && $detalle['descuento_monto'] > 0) {
                $detalleElement->appendChild($xml->createElement('DescuentoMonto', (string)number_format($detalle['descuento_monto'], 0, '', '')));
            }
            
            $montoItem = ($cantidad * $precio) - ($detalle['descuento_monto'] ?? 0);
            $detalleElement->appendChild($xml->createElement('MontoItem', (string)number_format($montoItem, 0, '', '')));
            
            // Indicador de exento
            if ($exenta || ($detalle['indica_exento'] ?? false)) {
                $detalleElement->appendChild($xml->createElement('IndExe', '1'));
            }
            
            $elementosDetalle[] = $detalleElement;
        }

        return $elementosDetalle;
    }

    private function crearReferencias(DOMDocument $xml, array $referencias): array
    {
        $elementosReferencia = [];

        foreach ($referencias as $index => $referencia) {
            $referenciaElement = $xml->createElement('Referencia');
            
            $referenciaElement->appendChild($xml->createElement('NroLinRef', $index + 1));
            $referenciaElement->appendChild($xml->createElement('TpoDocRef', $referencia['tipo_documento']));
            $referenciaElement->appendChild($xml->createElement('FolioRef', $referencia['folio_referencia']));
            $referenciaElement->appendChild($xml->createElement('FchRef', $referencia['fecha_referencia']));
            $referenciaElement->appendChild($xml->createElement('CodRef', $referencia['codigo_referencia']));
            
            if (isset($referencia['razon_referencia'])) {
                $referenciaElement->appendChild($xml->createElement('RazonRef', $referencia['razon_referencia']));
            }
            
            $elementosReferencia[] = $referenciaElement;
        }

        return $elementosReferencia;
    }

    private function crearTEDPlaceholder(DOMDocument $xml, int $folio, int $tipoDte, ?int $cafId = null): \DOMElement
    {
        // Placeholder para el TED - se completará después de la firma
        $ted = $xml->createElement('TED');
        $ted->setAttribute('version', '1.0');
        
        $dd = $xml->createElement('DD');
        $dd->appendChild($xml->createElement('RE', 'PLACEHOLDER_RUT_EMISOR'));
        $dd->appendChild($xml->createElement('TD', (string)$tipoDte));
        $dd->appendChild($xml->createElement('F', (string)$folio));
        $dd->appendChild($xml->createElement('FE', date('Y-m-d')));
        $dd->appendChild($xml->createElement('RR', 'PLACEHOLDER_RUT_RECEPTOR'));
        $dd->appendChild($xml->createElement('RSR', 'PLACEHOLDER_RAZON_SOCIAL_RECEPTOR'));
        $dd->appendChild($xml->createElement('MNT', 'PLACEHOLDER_MONTO_TOTAL'));
        $dd->appendChild($xml->createElement('IT1', 'PLACEHOLDER_ITEM_1'));
        $dd->appendChild($xml->createElement('CAF', 'PLACEHOLDER_CAF'));
        $dd->appendChild($xml->createElement('TSTED', date('Y-m-d\TH:i:s')));
        
        $ted->appendChild($dd);
        
        // Placeholder para firma del TED
        $frmt = $xml->createElement('FRMT');
        $frmt->setAttribute('algoritmo', 'SHA1withRSA');
        $frmt->nodeValue = 'PLACEHOLDER_FIRMA_TED';
        $ted->appendChild($frmt);

        return $ted;
    }
}
