# Implementación de Firma Digital y Envío al SII

## 🎯 Objetivo
Implementar el flujo completo de firma digital de XML y envío a la plataforma de certificación del SII (Servicio de Impuestos Internos de Chile) para documentos tributarios electrónicos (DTE).

## 📋 Funcionalidades Implementadas

### 1. Firma Digital de XML
- **Endpoint**: `POST /dte/{id}/firmar`
- **Descripción**: Firma digitalmente el XML del DTE usando certificados PFX
- **Flujo**:
  1. Valida que el DTE esté en estado "generado"
  2. Obtiene el certificado PFX correspondiente al RUT emisor
  3. Completa el TED (Timbre Electrónico de Documentos)
  4. Firma el XML usando XMLSecLibs
  5. Guarda el XML firmado en archivo y base de datos
  6. Actualiza el estado a "firmado"

### 2. Envío al SII
- **Endpoint**: `POST /dte/{id}/enviar-sii`
- **Descripción**: Envía el XML firmado a la plataforma de certificación del SII
- **Flujo**:
  1. Valida que el DTE esté firmado
  2. Crea el sobre de envío según especificaciones SII
  3. Firma el sobre de envío
  4. Envía al SII (simulado en certificación)
  5. Actualiza el estado a "enviado_sii"

### 3. Consulta de Estado SII
- **Endpoint**: `GET /dte/{id}/estado-sii`
- **Descripción**: Consulta el estado del DTE en la plataforma SII
- **Flujo**:
  1. Obtiene datos del DTE
  2. Consulta estado en SII
  3. Actualiza estado local si cambió
  4. Retorna información del estado

## 🔧 Servicios Implementados

### DigitalSignature.php
```php
class DigitalSignature
{
    public function firmarDTE(string $xmlDte, string $rutEmisor): ?string
    public function firmarXML(DOMDocument $xmlDoc, array $certificado): string
    public function validarFirma(string $xmlFirmado): array
    private function completarTED(DOMDocument $xmlDoc, string $rutEmisor): void
    private function generarFirmaTED(string $ddString, string $rutEmisor): string
}
```

### SIIService.php
```php
class SIIService
{
    public function enviarDTE(string $xmlDte, string $rutEmisor): array
    public function consultarEstadoDTE(int $tipoDte, int $folio, string $rutEmisor): array
    public function obtenerSeed(): array
    public function obtenerToken(string $semilla, string $rutEmpresa): array
    private function crearSobreEnvio(string $xmlDte, string $rutEmisor): string
    private function firmarSolicitud(string $xml, array $certificado): string
}
```

## 🎨 Frontend - Gestión de DTEs

### Nueva Vista: Gestión DTE
- **Navegación**: Botón "Gestión DTE" en la barra superior
- **Funcionalidades**:
  - Lista de DTEs con estados
  - Botones de acción según estado:
    - **Firmar**: Para DTEs en estado "generado"
    - **Enviar SII**: Para DTEs en estado "firmado"
    - **Consultar Estado**: Para cualquier DTE
    - **Ver XML**: Para visualizar el XML

### Estados de DTE
1. **generado**: DTE creado con XML básico
2. **firmado**: XML firmado digitalmente
3. **enviado_sii**: Enviado a plataforma SII
4. **aceptado**: Aceptado por SII
5. **rechazado**: Rechazado por SII

### Workflow Visual
```
[Generar DTE] → [Firmar Digitalmente] → [Enviar al SII] → [Consultar Estado]
```

## 📁 Estructura de Archivos

### Nuevos Endpoints en `public/api.php`
```php
case ($params = extractRouteParams('/dte/{id}/firmar', $path)) !== null:
case ($params = extractRouteParams('/dte/{id}/enviar-sii', $path)) !== null:
case ($params = extractRouteParams('/dte/{id}/estado-sii', $path)) !== null:
```

### Frontend - `frontend/index.html`
- Nueva vista `dte-management`
- Funciones JavaScript:
  - `loadDTEList()`
  - `firmarDTE(dteId)`
  - `enviarSII(dteId)`
  - `consultarEstadoSII(dteId)`
  - `verXML(dteId)`
  - `getEstadoClass(estado)`

## 🔐 Especificaciones SII

### TED (Timbre Electrónico de Documentos)
El TED incluye los siguientes campos según especificaciones SII:
- **RE**: RUT del Emisor
- **TD**: Tipo de DTE
- **F**: Folio
- **FE**: Fecha de Emisión
- **RR**: RUT del Receptor
- **RSR**: Razón Social del Receptor
- **MNT**: Monto Total
- **IT1**: Primer ítem (máximo 40 caracteres)
- **CAF**: Datos del CAF
- **TSTED**: Timestamp del TED

