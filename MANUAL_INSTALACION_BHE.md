# 📋 MANUAL DE INSTALACIÓN - SISTEMA BHE (Boletas de Honorarios Electrónicas)

## 🎯 Documentos Tributarios Electrónicos Chile - DTE Tipo 41

Este manual cubre la instalación completa del sistema en dos entornos:
- **🐧 Servidor Linux con Apache**
- **🏠 XAMPP (Windows/Mac/Linux)**

---

## 📋 REQUISITOS DEL SISTEMA

### **Requisitos Mínimos**
- **PHP**: 8.0 o superior ⚡
- **Base de Datos**: MySQL 5.7+ / MariaDB 10.3+
- **Servidor Web**: Apache 2.4+ / Nginx 1.18+
- **Memoria**: 512MB RAM mínimo
- **Disco**: 100MB espacio libre

### **Extensiones PHP Requeridas**
```bash
✅ php-pdo
✅ php-pdo-mysql
✅ php-openssl
✅ php-curl
✅ php-simplexml
✅ php-json
✅ php-mbstring
✅ php-gd (para códigos QR)
```

---

# 🐧 INSTALACIÓN EN SERVIDOR LINUX APACHE

## **Paso 1: Preparar el Servidor**

### **Ubuntu/Debian**
```bash
# Actualizar sistema
sudo apt update && sudo apt upgrade -y

# Instalar Apache
sudo apt install apache2 -y

# Instalar PHP 8.x y extensiones
sudo apt install php8.1 php8.1-common php8.1-mysql php8.1-xml php8.1-curl php8.1-gd php8.1-mbstring php8.1-json php8.1-zip -y

# Instalar MySQL/MariaDB
sudo apt install mariadb-server -y

# Habilitar servicios
sudo systemctl enable apache2
sudo systemctl enable mariadb
sudo systemctl start apache2
sudo systemctl start mariadb
```

### **CentOS/RHEL/Rocky Linux**
```bash
# Actualizar sistema
sudo dnf update -y

# Instalar Apache
sudo dnf install httpd -y

# Instalar repositorio PHP 8.x
sudo dnf install epel-release -y
sudo dnf module reset php
sudo dnf module enable php:8.1 -y

# Instalar PHP y extensiones
sudo dnf install php php-common php-pdo php-mysqlnd php-xml php-curl php-gd php-mbstring php-json -y

# Instalar MariaDB
sudo dnf install mariadb-server -y

# Habilitar servicios
sudo systemctl enable httpd
sudo systemctl enable mariadb
sudo systemctl start httpd
sudo systemctl start mariadb
```

## **Paso 2: Configurar Base de Datos**

```bash
# Asegurar instalación MySQL/MariaDB
sudo mysql_secure_installation

# Crear base de datos y usuario
sudo mysql -u root -p
```

```sql
-- En el prompt de MySQL/MariaDB:
CREATE DATABASE dte_sistema CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'dte_user'@'localhost' IDENTIFIED BY 'tu_password_seguro';
GRANT ALL PRIVILEGES ON dte_sistema.* TO 'dte_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## **Paso 3: Instalar el Sistema BHE**

```bash
# Ir al directorio web
cd /var/www/html

# Crear directorio del proyecto
sudo mkdir donfactura
sudo chown $USER:$USER donfactura
cd donfactura

# Descargar/copiar archivos del sistema BHE
# (Aquí irían los comandos para obtener el código fuente)
```

### **Estructura de Directorios**
```bash
# Crear estructura necesaria
mkdir -p storage/{certificates,generated,temp,logs}
mkdir -p vendor/psr/{http-message,log}
mkdir -p src/{Core,Models,Services,Controllers,Middleware,Utils}
mkdir -p public
mkdir -p config
mkdir -p examples
mkdir -p database

