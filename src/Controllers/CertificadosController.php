<?php

declare(strict_types=1);

namespace DonFactura\DTE\Controllers;

use DonFactura\DTE\Models\CertificadosModel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use PDO;

/**
 * Controlador para gestión de certificados digitales
 */
class CertificadosController
{
    private PDO $pdo;
    private LoggerInterface $logger;
    private array $config;
    private CertificadosModel $certificadosModel;

    public function __construct(PDO $pdo, LoggerInterface $logger, array $config)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->config = $config;
        $this->certificadosModel = new CertificadosModel($pdo);
    }

    public function upload(Request $request, Response $response): Response
    {
        try {
            $uploadedFiles = $request->getUploadedFiles();
            $data = $request->getParsedBody();

            // Validar que se haya subido un archivo
            if (empty($uploadedFiles['certificado'])) {
                return $this->respuestaError($response, ['Debe subir un archivo de certificado'], 400);
            }

            /** @var UploadedFileInterface $uploadedFile */
            $uploadedFile = $uploadedFiles['certificado'];

            // Validar que no haya errores en la subida
            if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
                return $this->respuestaError($response, ['Error al subir el archivo'], 400);
            }

            // Validar extensión del archivo
            $nombreArchivo = $uploadedFile->getClientFilename();
            if (!$nombreArchivo || !str_ends_with(strtolower($nombreArchivo), '.pfx')) {
                return $this->respuestaError($response, ['El archivo debe ser un certificado .pfx'], 400);
            }

            // Validar que se proporcione la contraseña
            if (empty($data['password'])) {
                return $this->respuestaError($response, ['Contraseña del certificado es requerida'], 400);
            }

            // Leer contenido del archivo
            $contenidoPfx = $uploadedFile->getStream()->getContents();

            // Validar el certificado
            $validacion = $this->certificadosModel->validarCertificado($contenidoPfx, $data['password']);
            
            if (!$validacion['valido']) {
                return $this->respuestaError($response, [$validacion['error']], 400);
            }

            // Extraer información del certificado
            $infoCert = $validacion['info'];
            
            // Preparar datos para guardar
            $datosCertificado = [
                'nombre' => $data['nombre'] ?? $nombreArchivo,
                'archivo_pfx' => $contenidoPfx,
                'password_pfx' => $data['password'],
                'rut_empresa' => $infoCert['rut'] ?? $data['rut_empresa'] ?? '',
                'razon_social' => $data['razon_social'] ?? $infoCert['subject']['O'] ?? '',
                'fecha_vencimiento' => substr($infoCert['valid_to'], 0, 10), // Solo fecha, sin hora
            ];

            // Guardar certificado
            $certificadoId = $this->certificadosModel->crear($datosCertificado);

            // Guardar archivo físico si es necesario
            $rutaArchivo = $this->guardarArchivoCertificado($contenidoPfx, $certificadoId);

            $this->logger->info('Certificado subido exitosamente', [
                'certificado_id' => $certificadoId,
                'nombre' => $datosCertificado['nombre'],
                'rut_empresa' => $datosCertificado['rut_empresa']
            ]);

            return $this->respuestaExito($response, [
                'id' => $certificadoId,
                'nombre' => $datosCertificado['nombre'],
                'rut_empresa' => $datosCertificado['rut_empresa'],
                'razon_social' => $datosCertificado['razon_social'],
                'fecha_vencimiento' => $datosCertificado['fecha_vencimiento'],
                'ruta_archivo' => $rutaArchivo,
                'info_certificado' => $infoCert
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error al subir certificado', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function listar(Request $request, Response $response): Response
    {
        try {
            $certificados = $this->certificadosModel->listar();

            // No enviar las contraseñas ni los archivos binarios
            $certificadosLimpios = array_map(function($cert) {
                unset($cert['password_pfx'], $cert['archivo_pfx']);
                return $cert;
            }, $certificados);

            return $this->respuestaExito($response, [
                'total' => count($certificadosLimpios),
                'certificados' => $certificadosLimpios
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error al listar certificados', [
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function obtener(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int) $args['id'];
            $certificado = $this->certificadosModel->obtenerPorId($id);

            if (!$certificado) {
                return $this->respuestaError($response, ['Certificado no encontrado'], 404);
            }

            // No enviar la contraseña ni el archivo binario
            unset($certificado['password_pfx'], $certificado['archivo_pfx']);

            return $this->respuestaExito($response, $certificado);

        } catch (\Exception $e) {
            $this->logger->error('Error al obtener certificado', [
                'id' => $args['id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function eliminar(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int) $args['id'];
            
            // Verificar que el certificado existe
            $certificado = $this->certificadosModel->obtenerPorId($id);
            if (!$certificado) {
                return $this->respuestaError($response, ['Certificado no encontrado'], 404);
            }

            // Eliminar (marcar como inactivo)
            $eliminado = $this->certificadosModel->eliminar($id);

            if (!$eliminado) {
                return $this->respuestaError($response, ['Error al eliminar certificado'], 500);
            }

            $this->logger->info('Certificado eliminado', [
                'certificado_id' => $id,
                'nombre' => $certificado['nombre']
            ]);

            return $this->respuestaExito($response, [
                'id' => $id,
                'mensaje' => 'Certificado eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error al eliminar certificado', [
                'id' => $args['id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    public function validar(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int) $args['id'];
            $certificado = $this->certificadosModel->obtenerPorId($id);

            if (!$certificado) {
                return $this->respuestaError($response, ['Certificado no encontrado'], 404);
            }

            // Validar el certificado
            $validacion = $this->certificadosModel->validarCertificado(
                $certificado['archivo_pfx'], 
                $certificado['password_pfx']
            );

            $estadoValidacion = [
                'id' => $id,
                'valido' => $validacion['valido'],
                'fecha_validacion' => date('Y-m-d H:i:s')
            ];

            if ($validacion['valido']) {
                $estadoValidacion['info'] = $validacion['info'];
                $estadoValidacion['mensaje'] = 'Certificado válido y funcional';
            } else {
                $estadoValidacion['error'] = $validacion['error'];
            }

            $this->logger->info('Certificado validado', [
                'certificado_id' => $id,
                'valido' => $validacion['valido']
            ]);

            return $this->respuestaExito($response, $estadoValidacion);

        } catch (\Exception $e) {
            $this->logger->error('Error al validar certificado', [
                'id' => $args['id'] ?? null,
                'error' => $e->getMessage()
            ]);

            return $this->respuestaError($response, ['Error interno del servidor'], 500);
        }
    }

    private function guardarArchivoCertificado(string $contenido, int $certificadoId): string
    {
        $rutaCertificados = $this->config['paths']['certificates'];
        $nombreArchivo = "cert_{$certificadoId}.pfx";
        $rutaCompleta = $rutaCertificados . $nombreArchivo;

        // Crear directorio si no existe
        if (!is_dir($rutaCertificados)) {
            mkdir($rutaCertificados, 0755, true);
        }

        // Guardar archivo
        file_put_contents($rutaCompleta, $contenido);

        return $rutaCompleta;
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