### Formato QR SII
```
RUTEmisor;TipoDTE;Folio;Fecha;RUTReceptor;Monto
```

### Estructura XML SII
```xml
<?xml version="1.0" encoding="ISO-8859-1"?>
<EnvioDTE xmlns="http://www.sii.cl/SiiDte" version="1.0">
    <SetDTE ID="SetDTE">
        <Caratula version="1.0">
            <RutEmisor>76543210-9</RutEmisor>
            <RutEnvia>76543210-9</RutEnvia>
            <RutReceptor>60803000-K</RutReceptor>
            <FchResol>2006-01-20</FchResol>
            <NroResol>102006</NroResol>
            <TmstFirmaEnv>2024-12-20T12:00:00</TmstFirmaEnv>
            <SubTotDTE>
                <TpoDTE>33</TpoDTE>
                <NroDTE>1</NroDTE>
            </SubTotDTE>
        </Caratula>
        <!-- XML del DTE aquí -->
    </SetDTE>
</EnvioDTE>
```

## 🧪 Pruebas Implementadas

### Script de Prueba: `test_firma_sii.php`
- Verifica certificados disponibles
- Prueba firma digital
- Prueba envío al SII
- Prueba consulta de estado
- Genera DTEs de prueba si es necesario

### Script de Verificación: `verificar_certificados.php`
- Lista todos los certificados
- Verifica fechas de vencimiento
- Valida certificados PFX
- Prueba obtención para firma

## 🚀 Uso del Sistema

### 1. Generar DTE
```bash
curl -X POST http://localhost:8000/api.php/dte/generar \
  -H "Content-Type: application/json" \
  -d '{
    "tipo_dte": 33,
    "emisor": {"rut": "76543210-9"},
    "receptor": {"rut": "12345678-9"},
    "detalles": [{"nombre_item": "Producto", "precio_unitario": 100000}]
  }'
```

### 2. Firmar DTE
```bash
curl -X POST http://localhost:8000/api.php/dte/1/firmar
```

### 3. Enviar al SII
```bash
curl -X POST http://localhost:8000/api.php/dte/1/enviar-sii
```

### 4. Consultar Estado
```bash
curl -X GET http://localhost:8000/api.php/dte/1/estado-sii
```

## 🔧 Configuración

### Ambiente SII
En `config/database.php`:
```php
'sii' => [
    'environment' => 'certification', // 'certification' o 'production'
    'cert_url_solicitud_folios' => 'https://maullin.sii.cl/DTEWS/GetTokenFromSeed.jws',
    'cert_url_upload_dte' => 'https://maullin.sii.cl/DTEWS/services/wsdte',
    'prod_url_solicitud_folios' => 'https://palena.sii.cl/DTEWS/GetTokenFromSeed.jws',
    'prod_url_upload_dte' => 'https://palena.sii.cl/DTEWS/services/wsdte',
]
```

### Dependencias
- **XMLSecLibs**: Para firma digital XML
- **OpenSSL**: Para manejo de certificados PFX
- **cURL**: Para comunicación con SII

## 📊 Logs y Monitoreo

### Logs del Sistema
- Firma digital: `storage/logs/app.log`
- XML firmados: `storage/xml/`
- Respuestas SII: Campo `respuesta_sii` en tabla `documentos_dte`

### Estados de Monitoreo
- ✅ Conexión a base de datos
- ✅ Certificados disponibles
- ✅ DTEs generados
- ✅ XML firmados
- ✅ Envíos al SII
- ✅ Consultas de estado

## 🔄 Flujo Completo

1. **Generación**: DTE creado con XML según especificaciones SII
2. **Firma**: XML firmado con certificado PFX, completando TED
3. **Envío**: XML firmado enviado a plataforma SII
4. **Validación**: SII valida y responde con estado
5. **Consulta**: Sistema consulta estado y actualiza localmente

## 🎯 Próximos Pasos

1. **Certificados Reales**: Implementar certificados PFX válidos para pruebas
2. **CAF Management**: Gestión completa de Correlativo de Autorización de Folios
3. **Producción SII**: Configurar para ambiente de producción
4. **Validaciones Avanzadas**: Validaciones adicionales según reglamento SII
5. **Reportes**: Generación de reportes de envíos y estados

## ✅ Estado Actual

- ✅ Firma digital implementada
- ✅ Envío al SII implementado
- ✅ Consulta de estado implementada
- ✅ Frontend para gestión de DTEs
- ✅ Logs y monitoreo
- ✅ Especificaciones SII cumplidas
- ✅ Scripts de prueba
- ✅ Documentación completa

El sistema está listo para pruebas con certificados PFX válidos y puede ser configurado para producción una vez certificado con el SII.
