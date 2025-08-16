<?php

declare(strict_types=1);

namespace DonFactura\DTE\Services;

use DOMDocument;
use DateTime;

/**
 * Generador de XML para Boletas de Honorarios Electrónicas (BHE - DTE Tipo 41)
 */
class BHEXMLGenerator
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function generar(array $data, int $folio, array $calculos, array $profesional): string
    {
        $xml = new DOMDocument('1.0', 'ISO-8859-1');
        $xml->formatOutput = true;

        // Elemento raíz DTE
        $dte = $xml->createElement('DTE');
        $dte->setAttribute('version', '1.0');
        $xml->appendChild($dte);

        // Documento para BHE
        $documento = $xml->createElement('Documento');
        $documento->setAttribute('ID', 'DTE-41');
        $dte->appendChild($documento);

        // Encabezado BHE
        $encabezado = $this->crearEncabezadoBHE($xml, $data, $folio, $calculos, $profesional);
        $documento->appendChild($encabezado);

        // Detalle BHE (servicios profesionales)
        $detalles = $this->crearDetalleBHE($xml, $data, $calculos);
        foreach ($detalles as $detalle) {
            $documento->appendChild($detalle);
        }

        // SubTotInfo para BHE
        $subTotInfo = $this->crearSubTotInfoBHE($xml, $calculos);
        $documento->appendChild($subTotInfo);

        // DscRcgGlobal para retención de honorarios
        $descuentoGlobal = $this->crearDescuentoRetencion($xml, $calculos);
        $documento->appendChild($descuentoGlobal);

        return $xml->saveXML();
    }

    private function crearEncabezadoBHE(DOMDocument $xml, array $data, int $folio, array $calculos, array $profesional): \DOMElement
    {
        $encabezado = $xml->createElement('Encabezado');

        // IdDoc - Identificación del documento
        $idDoc = $xml->createElement('IdDoc');
        $idDoc->appendChild($xml->createElement('TipoDTE', '41')); // BHE
        $idDoc->appendChild($xml->createElement('Folio', (string) $folio));
        $idDoc->appendChild($xml->createElement('FchEmis', date('Y-m-d')));
        
        // Indicador de servicios (obligatorio para BHE)
        $idDoc->appendChild($xml->createElement('IndServicio', '3')); // Servicios
        
        // Forma de pago (por defecto contado)
        $formaPago = $data['forma_pago'] ?? 1; // 1 = Contado
        $idDoc->appendChild($xml->createElement('FmaPago', (string) $formaPago));
        
        if ($formaPago == 2) { // Crédito
            $fechaVencimiento = $data['fecha_vencimiento'] ?? date('Y-m-d', strtotime('+30 days'));
            $idDoc->appendChild($xml->createElement('FchVenc', $fechaVencimiento));
        }

        $encabezado->appendChild($idDoc);

        // Emisor (Profesional)
        $emisor = $xml->createElement('Emisor');
        $emisor->appendChild($xml->createElement('RUTEmisor', $data['profesional']['rut']));
        
        // Razón social del profesional
        $razonSocial = trim($profesional['nombres'] . ' ' . $profesional['apellido_paterno'] . ' ' . ($profesional['apellido_materno'] ?? ''));
        $emisor->appendChild($xml->createElement('RznSoc', $this->limpiarTexto($razonSocial)));
        
        $emisor->appendChild($xml->createElement('GiroEmis', $this->limpiarTexto($profesional['profesion'] ?? 'PROFESIONAL INDEPENDIENTE')));
        
        // Actividades económicas (código SII para profesionales independientes)
        $actEco = $xml->createElement('Acteco', '749900'); // Otras actividades profesionales
        $emisor->appendChild($actEco);
        
        if (!empty($profesional['direccion'])) {
            $emisor->appendChild($xml->createElement('DirOrigen', $this->limpiarTexto($profesional['direccion'])));
        }
        
        if (!empty($profesional['comuna'])) {
            $emisor->appendChild($xml->createElement('CmnaOrigen', $this->limpiarTexto($profesional['comuna'])));
        }
        
        if (!empty($profesional['region'])) {
            $emisor->appendChild($xml->createElement('CiudadOrigen', $this->limpiarTexto($profesional['region'])));
        }

        $encabezado->appendChild($emisor);

        // Receptor (Pagador/Cliente)
        $receptor = $xml->createElement('Receptor');
        $receptor->appendChild($xml->createElement('RUTRecep', $data['pagador']['rut']));
        $receptor->appendChild($xml->createElement('RznSocRecep', $this->limpiarTexto($data['pagador']['nombre'])));
        
        if (!empty($data['pagador']['direccion'])) {
            $receptor->appendChild($xml->createElement('DirRecep', $this->limpiarTexto($data['pagador']['direccion'])));
        }
        
        if (!empty($data['pagador']['comuna'])) {
            $receptor->appendChild($xml->createElement('CmnaRecep', $this->limpiarTexto($data['pagador']['comuna'])));
        }

        $encabezado->appendChild($receptor);

        // Totales BHE
        $totales = $xml->createElement('Totales');
        $totales->appendChild($xml->createElement('MntNeto', '0')); // No aplica concepto de neto en BHE
        $totales->appendChild($xml->createElement('MntExe', (string) round($calculos['monto_bruto']))); // Monto exento = bruto
        $totales->appendChild($xml->createElement('MntTotal', (string) round($calculos['monto_liquido']))); // Total = líquido después de retención

        $encabezado->appendChild($totales);

        return $encabezado;
    }

    private function crearDetalleBHE(DOMDocument $xml, array $data, array $calculos): array
    {
        $detalles = [];

        // Detalle del servicio profesional
        $detalle = $xml->createElement('Detalle');
        $detalle->appendChild($xml->createElement('NroLinDet', '1'));
        
        // Descripción detallada del servicio
        $descripcionServicio = $this->limpiarTexto($data['servicios']['descripcion']);
        $detalle->appendChild($xml->createElement('NmbItem', $descripcionServicio));
        
        // Información del período de servicios
        $periodoDesde = $data['servicios']['periodo_desde'];
        $periodoHasta = $data['servicios']['periodo_hasta'];
        $descripcionPeriodo = "Servicios período: {$periodoDesde} al {$periodoHasta}";
        $detalle->appendChild($xml->createElement('DscItem', $this->limpiarTexto($descripcionPeriodo)));
        
        $detalle->appendChild($xml->createElement('QtyItem', '1')); // Cantidad siempre 1 para servicios
        $detalle->appendChild($xml->createElement('UnmdItem', 'UN')); // Unidad
        $detalle->appendChild($xml->createElement('PrcItem', (string) round($calculos['monto_bruto']))); // Precio unitario = monto bruto
        $detalle->appendChild($xml->createElement('MontoItem', (string) round($calculos['monto_bruto']))); // Monto total del ítem

        $detalles[] = $detalle;

        return $detalles;
    }

    private function crearSubTotInfoBHE(DOMDocument $xml, array $calculos): \DOMElement
    {
        $subTotInfo = $xml->createElement('SubTotInfo');
        $subTotInfo->appendChild($xml->createElement('NroSTI', '1'));
        $subTotInfo->appendChild($xml->createElement('GlosaSTI', 'SERVICIOS PROFESIONALES'));
        $subTotInfo->appendChild($xml->createElement('OrdenSTI', '1'));
        $subTotInfo->appendChild($xml->createElement('SubTotNetoSTI', '0')); // No aplica neto
        $subTotInfo->appendChild($xml->createElement('SubTotIVASTI', '0')); // No aplica IVA
        $subTotInfo->appendChild($xml->createElement('SubTotAdicSTI', '0'));
        $subTotInfo->appendChild($xml->createElement('SubTotExeSTI', (string) round($calculos['monto_bruto']))); // Exento = bruto

        return $subTotInfo;
    }

    private function crearDescuentoRetencion(DOMDocument $xml, array $calculos): \DOMElement
    {
        $descuento = $xml->createElement('DscRcgGlobal');
        $descuento->appendChild($xml->createElement('NroLinDR', '1'));
        $descuento->appendChild($xml->createElement('TpoMov', 'D')); // D = Descuento
        $descuento->appendChild($xml->createElement('GlosaDR', 'RETENCION HONORARIOS ' . $calculos['porcentaje_retencion'] . '%'));
        $descuento->appendChild($xml->createElement('TpoValor', '%')); // Porcentaje
        $descuento->appendChild($xml->createElement('ValorDR', (string) $calculos['porcentaje_retencion']));
        $descuento->appendChild($xml->createElement('ValorDROtrMnda', '0'));
        $descuento->appendChild($xml->createElement('IndExeDR', '1')); // Se aplica sobre monto exento

        return $descuento;
    }

    public function generarBoletaHonorarios(array $datosCompletos): string
    {
        $xml = new DOMDocument('1.0', 'ISO-8859-1');
        $xml->formatOutput = true;

        // Envío SII para BHE
        $envioSII = $xml->createElement('EnvioDTE');
        $envioSII->setAttribute('version', '1.0');
        $envioSII->setAttribute('xmlns', 'http://www.sii.cl/SiiDte');
        $envioSII->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $envioSII->setAttribute('xsi:schemaLocation', 'http://www.sii.cl/SiiDte EnvioDTE_v10.xsd');
        $xml->appendChild($envioSII);

        // SetDTE
        $setDTE = $xml->createElement('SetDTE');
        $setDTE->setAttribute('ID', 'SetDTE-BHE-' . date('YmdHis'));
        $envioSII->appendChild($setDTE);

        // Caratula
        $caratula = $this->crearCaratulaBHE($xml, $datosCompletos);
        $setDTE->appendChild($caratula);

        // DTE - usar el XML ya generado
        $xmlDTE = $this->generar(
            $datosCompletos['data'],
            $datosCompletos['folio'],
            $datosCompletos['calculos'],
            $datosCompletos['profesional']
        );

        // Importar el DTE al documento principal
        $domDTE = new DOMDocument();
        $domDTE->loadXML($xmlDTE);
        $importedDTE = $xml->importNode($domDTE->documentElement, true);
        $setDTE->appendChild($importedDTE);

        return $xml->saveXML();
    }

    private function crearCaratulaBHE(DOMDocument $xml, array $datosCompletos): \DOMElement
    {
        $caratula = $xml->createElement('Caratula');
        $caratula->setAttribute('version', '1.0');

        $caratula->appendChild($xml->createElement('RutEmisor', $datosCompletos['data']['profesional']['rut']));
        $caratula->appendChild($xml->createElement('RutEnvia', $datosCompletos['data']['profesional']['rut'])); // El profesional envía
        $caratula->appendChild($xml->createElement('RutReceptor', '60803000-K')); // RUT SII
        $caratula->appendChild($xml->createElement('FchResol', '2014-04-24')); // Fecha resolución SII para BHE
        $caratula->appendChild($xml->createElement('NroResol', '40')); // Número resolución SII
        $caratula->appendChild($xml->createElement('TmstFirmaEnv', date('Y-m-d\TH:i:s')));

        // SubTotDTE para BHE
        $subTotDTE = $xml->createElement('SubTotDTE');
        $subTotDTE->appendChild($xml->createElement('TpoDTE', '41'));
        $subTotDTE->appendChild($xml->createElement('NroDTE', '1'));
        $caratula->appendChild($subTotDTE);

        return $caratula;
    }

    private function limpiarTexto(string $texto): string
    {
        // Limpiar texto para XML
        $texto = trim($texto);
        $texto = mb_strtoupper($texto, 'UTF-8');
        
        // Reemplazar caracteres especiales
        $buscar = ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ', 'Ü'];
        $reemplazar = ['A', 'E', 'I', 'O', 'U', 'N', 'U'];
        $texto = str_replace($buscar, $reemplazar, $texto);
        
        // Remover caracteres no permitidos en XML
        $texto = preg_replace('/[^\x20-\x7E]/', '', $texto);
        
        return $texto;
    }

    public function validarEstructuraBHE(string $xml): array
    {
        $errores = [];

        try {
            $dom = new DOMDocument();
            $dom->loadXML($xml);

            // Validar elementos requeridos para BHE
            $xpath = new \DOMXPath($dom);

            // Tipo de documento debe ser 41
            $tipoDTE = $xpath->query('//TipoDTE')->item(0);
            if (!$tipoDTE || $tipoDTE->nodeValue !== '41') {
                $errores[] = 'TipoDTE debe ser 41 para BHE';
            }

            // Debe tener indicador de servicios
            $indServicio = $xpath->query('//IndServicio')->item(0);
            if (!$indServicio || $indServicio->nodeValue !== '3') {
                $errores[] = 'IndServicio debe ser 3 para servicios profesionales';
            }

            // Validar estructura de montos
            $montoExe = $xpath->query('//MntExe')->item(0);
            $montoTotal = $xpath->query('//MntTotal')->item(0);
            
            if (!$montoExe) {
                $errores[] = 'Falta MntExe (monto exento)';
            }
            
            if (!$montoTotal) {
                $errores[] = 'Falta MntTotal';
            }

            // Validar descuento por retención
            $descuentos = $xpath->query('//DscRcgGlobal');
            if ($descuentos->length === 0) {
                $errores[] = 'Falta descuento por retención de honorarios';
            }

        } catch (\Exception $e) {
            $errores[] = 'Error al validar XML: ' . $e->getMessage();
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores
        ];
    }
}
