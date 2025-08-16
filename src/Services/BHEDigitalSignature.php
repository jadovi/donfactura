<?php

declare(strict_types=1);

namespace DonFactura\DTE\Services;

/**
 * Servicio de firma digital simplificado para BHE (modo demo)
 */
class BHEDigitalSignature
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function firmarBHE(string $xml, string $rutProfesional): string
    {
        // Simulación de firma digital para BHE
        // En producción aquí iría la firma real con certificado
        
        // Generar firma simulada
        $timestedmp = date('Y-m-d\TH:i:s');
        $signatureValue = base64_encode(hash('sha256', $xml . $rutProfesional . $timestedmp, true));
        
        // Agregar elementos de firma al XML
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        
        // Encontrar el documento
        $documento = $dom->getElementsByTagName('Documento')->item(0);
        if (!$documento) {
            throw new \Exception('No se encontró elemento Documento en XML');
        }
        
        // Crear elemento TED (Timbre Electrónico Documento)
        $ted = $this->crearTED($dom, $rutProfesional, $timestedmp);
        $documento->appendChild($ted);
        
        // Crear elemento Signature (simulado)
        $signature = $this->crearSignature($dom, $signatureValue, $timestedmp);
        $documento->appendChild($signature);
        
        return $dom->saveXML();
    }

    private function crearTED(\DOMDocument $dom, string $rutProfesional, string $timestamp): \DOMElement
    {
        $ted = $dom->createElement('TED');
        $ted->setAttribute('version', '1.0');
        
        // DD (Datos del Documento)
        $dd = $dom->createElement('DD');
        
        // Obtener datos del documento para el TED
        $tipoDTE = $dom->getElementsByTagName('TipoDTE')->item(0);
        $folio = $dom->getElementsByTagName('Folio')->item(0);
        $fechaEmision = $dom->getElementsByTagName('FchEmis')->item(0);
        $rutEmisor = $dom->getElementsByTagName('RUTEmisor')->item(0);
        $rutReceptor = $dom->getElementsByTagName('RUTRecep')->item(0);
        $montoTotal = $dom->getElementsByTagName('MntTotal')->item(0);
        
        if ($tipoDTE) $dd->appendChild($dom->createElement('RE', $rutEmisor->nodeValue));
        if ($tipoDTE) $dd->appendChild($dom->createElement('TD', $tipoDTE->nodeValue));
        if ($folio) $dd->appendChild($dom->createElement('F', $folio->nodeValue));
        if ($fechaEmision) $dd->appendChild($dom->createElement('FE', $fechaEmision->nodeValue));
        if ($rutReceptor) $dd->appendChild($dom->createElement('RR', $rutReceptor->nodeValue));
        if ($montoTotal) $dd->appendChild($dom->createElement('MNT', $montoTotal->nodeValue));
        
        // Datos específicos BHE
        $dd->appendChild($dom->createElement('TSTED', $timestamp));
        
        $ted->appendChild($dd);
        
        // FRMT (Formato de firma - simulado)
        $frmt = $dom->createElement('FRMT');
        $frmt->setAttribute('algoritmo', 'SHA1withRSA');
        $frmt->nodeValue = base64_encode(hash('sha256', $dd->C14N(), true));
        $ted->appendChild($frmt);
        
        return $ted;
    }

    private function crearSignature(\DOMDocument $dom, string $signatureValue, string $timestamp): \DOMElement
    {
        $signature = $dom->createElement('Signature');
        $signature->setAttribute('xmlns', 'http://www.w3.org/2000/09/xmldsig#');
        
        // SignedInfo
        $signedInfo = $dom->createElement('SignedInfo');
        
        $canonicalizationMethod = $dom->createElement('CanonicalizationMethod');
        $canonicalizationMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($canonicalizationMethod);
        
        $signatureMethod = $dom->createElement('SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $signedInfo->appendChild($signatureMethod);
        
        $reference = $dom->createElement('Reference');
        $reference->setAttribute('URI', '');
        
        $transforms = $dom->createElement('Transforms');
        $transform = $dom->createElement('Transform');
        $transform->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($transform);
        $reference->appendChild($transforms);
        
        $digestMethod = $dom->createElement('DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $reference->appendChild($digestMethod);
        
        $digestValue = $dom->createElement('DigestValue', base64_encode(hash('sha1', $dom->C14N(), true)));
        $reference->appendChild($digestValue);
        
        $signedInfo->appendChild($reference);
        $signature->appendChild($signedInfo);
        
        // SignatureValue
        $signatureValueElement = $dom->createElement('SignatureValue', $signatureValue);
        $signature->appendChild($signatureValueElement);
        
        // KeyInfo (simulado)
        $keyInfo = $dom->createElement('KeyInfo');
        $keyName = $dom->createElement('KeyName', 'Certificado_BHE_' . str_replace('-', '', explode('-', hash('md5', $timestamp))[0]));
        $keyInfo->appendChild($keyName);
        $signature->appendChild($keyInfo);
        
        return $signature;
    }

    public function validarFirma(string $xmlFirmado): bool
    {
        // Validación simulada
        $dom = new \DOMDocument();
        $dom->loadXML($xmlFirmado);
        
        $signature = $dom->getElementsByTagName('Signature')->item(0);
        $ted = $dom->getElementsByTagName('TED')->item(0);
        
        return ($signature !== null && $ted !== null);
    }

    public function obtenerInfoFirma(string $xmlFirmado): array
    {
        $dom = new \DOMDocument();
        $dom->loadXML($xmlFirmado);
        
        $keyName = $dom->getElementsByTagName('KeyName')->item(0);
        $signatureValue = $dom->getElementsByTagName('SignatureValue')->item(0);
        $timestamp = $dom->getElementsByTagName('TSTED')->item(0);
        
        return [
            'certificado' => $keyName ? $keyName->nodeValue : 'No disponible',
            'algoritmo' => 'SHA1withRSA (simulado)',
            'timestamp' => $timestamp ? $timestamp->nodeValue : date('Y-m-d\TH:i:s'),
            'valida' => true,
            'modo' => 'DEMO - Firma simulada para pruebas'
        ];
    }

    public function generarTimbradoQR(array $datosBHE): string
    {
        // Generar datos para código QR específico de BHE
        $datosQR = [
            $datosBHE['rut_profesional'],
            '41', // Tipo DTE BHE
            $datosBHE['folio'],
            $datosBHE['fecha_emision'],
            $datosBHE['rut_pagador'],
            $datosBHE['monto_liquido']
        ];
        
        return implode(';', $datosQR);
    }
}
