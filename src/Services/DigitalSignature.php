<?php

declare(strict_types=1);

namespace DonFactura\DTE\Services;

use DonFactura\DTE\Models\CertificadosModel;
use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use DOMDocument;
use DOMXPath;
use PDO;

/**
 * Servicio para firma digital de documentos DTE
 */
class DigitalSignature
{
    private PDO $pdo;
    private array $config;
    private CertificadosModel $certificadosModel;

    public function __construct(array $config, PDO $pdo = null)
    {
        $this->config = $config;
        if ($pdo) {
            $this->pdo = $pdo;
            $this->certificadosModel = new CertificadosModel($pdo);
        }
    }

    public function firmarDTE(string $xmlDte, string $rutEmisor): ?string
    {
        try {
            // Obtener certificado para el RUT emisor
            $certificado = $this->certificadosModel->obtenerCertificadoParaFirma($rutEmisor);
            
            if (!$certificado) {
                throw new \Exception("No se encontró certificado válido para el RUT: {$rutEmisor}");
            }

            // Cargar XML
            $xmlDoc = new DOMDocument();
            $xmlDoc->loadXML($xmlDte);
            $xmlDoc->formatOutput = false;
            $xmlDoc->preserveWhiteSpace = true;

            // Completar el TED (Timbre Electrónico de Documentos)
            $this->completarTED($xmlDoc, $rutEmisor);

            // Firmar el documento
            $xmlFirmado = $this->firmarXML($xmlDoc, $certificado);

            return $xmlFirmado;

        } catch (\Exception $e) {
            error_log("Error al firmar DTE: " . $e->getMessage());
            return null;
        }
    }

    public function firmarXML(DOMDocument $xmlDoc, array $certificado): string
    {
        // Extraer certificado PFX
        $certs = [];
        if (!openssl_pkcs12_read($certificado['archivo_pfx'], $certs, $certificado['password_pfx'])) {
            throw new \Exception("Error al leer el certificado PFX");
        }

        // Obtener el elemento documento a firmar
        $documento = $xmlDoc->getElementsByTagName('Documento')->item(0);
        if (!$documento) {
            throw new \Exception("No se encontró el elemento Documento en el XML");
        }

        // Crear objeto de firma digital
        $objDSig = new XMLSecurityDSig();
        $objDSig->setCanonicalMethod(XMLSecurityDSig::EXC_C14N);

        // Agregar referencia al documento
        $objDSig->addReference(
            $documento,
            XMLSecurityDSig::SHA1,
            [
                'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
                XMLSecurityDSig::EXC_C14N
            ],
            ['id_name' => 'ID', 'overwrite' => false]
        );

        // Crear clave privada
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, ['type' => 'private']);
        $objKey->loadKey($certs['pkey']);

        // Firmar
        $objDSig->sign($objKey);

        // Agregar certificado
        $objDSig->add509Cert($certs['cert']);

        // Insertar firma en el documento
        $objDSig->insertSignature($documento);