# Establecer permisos
sudo chown -R www-data:www-data storage/
sudo chmod -R 775 storage/
sudo chown -R www-data:www-data public/
```

## **Paso 4: Configurar Apache**

### **Crear VirtualHost**
```bash
sudo nano /etc/apache2/sites-available/donfactura.conf
```

```apache
<VirtualHost *:80>
    ServerName donfactura.local
    DocumentRoot /var/www/html/donfactura/public
    
    <Directory /var/www/html/donfactura/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Configuración específica para API
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index_basic.php [QSA,L]
    </Directory>
    
    # Logs
    ErrorLog ${APACHE_LOG_DIR}/donfactura_error.log
    CustomLog ${APACHE_LOG_DIR}/donfactura_access.log combined
    
    # Configuración PHP
    php_admin_value upload_max_filesize 10M
    php_admin_value post_max_size 10M
    php_admin_value memory_limit 256M
    php_admin_value max_execution_time 300
</VirtualHost>
```

### **Activar sitio y módulos**
```bash
# Habilitar mod_rewrite
sudo a2enmod rewrite

# Activar sitio
sudo a2ensite donfactura.conf

# Reiniciar Apache
sudo systemctl restart apache2

# Agregar al hosts (opcional para desarrollo)
echo "127.0.0.1 donfactura.local" | sudo tee -a /etc/hosts
```

## **Paso 5: Configurar el Sistema**

### **Configurar Base de Datos**
```bash
# Editar configuración
nano config/database.php
```

```php
<?php
return [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'dte_sistema',
        'username' => 'dte_user',
        'password' => 'tu_password_seguro',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],
    // ... resto de configuración
];
```

### **Ejecutar Scripts de Instalación**
```bash
# Configurar base de datos BHE
php setup_bhe.php

# Crear folios de ejemplo
php create_folios_bhe.php

# Crear certificados de ejemplo
php create_certificados_bhe.php

# Corregir tabla folios si es necesario
php fix_folios_table.php
```

## **Paso 6: Verificar Instalación**

```bash
# Probar sistema
php test_bhe_system.php

# Acceder vía web
curl http://donfactura.local/bhe-features
# o
curl http://localhost/donfactura/public/bhe-features
```

---

# 🏠 INSTALACIÓN EN XAMPP

## **Paso 1: Descargar e Instalar XAMPP**

### **Windows**
1. Descargar XAMPP desde: https://www.apachefriends.org/
2. Ejecutar instalador como administrador
3. Instalar en: `C:\xampp\`
4. Seleccionar componentes: **Apache**, **MySQL**, **PHP**

### **macOS**
```bash
# Usando Homebrew
brew install --cask xampp

# O descargar desde el sitio oficial
```

### **Linux**
```bash
# Descargar installer
wget https://downloadsapachefriends.global.ssl.fastly.net/8.1.6/xampp-linux-x64-8.1.6-0-installer.run

# Hacer ejecutable y instalar
chmod +x xampp-linux-x64-*.run
sudo ./xampp-linux-x64-*.run
```

## **Paso 2: Iniciar Servicios XAMPP**

### **Panel de Control (Windows/Mac)**
1. Abrir **XAMPP Control Panel**
2. Iniciar **Apache** ✅
3. Iniciar **MySQL** ✅
4. Verificar puertos:
   - Apache: 80, 443
   - MySQL: 3306

### **Linux**
```bash
sudo /opt/lampp/lampp start
```

## **Paso 3: Configurar Base de Datos**

### **Acceder a phpMyAdmin**
```
URL: http://localhost/phpmyadmin
Usuario: root
Contraseña: (vacía por defecto)
```

### **Crear Base de Datos**
1. En phpMyAdmin, crear nueva base de datos: `dte_sistema`
2. Cotejamiento: `utf8mb4_unicode_ci`
3. O ejecutar SQL:

```sql
CREATE DATABASE dte_sistema CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## **Paso 4: Instalar Sistema BHE**

### **Ubicación de Archivos**
```bash
# Windows
C:\xampp\htdocs\donfactura\

# macOS
/Applications/XAMPP/htdocs/donfactura/

# Linux
/opt/lampp/htdocs/donfactura/
```

