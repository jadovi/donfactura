<?php

namespace DonFactura\DTE\Services;

use PDO;
use Exception;
use DOMDocument;
use DOMElement;

/**
 * Servicio para gestión de CAF (Correlativo de Autorización de Folios)
 * Cumple con normativa SII Chile para facturación electrónica
 */
class CAFService
{
    private PDO $pdo;
    private array $config;
    
    public function __construct(array $config, PDO $pdo)
    {
        $this->config = $config;
        $this->pdo = $pdo;
    }
    
    /**
     * Obtener el siguiente folio disponible para un tipo de DTE y RUT
     */
    public function obtenerSiguienteFolio(int $tipoDte, string $rutEmisor): array
    {
        try {
            // Buscar CAF activo para el tipo de documento y RUT
            $stmt = $this->pdo->prepare("
                SELECT id, folio_actual, folio_hasta, folios_disponibles, estado, fecha_vencimiento
                FROM caf_folios 
                WHERE tipo_dte = ? AND rut_emisor = ? AND estado = 'activo' 
                AND fecha_vencimiento >= CURDATE()
                AND folios_disponibles > 0
                ORDER BY folio_actual ASC
                LIMIT 1
            ");
            
            $stmt->execute([$tipoDte, $rutEmisor]);
            $caf = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$caf) {
                return [
                    'success' => false,
                    'error' => "No hay CAF activo disponible para tipo DTE {$tipoDte} y RUT {$rutEmisor}",
                    'code' => 'CAF_NO_DISPONIBLE'
                ];
            }
            
            // Verificar si el folio actual está dentro del rango
            if ($caf['folio_actual'] > $caf['folio_hasta']) {
                return [
                    'success' => false,
                    'error' => "CAF agotado para tipo DTE {$tipoDte}",
                    'code' => 'CAF_AGOTADO'
                ];
            }
            
            // Verificar si el folio ya fue usado
            $stmtFolioUsado = $this->pdo->prepare("
                SELECT id FROM folios_usados 
                WHERE caf_id = ? AND folio = ?
            ");
            $stmtFolioUsado->execute([$caf['id'], $caf['folio_actual']]);
            
            if ($stmtFolioUsado->fetch()) {
                // El folio ya fue usado, buscar el siguiente disponible
                $stmtSiguiente = $this->pdo->prepare("
                    SELECT MIN(f.folio) as siguiente_folio
                    FROM (
                        SELECT folio_desde + n - 1 as folio
                        FROM (
                            SELECT 1 as n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION
                            SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10
                        ) numbers
                        CROSS JOIN (
                            SELECT folio_desde, folio_hasta 
                            FROM caf_folios 
                            WHERE id = ?
                        ) caf
                        WHERE folio_desde + n - 1 <= folio_hasta
                    ) f
                    WHERE f.folio NOT IN (
                        SELECT folio FROM folios_usados WHERE caf_id = ?
                    )
                ");
                $stmtSiguiente->execute([$caf['id'], $caf['id']]);
                $siguiente = $stmtSiguiente->fetch(PDO::FETCH_ASSOC);
                
                if (!$siguiente || !$siguiente['siguiente_folio']) {
                    return [
                        'success' => false,
                        'error' => "No hay folios disponibles en el CAF",
                        'code' => 'FOLIOS_AGOTADOS'
                    ];
                }
                
                $folioDisponible = $siguiente['siguiente_folio'];
            } else {
                $folioDisponible = $caf['folio_actual'];
            }
            
            return [
                'success' => true,
                'data' => [
                    'caf_id' => $caf['id'],
                    'folio' => $folioDisponible,
                    'tipo_dte' => $tipoDte,
                    'rut_emisor' => $rutEmisor,
                    'folios_disponibles' => $caf['folios_disponibles']
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener folio: ' . $e->getMessage(),
                'code' => 'ERROR_INTERNO'
            ];
        }
    }
    
    /**
     * Asignar un folio a un DTE y marcarlo como usado
     */
    public function asignarFolio(int $cafId, int $folio, int $dteId, int $tipoDte, string $rutEmisor): array
    {
        try {
            $this->pdo->beginTransaction();
            
            // Verificar que el folio no esté usado
            $stmtVerificar = $this->pdo->prepare("
                SELECT id FROM folios_usados 
                WHERE caf_id = ? AND folio = ?
            ");
            $stmtVerificar->execute([$cafId, $folio]);
            
            if ($stmtVerificar->fetch()) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'error' => "El folio {$folio} ya ha sido usado",
                    'code' => 'FOLIO_YA_USADO'
                ];
            }
            
            // Registrar el folio como usado
            $stmtUsado = $this->pdo->prepare("
                INSERT INTO folios_usados (caf_id, folio, dte_id, tipo_dte, rut_emisor)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtUsado->execute([$cafId, $folio, $dteId, $tipoDte, $rutEmisor]);
            
            // Actualizar el folio actual en el CAF
            $stmtActualizar = $this->pdo->prepare("
                UPDATE caf_folios 
                SET folio_actual = ?, folios_disponibles = folios_disponibles - 1
                WHERE id = ?
            ");
            $stmtActualizar->execute([$folio + 1, $cafId]);
            
            // Verificar si el CAF se agotó
            $stmtVerificarAgotado = $this->pdo->prepare("
                SELECT folios_disponibles FROM caf_folios WHERE id = ?
            ");
            $stmtVerificarAgotado->execute([$cafId]);
            $cafActual = $stmtVerificarAgotado->fetch(PDO::FETCH_ASSOC);
            
            if ($cafActual && $cafActual['folios_disponibles'] <= 0) {
                $stmtMarcarAgotado = $this->pdo->prepare("
                    UPDATE caf_folios SET estado = 'agotado' WHERE id = ?
                ");
                $stmtMarcarAgotado->execute([$cafId]);
                
                // Registrar log de agotamiento
                $this->registrarLog($cafId, null, 'agotamiento', $tipoDte, $rutEmisor, $folio, 
                    "CAF agotado - Folio {$folio} fue el último disponible");
            }
            
            // Actualizar el DTE con el folio asignado
            $stmtActualizarDte = $this->pdo->prepare("
                UPDATE documentos_dte 
                SET folio = ?, caf_id = ? 
                WHERE id = ?
            ");
            $stmtActualizarDte->execute([$folio, $cafId, $dteId]);
            
            // Registrar log de asignación
            $this->registrarLog($cafId, null, 'asignacion_folio', $tipoDte, $rutEmisor, $folio, 
                "Folio {$folio} asignado al DTE {$dteId}");
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'data' => [
                    'caf_id' => $cafId,
                    'folio' => $folio,
                    'dte_id' => $dteId,
                    'folios_disponibles' => $cafActual['folios_disponibles'] - 1
                ]
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'error' => 'Error al asignar folio: ' . $e->getMessage(),
                'code' => 'ERROR_INTERNO'
            ];
        }
    }
    
    /**
     * Solicitar nuevo CAF al SII
     */
    public function solicitarCAF(int $tipoDte, string $rutEmisor, int $cantidadFolios): array
    {
        try {
            // Crear registro de solicitud
            $stmtSolicitud = $this->pdo->prepare("
                INSERT INTO solicitudes_caf (tipo_dte, rut_emisor, cantidad_folios, estado)
                VALUES (?, ?, ?, 'pendiente')
            ");
            $stmtSolicitud->execute([$tipoDte, $rutEmisor, $cantidadFolios]);
            $solicitudId = $this->pdo->lastInsertId();
            
            // Generar XML de solicitud según especificaciones SII
            $xmlSolicitud = $this->generarXMLSolicitudCAF($tipoDte, $rutEmisor, $cantidadFolios);
            
            // Actualizar solicitud con XML
            $stmtActualizar = $this->pdo->prepare("
                UPDATE solicitudes_caf 
                SET xml_solicitud = ?, estado = 'procesando'
                WHERE id = ?
            ");
            $stmtActualizar->execute([$xmlSolicitud, $solicitudId]);
            
            // Enviar solicitud al SII (simulado para desarrollo)
            $respuestaSII = $this->enviarSolicitudSII($xmlSolicitud, $tipoDte, $rutEmisor);
            
            if ($respuestaSII['success']) {
                // Procesar respuesta del SII
                $resultado = $this->procesarRespuestaCAF($solicitudId, $respuestaSII['data']);
                
                // Registrar log
                $this->registrarLog(null, $solicitudId, 'solicitud', $tipoDte, $rutEmisor, null, 
                    "Solicitud de CAF procesada exitosamente");
                
                return $resultado;
            } else {
                // Marcar solicitud como rechazada
                $stmtRechazar = $this->pdo->prepare("
                    UPDATE solicitudes_caf 
                    SET estado = 'rechazada', mensaje_error = ?, fecha_respuesta = NOW()
                    WHERE id = ?
                ");
                $stmtRechazar->execute([$respuestaSII['error'], $solicitudId]);
                
                // Registrar log de error
                $this->registrarLog(null, $solicitudId, 'error', $tipoDte, $rutEmisor, null, 
                    "Error en solicitud de CAF: " . $respuestaSII['error']);
                
                return $respuestaSII;
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al solicitar CAF: ' . $e->getMessage(),
                'code' => 'ERROR_INTERNO'
            ];
        }
    }
    
    /**
     * Obtener CAF disponibles para un tipo de documento
     */
    public function obtenerCAFDisponibles(?int $tipoDte = null, string $rutEmisor = null): array
    {
        try {
            $sql = "
                SELECT id, tipo_dte, rut_emisor, folio_desde, folio_hasta, folio_actual,
                       cantidad_folios, folios_disponibles, fecha_autorizacion, fecha_vencimiento,
                       estado, created_at
                FROM caf_folios 
                WHERE 1=1
            ";
            $params = [];
            
            if ($tipoDte) {
                $sql .= " AND tipo_dte = ?";
                $params[] = $tipoDte;
            }
            
            if ($rutEmisor) {
                $sql .= " AND rut_emisor = ?";
                $params[] = $rutEmisor;
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $cafs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'data' => [
                    'cafs' => $cafs,
                    'total' => count($cafs)
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener CAF: ' . $e->getMessage(),
                'code' => 'ERROR_INTERNO'
            ];
        }
    }
    
    /**
     * Verificar estado de CAF y actualizar si es necesario
     */
    public function verificarEstadoCAF(): array
    {
        try {
            // Marcar CAF vencidos
            $stmtVencidos = $this->pdo->prepare("
                UPDATE caf_folios 
                SET estado = 'vencido' 
                WHERE fecha_vencimiento < CURDATE() AND estado = 'activo'
            ");
            $stmtVencidos->execute();
            $vencidos = $stmtVencidos->rowCount();
            
            // Marcar CAF agotados
            $stmtAgotados = $this->pdo->prepare("
                UPDATE caf_folios 
                SET estado = 'agotado' 
                WHERE folios_disponibles <= 0 AND estado = 'activo'
            ");
            $stmtAgotados->execute();
            $agotados = $stmtAgotados->rowCount();
            
            return [
                'success' => true,
                'data' => [
                    'cafs_vencidos' => $vencidos,
                    'cafs_agotados' => $agotados,
                    'mensaje' => "Verificación completada: {$vencidos} CAF vencidos, {$agotados} CAF agotados"
                ]
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al verificar estado CAF: ' . $e->getMessage(),
                'code' => 'ERROR_INTERNO'
            ];
        }
    }
    
    /**
     * Generar XML de solicitud de CAF según especificaciones SII
     */
    private function generarXMLSolicitudCAF(int $tipoDte, string $rutEmisor, int $cantidadFolios): string
    {
        $xml = new DOMDocument('1.0', 'ISO-8859-1');
        $xml->formatOutput = true;
        
        $root = $xml->createElement('AUTORIZACION');
        $root->setAttribute('version', '1.0');
        $xml->appendChild($root);
        
        // Datos de autorización
        $da = $xml->createElement('DA');
        $root->appendChild($da);
        
        $da->appendChild($xml->createElement('RE', $rutEmisor)); // RUT Emisor
        $da->appendChild($xml->createElement('TD', (string)$tipoDte)); // Tipo Documento
        $da->appendChild($xml->createElement('F', '1')); // Folio desde
        $da->appendChild($xml->createElement('FA', (string)$cantidadFolios)); // Folio hasta
        $da->appendChild($xml->createElement('RS', 'EMPRESA SOLICITANTE')); // Razón Social
        $da->appendChild($xml->createElement('IDK', '123456789')); // ID Key
        
        // Firma (placeholder para desarrollo)
        $frma = $xml->createElement('FRMA');
        $frma->setAttribute('algoritmo', 'SHA1withRSA');
        $frma->textContent = 'firma_digital_placeholder';
        $root->appendChild($frma);
        
        return $xml->saveXML();
    }
    
    /**
     * Enviar solicitud al SII (simulado para desarrollo)
     */
    private function enviarSolicitudSII(string $xmlSolicitud, int $tipoDte, string $rutEmisor): array
    {
        // En desarrollo, simulamos una respuesta exitosa del SII
        // En producción, aquí iría la comunicación real con el SII
        
        $folioDesde = rand(1, 1000);
        $folioHasta = $folioDesde + 999;
        
        $xmlRespuesta = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>
        <CAF version=\"1.0\">
            <DA>
                <RE>{$rutEmisor}</RE>
                <TD>{$tipoDte}</TD>
                <F>{$folioDesde}</F>
                <FA>{$folioHasta}</FA>
                <RS>EMPRESA AUTORIZADA</RS>
                <IDK>123456789</IDK>
            </DA>
            <FRMA algoritmo=\"SHA1withRSA\">firma_digital_sii</FRMA>
        </CAF>";
        
        return [
            'success' => true,
            'data' => [
                'xml_respuesta' => $xmlRespuesta,
                'folio_desde' => $folioDesde,
                'folio_hasta' => $folioHasta,
                'fecha_autorizacion' => date('Y-m-d'),
                'fecha_vencimiento' => date('Y-m-d', strtotime('+2 years'))
            ]
        ];
    }
    
    /**
     * Procesar respuesta del SII y crear CAF
     */
    private function procesarRespuestaCAF(int $solicitudId, array $respuestaSII): array
    {
        try {
            $this->pdo->beginTransaction();
            
            // Crear nuevo CAF
            $stmtCAF = $this->pdo->prepare("
                INSERT INTO caf_folios (
                    tipo_dte, rut_emisor, folio_desde, folio_hasta, folio_actual,
                    cantidad_folios, folios_disponibles, fecha_autorizacion, fecha_vencimiento,
                    xml_caf, estado, respuesta_sii
                ) VALUES (
                    (SELECT tipo_dte FROM solicitudes_caf WHERE id = ?),
                    (SELECT rut_emisor FROM solicitudes_caf WHERE id = ?),
                    ?, ?, ?,
                    (? - ? + 1), (? - ? + 1), ?, ?,
                    ?, 'activo', ?
                )
            ");
            
            $stmtCAF->execute([
                $solicitudId, $solicitudId,
                $respuestaSII['folio_desde'], $respuestaSII['folio_hasta'], $respuestaSII['folio_desde'],
                $respuestaSII['folio_hasta'], $respuestaSII['folio_desde'], $respuestaSII['folio_hasta'], $respuestaSII['folio_desde'],
                $respuestaSII['fecha_autorizacion'], $respuestaSII['fecha_vencimiento'],
                $respuestaSII['xml_respuesta'], json_encode($respuestaSII)
            ]);
            
            $cafId = $this->pdo->lastInsertId();
            
            // Actualizar solicitud como aprobada
            $stmtActualizar = $this->pdo->prepare("
                UPDATE solicitudes_caf 
                SET estado = 'aprobada', xml_respuesta = ?, respuesta_sii = ?, fecha_respuesta = NOW()
                WHERE id = ?
            ");
            $stmtActualizar->execute([
                $respuestaSII['xml_respuesta'],
                json_encode($respuestaSII),
                $solicitudId
            ]);
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'data' => [
                    'caf_id' => $cafId,
                    'solicitud_id' => $solicitudId,
                    'folio_desde' => $respuestaSII['folio_desde'],
                    'folio_hasta' => $respuestaSII['folio_hasta'],
                    'cantidad_folios' => $respuestaSII['folio_hasta'] - $respuestaSII['folio_desde'] + 1,
                    'mensaje' => 'CAF creado exitosamente'
                ]
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'error' => 'Error al procesar respuesta CAF: ' . $e->getMessage(),
                'code' => 'ERROR_INTERNO'
            ];
        }
    }
    
    /**
     * Registrar log de operaciones CAF
     */
    private function registrarLog(?int $cafId, ?int $solicitudId, string $operacion, ?int $tipoDte, ?string $rutEmisor, ?int $folio, string $mensaje, array $datosAdicionales = []): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO caf_logs (caf_id, solicitud_id, operacion, tipo_dte, rut_emisor, folio, mensaje, datos_adicionales)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $cafId,
                $solicitudId,
                $operacion,
                $tipoDte,
                $rutEmisor,
                $folio,
                $mensaje,
                json_encode($datosAdicionales)
            ]);
        } catch (Exception $e) {
            // Silenciar errores de logging para no afectar operaciones principales
            error_log("Error al registrar log CAF: " . $e->getMessage());
        }
    }
}