        return $xmlDoc->saveXML();
    }

    public function validarFirma(string $xmlFirmado): array
    {
        try {
            $xmlDoc = new DOMDocument();
            $xmlDoc->loadXML($xmlFirmado);

            $objXMLSecDSig = new XMLSecurityDSig();
            $objDSig = $objXMLSecDSig->locateSignature($xmlDoc);
            
            if (!$objDSig) {
                return [
                    'valida' => false,
                    'error' => 'No se encontró firma digital en el documento'
                ];
            }

            $objXMLSecDSig->canonicalizeSignedInfo();
            $objKey = $objXMLSecDSig->locateKey();
            
            if (!$objKey) {
                return [
                    'valida' => false,
                    'error' => 'No se encontró clave pública en la firma'
                ];
            }

            // Verificar firma
            $isValid = $objXMLSecDSig->verify($objKey);

            return [
                'valida' => $isValid,
                'error' => $isValid ? null : 'La firma digital no es válida'
            ];

        } catch (\Exception $e) {
            return [
                'valida' => false,
                'error' => 'Error al validar firma: ' . $e->getMessage()
            ];
        }
    }

    private function completarTED(DOMDocument $xmlDoc, string $rutEmisor): void
    {
        $xpath = new DOMXPath($xmlDoc);
        
        // Obtener datos del documento
        $tipoDte = $xpath->query('//IdDoc/TipoDTE')->item(0)?->nodeValue;
        $folio = $xpath->query('//IdDoc/Folio')->item(0)?->nodeValue;
        $fechaEmision = $xpath->query('//IdDoc/FchEmis')->item(0)?->nodeValue;
        $rutReceptor = $xpath->query('//Receptor/RUTRecep')->item(0)?->nodeValue;
        $razonSocialReceptor = $xpath->query('//Receptor/RznSocRecep')->item(0)?->nodeValue;
        $montoTotal = $xpath->query('//Totales/MntTotal')->item(0)?->nodeValue;
        
        // Obtener primer item para el TED
        $primerItem = $xpath->query('//Detalle[1]/NmbItem')->item(0)?->nodeValue ?? 'Producto/Servicio';

        // Obtener datos CAF (simulado por ahora)
        $cafData = $this->obtenerDatosCAF($tipoDte, $rutEmisor, $folio);

        // Actualizar placeholders en TED
        $tedElements = $xpath->query('//TED/DD/*');
        foreach ($tedElements as $element) {
            switch ($element->nodeName) {
                case 'RE':
                    $element->nodeValue = $rutEmisor;
                    break;
                case 'TD':
                    $element->nodeValue = $tipoDte;
                    break;
                case 'F':
                    $element->nodeValue = $folio;
                    break;
                case 'FE':
                    $element->nodeValue = $fechaEmision;
                    break;
                case 'RR':
                    $element->nodeValue = $rutReceptor;
                    break;
                case 'RSR':
                    $element->nodeValue = $razonSocialReceptor;
                    break;
                case 'MNT':
                    $element->nodeValue = $montoTotal;
                    break;
                case 'IT1':
                    $element->nodeValue = substr($primerItem, 0, 40); // Máximo 40 caracteres
                    break;
                case 'CAF':
                    $element->nodeValue = $cafData;
                    break;
                case 'TSTED':
                    $element->nodeValue = date('Y-m-d\TH:i:s');
                    break;
            }
        }

        // Generar firma del TED
        $ddElement = $xpath->query('//TED/DD')->item(0);
        if ($ddElement) {
            $ddString = $xmlDoc->saveXML($ddElement);
            $firmaTED = $this->generarFirmaTED($ddString, $rutEmisor);
            
            $frmtElement = $xpath->query('//TED/FRMT')->item(0);
            if ($frmtElement) {
                $frmtElement->nodeValue = $firmaTED;
            }
        }
    }

    private function obtenerDatosCAF(string $tipoDte, string $rutEmisor, string $folio): string
    {
        // Aquí deberías obtener el CAF real desde la base de datos
        // Por ahora retornamos un placeholder
        return "CAF_PLACEHOLDER_FOR_TYPE_{$tipoDte}_FOLIO_{$folio}";
    }

    private function generarFirmaTED(string $ddString, string $rutEmisor): string
    {
        try {
            $certificado = $this->certificadosModel->obtenerCertificadoParaFirma($rutEmisor);
            
            if (!$certificado) {
                return "FIRMA_TED_PLACEHOLDER";
            }

            // Extraer certificado PFX
            $certs = [];
            if (!openssl_pkcs12_read($certificado['archivo_pfx'], $certs, $certificado['password_pfx'])) {
                return "FIRMA_TED_ERROR";
            }

            // Generar hash SHA1 del DD
            $hash = sha1($ddString, true);

            // Firmar con clave privada
            $firma = '';
            if (openssl_sign($hash, $firma, $certs['pkey'], OPENSSL_ALGO_SHA1)) {
                return base64_encode($firma);
            }

            return "FIRMA_TED_ERROR";

        } catch (\Exception $e) {
            return "FIRMA_TED_EXCEPTION";
        }
    }

    public function generarCAF(int $tipoDte, string $rutEmpresa, int $folioDesde, int $folioHasta): array
    {
        try {
            $certificado = $this->certificadosModel->obtenerCertificadoParaFirma($rutEmpresa);
            
            if (!$certificado) {
                return [
                    'success' => false,
                    'error' => 'No se encontró certificado válido'
                ];
            }

            // Crear estructura CAF
            $xmlCAF = new DOMDocument('1.0', 'UTF-8');
            $xmlCAF->formatOutput = true;

            $autorizacion = $xmlCAF->createElement('AUTORIZACION');
            $autorizacion->setAttribute('version', '1.0');
            $xmlCAF->appendChild($autorizacion);

            $caf = $xmlCAF->createElement('CAF');
            $autorizacion->appendChild($caf);

            $da = $xmlCAF->createElement('DA');
            $caf->appendChild($da);

            // Datos de autorización
            $da->appendChild($xmlCAF->createElement('RE', $rutEmpresa));
            $da->appendChild($xmlCAF->createElement('RS', $certificado['razon_social']));
            $da->appendChild($xmlCAF->createElement('TD', $tipoDte));
            
            $rng = $xmlCAF->createElement('RNG');
            $rng->appendChild($xmlCAF->createElement('D', $folioDesde));
            $rng->appendChild($xmlCAF->createElement('H', $folioHasta));
            $da->appendChild($rng);
            
            $da->appendChild($xmlCAF->createElement('FA', date('Y-m-d')));
            $da->appendChild($xmlCAF->createElement('RSAPK', 'RSA_PUBLIC_KEY_PLACEHOLDER'));
            $da->appendChild($xmlCAF->createElement('IDK', '100'));

            // Firma del CAF
            $xmlCAFString = $xmlCAF->saveXML($da);
            $firmaCAF = $this->firmarCAF($xmlCAFString, $certificado);
            
            $frma = $xmlCAF->createElement('FRMA');
            $frma->setAttribute('algoritmo', 'SHA1withRSA');
            $frma->nodeValue = $firmaCAF;
            $caf->appendChild($frma);

            return [
                'success' => true,
                'xml_caf' => $xmlCAF->saveXML(),
                'tipo_dte' => $tipoDte,
                'folio_desde' => $folioDesde,
                'folio_hasta' => $folioHasta
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al generar CAF: ' . $e->getMessage()
            ];
        }
    }

    private function firmarCAF(string $daString, array $certificado): string
    {
        try {
            // Extraer certificado PFX
            $certs = [];
            if (!openssl_pkcs12_read($certificado['archivo_pfx'], $certs, $certificado['password_pfx'])) {
                return "FIRMA_CAF_ERROR";
            }

            // Generar hash SHA1
            $hash = sha1($daString, true);

            // Firmar con clave privada
            $firma = '';
            if (openssl_sign($hash, $firma, $certs['pkey'], OPENSSL_ALGO_SHA1)) {
                return base64_encode($firma);
            }

            return "FIRMA_CAF_ERROR";

        } catch (\Exception $e) {
            return "FIRMA_CAF_EXCEPTION";
        }
    }
}
