# DonFactura - API para Documentos Tributarios Electrónicos Chile

API REST desarrollada en PHP 8.x para generar Documentos Tributarios Electrónicos (DTE) según las especificaciones del Servicio de Impuestos Internos (SII) de Chile.

## Características

- ✅ Generación de todos los tipos de DTE chilenos (33, 34, 39, 45, 56, 61)
- ✅ Firma digital automática usando certificados PFX
- ✅ Gestión de folios CAF
- ✅ Módulo específico para boletas electrónicas
- ✅ API REST completa con validaciones
- ✅ Base de datos MariaDB optimizada
- ✅ Logging completo de operaciones

## Tipos de DTE Soportados

| Código | Tipo de Documento |
|--------|-------------------|
| 33 | Factura Electrónica |
| 34 | Factura Electrónica Exenta |
| 39 | Boleta Electrónica |
| 45 | Factura de Compra Electrónica |
| 56 | Nota de Débito Electrónica |
| 61 | Nota de Crédito Electrónica |

## Requisitos del Sistema

- PHP 8.0 o superior
- MariaDB 10.4 o superior
- Extensiones PHP: PDO, OpenSSL, cURL, SimpleXML
- Composer para dependencias
- XAMPP (para desarrollo)

## Instalación

### 1. Clonar el repositorio
```bash
git clone https://github.com/tu-usuario/donfactura.git
cd donfactura
```

### 2. Instalar dependencias
```bash
composer install
```

### 3. Configurar base de datos
1. Iniciar XAMPP con MariaDB
2. Ejecutar el script SQL:
```bash
mysql -u root -p123123 < database/create_database.sql
```

### 4. Configurar certificados
Crear el directorio para certificados:
```bash
mkdir -p storage/certificates
mkdir -p storage/temp
mkdir -p storage/generated
mkdir -p storage/logs
```

### 5. Iniciar servidor
```bash
cd public
php -S localhost:8000
```

La API estará disponible en: `http://localhost:8000`

## Configuración

### Base de Datos
Editar `config/database.php` si necesitas cambiar las credenciales:

```php
'database' => [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'dte_sistema',
    'username' => 'root',
    'password' => '123123',
    // ...
]
```

### Ambiente SII
Por defecto está configurado para certificación. Para producción:

```php
'sii' => [
    'environment' => 'production', // cambiar de 'certification'
    // ...
]
```

## Uso de la API

### Endpoints Principales

#### 1. Subir Certificado Digital
```http
POST /api/certificados/upload
Content-Type: multipart/form-data

- certificado: archivo .pfx
- password: contraseña del certificado
- nombre: nombre descriptivo
- rut_empresa: RUT de la empresa
- razon_social: razón social
```

#### 2. Generar DTE
```http
POST /api/dte/generar
Content-Type: application/json

{
    "tipo_dte": 33,
    "fecha_emision": "2024-01-15",
    "emisor": {
        "rut": "76543210-9",
        "razon_social": "EMPRESA EMISORA LTDA",
        "giro": "SERVICIOS DE CONSULTORÍA",
        "direccion": "AV. PROVIDENCIA 123",
        "comuna": "PROVIDENCIA",
        "ciudad": "SANTIAGO"
    },
    "receptor": {
        "rut": "12345678-9",
        "razon_social": "CLIENTE RECEPTOR S.A.",
        "giro": "COMERCIO AL POR MENOR",
        "direccion": "CALLE FALSA 456",
        "comuna": "LAS CONDES",
        "ciudad": "SANTIAGO"
    },
    "detalles": [
        {
            "nombre_item": "Consultoría técnica",
            "descripcion": "Servicios de consultoría especializada",
            "cantidad": 1,
            "precio_unitario": 100000,
            "unidad_medida": "UN"
        }
    ],
    "observaciones": "Factura por servicios prestados"
}
```

#### 3. Generar Boleta Electrónica
```http
POST /api/boletas/generar
Content-Type: application/json

{
    "emisor": {
        "rut": "76543210-9",
        "razon_social": "TIENDA RETAIL LTDA"
    },
    "receptor": {
        "rut": "66666666-6",
        "razon_social": "CONSUMIDOR FINAL"
    },
    "detalles": [
        {
            "nombre_item": "Producto A",
            "cantidad": 2,
            "precio_unitario": 5000
        }
    ],
    "boleta": {
        "forma_pago": "efectivo",
        "numero_caja": "001",
        "cajero": "JUAN PEREZ"
    }
}
```

#### 4. Solicitar Folios CAF
```http
POST /api/folios/solicitar
Content-Type: application/json

{
    "tipo_dte": 33,
    "rut_empresa": "76543210-9",
    "cantidad": 100
}
```

#### 5. Cargar CAF desde SII
```http
POST /api/folios/cargar-caf
Content-Type: application/json

{
    "xml_caf": "<AUTORIZACION version=\"1.0\">...</AUTORIZACION>"
}
```

### Respuestas

#### Respuesta Exitosa
```json
{
    "success": true,
    "data": {
        "id": 123,
        "tipo_dte": 33,
        "folio": 1001,
        "estado": "generado",
        "xml": "<?xml version=\"1.0\"?>..."
    }
}
```

#### Respuesta de Error
```json
{
    "success": false,
    "errors": [
        "RUT del emisor es requerido",
        "Debe incluir al menos un detalle"
    ]
}
```

## Flujo de Trabajo

### 1. Configuración Inicial
1. Subir certificado digital PFX
2. Solicitar folios CAF al SII
3. Cargar archivo CAF recibido

### 2. Generación de DTE
1. Crear DTE mediante API
2. Firmar documento automáticamente
3. Enviar al SII (opcional)
4. Consultar estado en SII

### 3. Para Boletas Electrónicas
1. Generar boletas durante el día
2. Envío masivo al SII
3. Generar reporte diario

## Estructura del Proyecto

```
donfactura/
├── config/
│   └── database.php          # Configuración BD
├── database/
│   └── create_database.sql   # Script creación BD
├── public/
│   └── index.php            # Punto de entrada
├── src/
│   ├── Controllers/         # Controladores REST
│   ├── Core/               # Clases principales
│   ├── Middleware/         # Middleware HTTP
│   ├── Models/            # Modelos de datos
│   └── Services/          # Servicios de negocio
├── storage/               # Archivos y logs
└── composer.json         # Dependencias
```

## Logging

Los logs se almacenan en `storage/logs/app.log` e incluyen:
- Requests HTTP recibidos
- DTE generados y firmados
- Errores y excepciones
- Transacciones con SII

## Seguridad

- ✅ Validación de entrada en todos los endpoints
- ✅ Almacenamiento seguro de certificados
- ✅ Transacciones de base de datos
- ✅ Logging completo de operaciones
- ✅ Manejo de errores sin exposición de datos sensibles

## Certificación SII

Para usar en producción:

1. Completar proceso de certificación con SII
2. Obtener certificado digital válido
3. Cambiar configuración a ambiente producción
4. Realizar pruebas exhaustivas

## Soporte

Para soporte técnico o consultas:
- Email: dev@donfactura.cl
- Documentación SII: https://www.sii.cl

## Licencia

Este proyecto está bajo licencia MIT. Ver archivo LICENSE para detalles.

---

**⚠️ Importante:** Este sistema debe ser certificado por el SII antes de usar en producción. Asegúrate de cumplir con todos los requisitos legales y técnicos vigentes.
