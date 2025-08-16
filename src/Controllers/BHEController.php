<?php

declare(strict_types=1);

namespace DonFactura\DTE\Controllers;

use DonFactura\DTE\Services\BHEService;
use DonFactura\DTE\Services\BHEPDFGenerator;
use DonFactura\DTE\Models\ProfesionalesModel;
use PDO;

/**
 * Controlador para Boletas de Honorarios Electrónicas (BHE - DTE Tipo 41)
 */
class BHEController
{
    private PDO $pdo;
    private array $config;
    private BHEService $bheService;
    private BHEPDFGenerator $pdfGenerator;
    private ProfesionalesModel $profesionalesModel;

    public function __construct(PDO $pdo, array $config)
    {
        $this->pdo = $pdo;
        $this->config = $config;
        $this->bheService = new BHEService($pdo, $config);
        $this->pdfGenerator = new BHEPDFGenerator($config);
        $this->profesionalesModel = new ProfesionalesModel($pdo);
    }

    public function generar(): array
    {
        try {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data) {
                return [
                    'success' => false,
                    'error' => 'Datos JSON inválidos'
                ];
            }

            $resultado = $this->bheService->generar($data);

            if ($resultado['success']) {
                return [
                    'success' => true,
                    'message' => 'BHE generada exitosamente',
                    'data' => $resultado['data']
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $resultado['error'],
                    'errores' => $resultado['errores'] ?? []
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error interno: ' . $e->getMessage()
            ];
        }
    }

    public function obtener(int $dteId): array
    {
        try {
            $bhe = $this->bheService->obtenerPorId($dteId);

            if (!$bhe) {
                return [
                    'success' => false,
                    'error' => 'BHE no encontrada'
                ];
            }

            return [
                'success' => true,
                'data' => $bhe
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener BHE: ' . $e->getMessage()
            ];
        }
    }

    public function listarPorProfesional(string $rutProfesional): array
    {
        try {
            // Validar parámetros de consulta
            $limite = (int) ($_GET['limite'] ?? 50);
            $offset = (int) ($_GET['offset'] ?? 0);

            if ($limite > 200) {
                $limite = 200; // Máximo 200 registros por consulta
            }

            $bheList = $this->bheService->listarPorProfesional($rutProfesional, $limite, $offset);

            return [
                'success' => true,
                'data' => [
                    'rut_profesional' => $rutProfesional,
                    'limite' => $limite,
                    'offset' => $offset,
                    'total_registros' => count($bheList),
                    'bhe' => $bheList
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al listar BHE: ' . $e->getMessage()
            ];
        }
    }

    public function generarReportePeriodo(): array
    {
        try {
            $rutProfesional = $_GET['rut_profesional'] ?? '';
            $fechaDesde = $_GET['fecha_desde'] ?? '';
            $fechaHasta = $_GET['fecha_hasta'] ?? '';

            if (empty($rutProfesional) || empty($fechaDesde) || empty($fechaHasta)) {
                return [
                    'success' => false,
                    'error' => 'Parámetros requeridos: rut_profesional, fecha_desde, fecha_hasta'
                ];
            }

            $reporte = $this->bheService->generarReportePeriodo($rutProfesional, $fechaDesde, $fechaHasta);

            return $reporte;

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al generar reporte: ' . $e->getMessage()
            ];
        }
    }

