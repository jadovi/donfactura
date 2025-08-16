<?php

declare(strict_types=1);

namespace DonFactura\DTE\Controllers;

use DonFactura\DTE\Services\PDFGenerator;
use DonFactura\DTE\Models\EmpresasConfigModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use PDO;

/**
 * Controlador para generación de PDFs de documentos DTE
 */
class PDFController
{
    private PDO $pdo;
    private LoggerInterface $logger;
    private array $config;
    private PDFGenerator $pdfGenerator;
    private EmpresasConfigModel $empresasModel;

    public function __construct(PDO $pdo, LoggerInterface $logger, array $config)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->config = $config;
        $this->pdfGenerator = new PDFGenerator($pdo, $config);
        $this->empresasModel = new EmpresasConfigModel($pdo);
    }

    public function generarPDF(Request $request, Response $response, array $args): Response
    {
        try {
            $dteId = (int) $args['id'];
            $params = $request->getQueryParams();
            $formato = $params['formato'] ?? 'carta';

            // Validar formato
            if (!in_array($formato, ['carta', '80mm'])) {
                return $this->respuestaError($response, ['Formato debe ser "carta" o "80mm"'], 400);
            }

            // Generar PDF
            $resultado = $this->pdfGenerator->generarPDF($dteId, $formato);

            if (!$resultado['success']) {
                return $this->respuestaError($response, [$resultado['error']], 500);
            }

            $this->logger->info('PDF generado exitosamente', [
                'dte_id' => $dteId,
                'formato' => $formato,
                'pdf_id' => $resultado['pdf_id']
            ]);

            return $this->respuestaExito($response, $resultado);

        } catch (\Exception $e) {
            $this->logger->error('Error al generar PDF', [
                'dte_id' => $args['id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function descargarPDF(Request $request, Response $response, array $args): Response
    {
        try {
            $pdfId = (int) $args['pdf_id'];

            // Obtener PDF de la base de datos
            $sql = "SELECT dp.*, d.tipo_dte, d.folio 
                    FROM documentos_pdf dp 
                    INNER JOIN documentos_dte d ON dp.dte_id = d.id 
                    WHERE dp.id = :pdf_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['pdf_id' => $pdfId]);
            $pdf = $stmt->fetch();

            if (!$pdf) {
                return $this->respuestaError($response, ['PDF no encontrado'], 404);
            }

            // Preparar headers para descarga
            $nombreArchivo = $pdf['nombre_archivo'];
            $contenido = $pdf['contenido_pdf'];

            $response = $response
                ->withHeader('Content-Type', 'application/pdf')
                ->withHeader('Content-Disposition', "attachment; filename=\"{$nombreArchivo}\"")
                ->withHeader('Content-Length', strlen($contenido));

            $response->getBody()->write($contenido);

            return $response;

        } catch (\Exception $e) {
            $this->logger->error('Error al descargar PDF', [
                'pdf_id' => $args['pdf_id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function listarPDFs(Request $request, Response $response, array $args): Response
    {
        try {
            $dteId = (int) $args['id'];
            
            $sql = "SELECT id, tipo_formato, nombre_archivo, fecha_generacion 
                    FROM documentos_pdf 
                    WHERE dte_id = :dte_id 
                    ORDER BY fecha_generacion DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['dte_id' => $dteId]);
            $pdfs = $stmt->fetchAll();

            return $this->respuestaExito($response, [
                'dte_id' => $dteId,
                'total_pdfs' => count($pdfs),
                'pdfs' => $pdfs
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error al listar PDFs', [
                'dte_id' => $args['id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function configurarEmpresa(Request $request, Response $response): Response
    {
        try {
            $data = $request->getParsedBody();
            
            // Validar datos requeridos
            $validacion = $this->validarDatosEmpresa($data);
            if (!$validacion['valido']) {
                return $this->respuestaError($response, $validacion['errores'], 400);
            }

            // Verificar si la empresa ya existe
            $empresaExistente = $this->empresasModel->obtenerPorRut($data['rut_empresa']);
            
            if ($empresaExistente) {
                // Actualizar
                $resultado = $this->empresasModel->actualizar($empresaExistente['id'], $data);
                $empresaId = $empresaExistente['id'];
                $accion = 'actualizada';
            } else {
                // Crear nueva
                $empresaId = $this->empresasModel->crear($data);
                $accion = 'creada';
            }

            $this->logger->info("Empresa {$accion}", [
                'empresa_id' => $empresaId,
                'rut' => $data['rut_empresa']
            ]);

            return $this->respuestaExito($response, [
                'id' => $empresaId,
                'rut_empresa' => $data['rut_empresa'],
                'accion' => $accion
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error al configurar empresa', [
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function subirLogo(Request $request, Response $response, array $args): Response
    {
        try {
            $empresaId = (int) $args['id'];
            $uploadedFiles = $request->getUploadedFiles();

            // Validar que se haya subido un archivo
            if (empty($uploadedFiles['logo'])) {
                return $this->respuestaError($response, ['Debe subir un archivo de logo'], 400);
            }

            $uploadedFile = $uploadedFiles['logo'];

            // Validar que no haya errores en la subida
            if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
                return $this->respuestaError($response, ['Error al subir el archivo'], 400);
            }

            // Leer contenido del archivo
            $logoContent = $uploadedFile->getStream()->getContents();
            $nombreArchivo = $uploadedFile->getClientFilename();
            $tipoMime = $uploadedFile->getClientMediaType();

            // Validar logo
            $validacion = $this->empresasModel->validarLogo($logoContent, $tipoMime);
            if (!$validacion['valido']) {
                return $this->respuestaError($response, [$validacion['error']], 400);
            }

            // Guardar logo
            $resultado = $this->empresasModel->subirLogo($empresaId, $logoContent, $nombreArchivo, $tipoMime);

            if (!$resultado) {
                return $this->respuestaError($response, ['Error al guardar el logo'], 500);
            }

            $this->logger->info('Logo subido exitosamente', [
                'empresa_id' => $empresaId,
                'archivo' => $nombreArchivo,
                'tamaño' => $validacion['info']['tamaño']
            ]);

            return $this->respuestaExito($response, [
                'empresa_id' => $empresaId,
                'logo_info' => $validacion['info'],
                'mensaje' => 'Logo subido exitosamente'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error al subir logo', [
                'empresa_id' => $args['id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function configurarFormatos(Request $request, Response $response, array $args): Response
    {
        try {
            $empresaId = (int) $args['id'];
            $data = $request->getParsedBody();

            $formatoCarta = $data['formato_carta'] ?? true;
            $formato80mm = $data['formato_80mm'] ?? true;

            $resultado = $this->empresasModel->configurarFormatos($empresaId, $formatoCarta, $formato80mm);

            if (!$resultado) {
                return $this->respuestaError($response, ['Error al configurar formatos'], 500);
            }

            return $this->respuestaExito($response, [
                'empresa_id' => $empresaId,
                'formato_carta' => $formatoCarta,
                'formato_80mm' => $formato80mm
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error al configurar formatos', [
                'empresa_id' => $args['id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function configurarColores(Request $request, Response $response, array $args): Response
    {
        try {
            $empresaId = (int) $args['id'];
            $data = $request->getParsedBody();

            $colorPrimario = $data['color_primario'] ?? '#000000';
            $colorSecundario = $data['color_secundario'] ?? '#666666';

            $resultado = $this->empresasModel->configurarColores($empresaId, $colorPrimario, $colorSecundario);

            if (!$resultado) {
                return $this->respuestaError($response, ['Error al configurar colores o formato de color inválido'], 400);
            }

            return $this->respuestaExito($response, [
                'empresa_id' => $empresaId,
                'color_primario' => $colorPrimario,
                'color_secundario' => $colorSecundario
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error al configurar colores', [
                'empresa_id' => $args['id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function obtenerConfiguracion(Request $request, Response $response, array $args): Response
    {
        try {
            $rutEmpresa = $args['rut'];
            $empresa = $this->empresasModel->obtenerPorRut($rutEmpresa);

            if (!$empresa) {
                return $this->respuestaError($response, ['Configuración de empresa no encontrada'], 404);
            }

            // No enviar el logo binario, solo indicar si existe
            $configuracion = $empresa;
            $configuracion['tiene_logo'] = !empty($empresa['logo_empresa']);
            unset($configuracion['logo_empresa']);

            return $this->respuestaExito($response, $configuracion);

        } catch (\Exception $e) {
            $this->logger->error('Error al obtener configuración', [
                'rut' => $args['rut'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    private function validarDatosEmpresa(array $data): array
    {
        $errores = [];

        // Validar campos requeridos
        $requeridos = ['rut_empresa', 'razon_social'];
        foreach ($requeridos as $campo) {
            if (!isset($data[$campo]) || empty($data[$campo])) {
                $errores[] = "Campo requerido: {$campo}";
            }
        }

        // Validar formato RUT
        if (isset($data['rut_empresa']) && !preg_match('/^\d{7,8}-[\dkK]$/', $data['rut_empresa'])) {
            $errores[] = "Formato de RUT inválido";
        }

        // Validar colores si se proporcionan
        if (isset($data['color_primario']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $data['color_primario'])) {
            $errores[] = "Color primario debe ser formato hexadecimal #RRGGBB";
        }

        if (isset($data['color_secundario']) && !preg_match('/^#[0-9A-Fa-f]{6}$/', $data['color_secundario'])) {
            $errores[] = "Color secundario debe ser formato hexadecimal #RRGGBB";
        }

        return [
            'valido' => empty($errores),
            'errores' => $errores
        ];
    }

    private function respuestaExito(Response $response, array $data): Response
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => $data
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function respuestaError(Response $response, array $errores, int $codigo = 400): Response
    {
        $response->getBody()->write(json_encode([
            'success' => false,
            'errors' => $errores
        ]));
        
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($codigo);
    }
}