### **Copiar Archivos del Sistema**
```bash
# Estructura completa
donfactura/
├── config/
│   └── database.php
├── src/
│   ├── Controllers/
│   ├── Models/
│   ├── Services/
│   └── Core/
├── public/
│   └── index_basic.php
├── storage/
│   ├── certificates/
│   ├── generated/
│   └── logs/
├── examples/
├── vendor/
└── setup_bhe.php
```

## **Paso 5: Configurar Credenciales**

### **Archivo config/database.php**
```php
<?php
return [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'dte_sistema',
        'username' => 'root',
        'password' => '123123', // o password de tu MySQL
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],
    // ... resto de configuración BHE
];
```

## **Paso 6: Ejecutar Instalación**

### **Via Línea de Comandos**
```bash
# Windows (desde C:\xampp\htdocs\donfactura)
C:\xampp\php\php.exe setup_bhe.php
C:\xampp\php\php.exe create_folios_bhe.php
C:\xampp\php\php.exe create_certificados_bhe.php

# macOS/Linux
php setup_bhe.php
php create_folios_bhe.php
php create_certificados_bhe.php
```

### **Via Navegador Web**
```
# Acceder directamente (menos recomendado)
http://localhost/donfactura/setup_bhe.php
```

## **Paso 7: Verificar Instalación XAMPP**

### **Pruebas Básicas**
```
# Verificar funcionalidades
http://localhost/donfactura/public/bhe-features

# Probar health check
http://localhost/donfactura/public/health

# Ver estructura base de datos
http://localhost/donfactura/public/estructura
```

### **Prueba Completa**
```bash
# Ejecutar test completo
php test_bhe_system.php
```

---

# 🔧 CONFIGURACIÓN AVANZADA

## **Configurar HTTPS (Producción)**

### **Apache Linux con Let's Encrypt**
```bash
# Instalar Certbot
sudo apt install certbot python3-certbot-apache

# Obtener certificado
sudo certbot --apache -d tu-dominio.com

# Auto-renovación
sudo crontab -e
# Agregar: 0 12 * * * /usr/bin/certbot renew --quiet
```

### **XAMPP con Certificado Auto-firmado**
```bash
# Generar certificado
openssl req -new -x509 -days 365 -nodes -out server.crt -keyout server.key

# Configurar en httpd-ssl.conf
SSLCertificateFile "path/to/server.crt"
SSLCertificateKeyFile "path/to/server.key"
```

## **Optimización PHP (php.ini)**

```ini
# Configuración recomendada para BHE
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
max_input_vars = 3000

# Extensiones requeridas
extension=pdo
extension=pdo_mysql
extension=openssl
extension=curl
extension=simplexml
extension=json
extension=mbstring
extension=gd
```

## **Configuración Apache (.htaccess)**

```apache
# public/.htaccess
RewriteEngine On

# Redirigir todo a index_basic.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index_basic.php [QSA,L]

# Headers de seguridad
Header always set X-Content-Type-Options "nosniff"
Header always set X-Frame-Options "DENY"
Header always set X-XSS-Protection "1; mode=block"

# CORS para API
Header always set Access-Control-Allow-Origin "*"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
Header always set Access-Control-Allow-Headers "Content-Type, Authorization"

# Cache para archivos estáticos
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType application/json "access plus 1 hour"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

---

# 🔍 SOLUCIÓN DE PROBLEMAS

## **Problemas Comunes**

### **Error: "No se puede conectar a la base de datos"**
```bash
# Verificar servicio MySQL
sudo systemctl status mysql     # Linux
# o verificar en XAMPP Control Panel

# Verificar credenciales en config/database.php
# Verificar que la base de datos existe
```

### **Error: "Class not found"**
```bash
# Verificar autoloader
ls vendor/autoload.php

# Verificar permisos
chmod +r vendor/autoload.php

# Verificar estructura de clases en src/
```

### **Error: "Permission denied" en storage/**
```bash
# Linux/Apache
sudo chown -R www-data:www-data storage/
sudo chmod -R 775 storage/

# XAMPP
chmod -R 777 storage/  # Solo desarrollo
```

### **Error: "Folios no disponibles"**
```bash
# Ejecutar script de folios
php create_folios_bhe.php

