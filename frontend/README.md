# Frontend DonFactura

Frontend completo para testing y demostración de la API de Facturación Electrónica DonFactura.

## 🚀 Características

### ✅ Funcionalidades Implementadas

- **Dashboard Interactivo**: Estadísticas en tiempo real y documentos recientes
- **Generación DTE**: Soporte completo para todos los tipos de DTE chilenos
- **BHE (Boletas de Honorarios)**: Módulo especializado para profesionales independientes
- **PDF con QR**: Generación de PDFs en formato carta y 80mm con códigos QR
- **Gestión de Certificados**: Upload y validación de certificados digitales PFX
- **Testing SII**: Conexión y pruebas con plataforma de certificación del SII
- **Validaciones Avanzadas**: RUT chileno, emails, montos, etc.

### 📋 Tipos de DTE Soportados

| Código | Tipo de Documento |
|--------|------------------|
| 33 | Factura Electrónica |
| 34 | Factura Electrónica Exenta |
| 39 | Boleta Electrónica |
| 41 | Boleta de Honorarios Electrónica (BHE) |
| 45 | Factura de Compra Electrónica |
| 56 | Nota de Débito Electrónica |
| 61 | Nota de Crédito Electrónica |

## 🛠️ Instalación y Uso

### Opción 1: Servidor Python (Recomendado)

```bash
cd frontend
python -m http.server 3000
```

Acceder a: `http://localhost:3000`

### Opción 2: Cualquier servidor web

Puedes servir los archivos con cualquier servidor web estático:

- Apache
- Nginx
- Node.js (http-server)
- PHP built-in server

### Opción 3: Abrir directamente

Solo para pruebas locales (algunas funciones pueden no funcionar por CORS):

```bash
open index.html
```

## 📁 Estructura de Archivos

```
frontend/
├── index.html          # Aplicación principal
├── demo.html          # Demo y testing avanzado
├── config.js          # Configuración centralizada
└── README.md          # Este archivo
```

## 🔧 Configuración

### API Backend

Editar en `config.js`:

```javascript
const CONFIG = {
    API_BASE_URL: 'http://localhost:8000', // URL de tu API DonFactura
    // ... otras configuraciones
};
```

### Personalización

El archivo `config.js` incluye:

- **Tipos de DTE**: Configuración de documentos disponibles
- **Validaciones**: Patrones para RUT, email, teléfono
- **Formatos**: PDF, unidades de medida, comunas
- **Mensajes**: Textos del sistema personalizables
- **Utilidades**: Funciones de formato y validación

## 📺 Interfaces Disponibles

### 1. Aplicación Principal (`index.html`)

**Funcionalidades:**
- Dashboard con estadísticas
- Formularios de generación DTE y BHE
- Gestión de certificados digitales
- Generación de PDFs con QR
- Interfaz de usuario optimizada

**Ideal para:** Uso diario, demostración a clientes, operación normal

### 2. Demo y Testing (`demo.html`)

**Funcionalidades:**
- Tests automatizados de endpoints
- Generación de datos de prueba
- Validación de certificados
- Tests de conexión SII
- Análisis detallado de respuestas

**Ideal para:** Desarrollo, testing, debugging, validación técnica

## 🎯 Casos de Uso

### Para Desarrolladores

1. **Testing de API**: Usar `demo.html` para validar endpoints
2. **Debugging**: Revisar respuestas detalladas y logs
3. **Integración**: Ejemplo de cómo consumir la API DonFactura

### Para Empresas

1. **Demostración**: Mostrar capacidades del sistema
2. **Capacitación**: Entrenar usuarios en generación de DTE
3. **Pruebas**: Validar documentos antes de producción

### Para Profesionales (BHE)

1. **Registro**: Registrar profesionales independientes
2. **Generación BHE**: Crear boletas de honorarios
3. **PDF Térmico**: Generar comprobantes para impresoras

## 🔍 Testing y Validación

### Tests Automáticos Disponibles

- **Endpoints API**: Verificación de disponibilidad
- **Generación DTE**: Tests con datos predefinidos
- **BHE Workflow**: Flujo completo de honorarios
- **PDF Generation**: Creación de documentos
- **SII Integration**: Conexión con plataforma oficial

### Datos de Prueba

El sistema incluye datos de ejemplo para:
- RUTs válidos de testing
- Productos y servicios estándar
- Montos y períodos típicos
- Certificados de prueba

## 🚨 Consideraciones Importantes

### Ambiente de Desarrollo

- Configurado para ambiente de **CERTIFICACIÓN** del SII
- No usar datos reales de producción
- Certificados digitales solo para testing

### Seguridad

- Validación de RUT chileno implementada
- Sanitización de datos de entrada
- Manejo seguro de certificados PFX

### Performance

- Carga lazy de módulos pesados
- Validación client-side para UX rápida
- Caché inteligente de datos frecuentes

## 📊 Monitoreo y Logs

### Logs del Frontend

- Errores de API en consola del navegador
- Estados de conexión SII
- Validaciones fallidas
- Operaciones exitosas

### Integración con Backend

- Logs automáticos en `storage/logs/app.log`
- Tracking de requests HTTP
- Métricas de performance

## 🔗 Integración con Sistemas Externos

### SII (Servicio de Impuestos Internos)

- Conexión directa a plataforma de certificación
- Envío automático de DTE
- Consulta de estados
- Descarga de folios CAF

### Sistemas Contables

- Export de DTE en formato estándar
- Integración vía API REST
- Webhooks para notificaciones

## 🛡️ Cumplimiento y Certificación

### Normativa SII

- **Resolución SII N° 80/2014**: DTE estándar ✅
- **Resolución SII N° 40/2014**: BHE específica ✅
- **Códigos QR**: Según especificaciones oficiales ✅
- **Firma Digital**: Obligatoria y validada ✅

### Validaciones Implementadas

- Formato RUT chileno con dígito verificador
- Fechas coherentes y no futuras
- Montos positivos y realistas
- Períodos de servicios válidos (BHE)
- Estructura XML según esquemas SII

## 📈 Roadmap

### Próximas Funcionalidades

- [ ] Dashboard con gráficos avanzados
- [ ] Export masivo de documentos
- [ ] Templates personalizables
- [ ] Integración con sistemas ERP
- [ ] App móvil complementaria
- [ ] API webhooks para eventos

### Mejoras Técnicas

- [ ] PWA (Progressive Web App)
- [ ] Modo offline para consultas
- [ ] Notificaciones push
- [ ] Optimización para tablet/móvil
- [ ] Tests automatizados E2E

## 🆘 Soporte y Contribución

### Reportar Issues

1. Describir el problema claramente
2. Incluir pasos para reproducir
3. Adjuntar logs relevantes
4. Especificar navegador y versión

### Contribuir

1. Fork del repositorio
2. Crear branch para feature
3. Seguir estándares de código
4. Incluir tests cuando aplique
5. Documentar cambios importantes

---

## 📞 Contacto

- **Email**: dev@donfactura.cl
- **Documentación SII**: https://www.sii.cl/factura_electronica/
- **GitHub**: https://github.com/donfactura/api

---

**⚠️ Importante**: Este frontend está diseñado para trabajar con el ambiente de certificación del SII. Antes de usar en producción, asegúrate de:

1. Completar proceso de certificación SII
2. Configurar certificados de producción
3. Cambiar URLs a ambiente productivo
4. Realizar pruebas exhaustivas con datos reales

**✅ Listo para usar**: El sistema está completamente funcional para testing y desarrollo. ¡Comienza a generar tus primeros DTE!