    public function generarPDF(int $dteId): array
    {
        try {
            $formato = $_GET['formato'] ?? 'carta';

            // Validar formato
            if (!$this->pdfGenerator->validarFormatoBHE($formato)) {
                return [
                    'success' => false,
                    'error' => 'Formato no válido. Formatos disponibles: carta, 80mm'
                ];
            }

            // Obtener datos de la BHE
            $bheData = $this->bheService->obtenerPorId($dteId);
            if (!$bheData) {
                return [
                    'success' => false,
                    'error' => 'BHE no encontrada'
                ];
            }

            // Generar PDF
            $resultado = $this->pdfGenerator->generar($bheData, $formato);

            if ($resultado['success']) {
                return [
                    'success' => true,
                    'message' => 'PDF BHE generado exitosamente',
                    'data' => $resultado
                ];
            } else {
                return [
                    'success' => false,
                    'error' => $resultado['error']
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al generar PDF: ' . $e->getMessage()
            ];
        }
    }

    public function registrarProfesional(): array
    {
        try {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data) {
                return [
                    'success' => false,
                    'error' => 'Datos JSON inválidos'
                ];
            }

            // Validar datos
            $validacion = $this->profesionalesModel->validarDatos($data);
            if (!$validacion['valido']) {
                return [
                    'success' => false,
                    'error' => 'Datos inválidos',
                    'errores' => $validacion['errores']
                ];
            }

            // Verificar que el RUT sea único
            if (!$this->profesionalesModel->verificarRutUnico($data['rut_profesional'])) {
                return [
                    'success' => false,
                    'error' => 'Ya existe un profesional registrado con este RUT'
                ];
            }

            $profesionalId = $this->profesionalesModel->crear($data);

            return [
                'success' => true,
                'message' => 'Profesional registrado exitosamente',
                'data' => [
                    'id' => $profesionalId,
                    'rut_profesional' => $data['rut_profesional']
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al registrar profesional: ' . $e->getMessage()
            ];
        }
    }

    public function obtenerProfesional(string $rutProfesional): array
    {
        try {
            $profesional = $this->profesionalesModel->obtenerPorRut($rutProfesional);

            if (!$profesional) {
                return [
                    'success' => false,
                    'error' => 'Profesional no encontrado'
                ];
            }

            return [
                'success' => true,
                'data' => $profesional
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener profesional: ' . $e->getMessage()
            ];
        }
    }

    public function actualizarProfesional(int $profesionalId): array
    {
        try {
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            if (!$data) {
                return [
                    'success' => false,
                    'error' => 'Datos JSON inválidos'
                ];
            }

            // Validar datos (excluir campos no actualizables)
            $datosActualizables = array_intersect_key($data, array_flip([
                'nombres', 'apellido_paterno', 'apellido_materno', 'fecha_nacimiento',
                'profesion', 'titulo_profesional', 'universidad',
                'telefono', 'email', 'direccion', 'comuna', 'codigo_comuna', 'region',
                'porcentaje_retencion_default'
            ]));

            if (empty($datosActualizables)) {
                return [
                    'success' => false,
                    'error' => 'No hay campos válidos para actualizar'
                ];
            }

            $actualizado = $this->profesionalesModel->actualizar($profesionalId, $datosActualizables);

            if ($actualizado) {
                return [
                    'success' => true,
                    'message' => 'Profesional actualizado exitosamente'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'No se pudo actualizar el profesional'
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al actualizar profesional: ' . $e->getMessage()
            ];
        }
    }

    public function listarProfesionales(): array
    {
        try {
            $limite = (int) ($_GET['limite'] ?? 50);
            $offset = (int) ($_GET['offset'] ?? 0);

            if ($limite > 200) {
                $limite = 200;
            }

            $profesionales = $this->profesionalesModel->listar($limite, $offset);

            return [
                'success' => true,
                'data' => [
                    'limite' => $limite,
                    'offset' => $offset,
                    'total_registros' => count($profesionales),
                    'profesionales' => $profesionales
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al listar profesionales: ' . $e->getMessage()
            ];
        }
    }

    public function buscarProfesionales(): array
    {
        try {
            $termino = $_GET['q'] ?? '';
            
            if (strlen($termino) < 3) {
                return [
                    'success' => false,
                    'error' => 'El término de búsqueda debe tener al menos 3 caracteres'
                ];
            }

            $limite = (int) ($_GET['limite'] ?? 20);
            $resultados = $this->profesionalesModel->buscar($termino, $limite);

            return [
                'success' => true,
                'data' => [
                    'termino' => $termino,
                    'total_encontrados' => count($resultados),
                    'profesionales' => $resultados
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error en búsqueda: ' . $e->getMessage()
            ];
        }
    }

    public function obtenerComunas(): array
    {
        try {
            $region = $_GET['region'] ?? null;
            $comunas = $this->profesionalesModel->obtenerComunas($region);

            return [
                'success' => true,
                'data' => [
                    'region_filtro' => $region,
                    'total_comunas' => count($comunas),
                    'comunas' => $comunas
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener comunas: ' . $e->getMessage()
            ];
        }
    }

    public function obtenerFormatosPDF(): array
    {
        try {
            $formatos = $this->pdfGenerator->obtenerTamañosDisponibles();

            return [
                'success' => true,
                'data' => [
                    'formatos_disponibles' => $formatos,
                    'tipo_documento' => 'Boleta de Honorarios Electrónica (BHE)',
                    'tipo_dte' => 41
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener formatos: ' . $e->getMessage()
            ];
        }
    }

    public function obtenerEstadisticas(): array
    {
        try {
            $estadisticas = [
                'profesionales' => $this->profesionalesModel->obtenerEstadisticas(),
                'bhe_info' => [
                    'tipo_dte' => 41,
                    'nombre' => 'Boleta de Honorarios Electrónica',
                    'retencion_default' => '10%',
                    'categoria_impuesto' => 'Segunda Categoría'
                ]
            ];

            return [
                'success' => true,
                'data' => $estadisticas
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al obtener estadísticas: ' . $e->getMessage()
            ];
        }
    }
}