# Verificar tabla folios
php check_tables.php
```

## **Logs y Depuración**

### **Ubicación de Logs**
```bash
# Sistema BHE
storage/logs/app.log

# Apache Linux
/var/log/apache2/error.log
/var/log/apache2/access.log

# XAMPP
xampp/apache/logs/error.log
xampp/apache/logs/access.log

# PHP
php.ini → log_errors = On
php.ini → error_log = /path/to/php_errors.log
```

### **Habilitar Debug**
```php
// En config/database.php
'debug' => true,

// En PHP
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

---

# ✅ CHECKLIST DE INSTALACIÓN

## **Pre-instalación**
- [ ] Servidor con PHP 8.0+
- [ ] MySQL/MariaDB instalado
- [ ] Extensiones PHP requeridas
- [ ] Permisos de escritura en directorios

## **Durante la Instalación**
- [ ] Base de datos creada
- [ ] Archivos copiados correctamente
- [ ] Configuración database.php
- [ ] Scripts de setup ejecutados
- [ ] Permisos configurados

## **Post-instalación**
- [ ] Health check pasa ✅
- [ ] BHE se genera correctamente ✅
- [ ] PDF se crean sin errores ✅
- [ ] API endpoints responden ✅
- [ ] Logs sin errores críticos ✅

## **Verificación Final**
```bash
# Test completo del sistema
php test_bhe_system.php

# Verificar en navegador
http://tu-dominio.com/bhe-features
```

---

# 📞 SOPORTE Y MANTENIMIENTO

## **Comandos Útiles**

### **Backup Base de Datos**
```bash
# Backup completo
mysqldump -u dte_user -p dte_sistema > backup_dte_$(date +%Y%m%d).sql

# Restore
mysql -u dte_user -p dte_sistema < backup_dte_20241216.sql
```

### **Actualizar Sistema**
```bash
# Backup antes de actualizar
cp -r donfactura donfactura_backup_$(date +%Y%m%d)

# Copiar nuevos archivos
# Ejecutar scripts de migración si es necesario
```

### **Monitoreo**
```bash
# Ver logs en tiempo real
tail -f storage/logs/app.log

# Verificar espacio en disco
df -h

# Verificar procesos PHP
ps aux | grep php
```

## **Mantenimiento Rutinario**

### **Semanal**
- [ ] Verificar logs de errores
- [ ] Backup base de datos
- [ ] Verificar espacio en disco
- [ ] Test básico del sistema

### **Mensual**
- [ ] Actualizar sistema operativo
- [ ] Actualizar PHP si es necesario
- [ ] Revisar certificados SSL
- [ ] Limpieza de archivos temporales

### **Anual**
- [ ] Renovar certificados digitales
- [ ] Auditoría de seguridad
- [ ] Actualización mayor del sistema
- [ ] Revisión de folios CAF

---

# 📋 RESUMEN EJECUTIVO

## **✅ Instalación Completada**

Una vez seguidos estos pasos, tendrás un sistema completo de **Boletas de Honorarios Electrónicas (BHE)** funcionando en:

- **🐧 Servidor Linux Apache**: Para producción
- **🏠 XAMPP**: Para desarrollo local

## **🎯 Funcionalidades Disponibles**

- ✅ Generación BHE DTE Tipo 41
- ✅ Firma electrónica obligatoria
- ✅ PDF formatos carta y térmico
- ✅ API REST completa
- ✅ Gestión de profesionales
- ✅ Cumplimiento normativa SII

## **🚀 URLs de Acceso**

```bash
# Funcionalidades principales
http://tu-dominio.com/bhe-features

# API para generar BHE
POST http://tu-dominio.com/api/bhe/generar

# Gestión profesionales
GET http://tu-dominio.com/api/profesionales
```

---

*Manual de Instalación BHE - Versión 1.0*  
*Compatible con: PHP 8.x, Apache 2.4+, MySQL 5.7+*  
*Normativa: SII Chile - DTE Tipo 41*
