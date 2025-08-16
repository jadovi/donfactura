@echo off
REM Script de Instalación para XAMPP - Sistema BHE
REM Boletas de Honorarios Electrónicas - DTE Tipo 41

setlocal enabledelayedexpansion

echo =========================================
echo   INSTALADOR SISTEMA BHE PARA XAMPP
echo =========================================
echo.

REM Variables de configuración
set XAMPP_PATH=C:\xampp
set PROJECT_DIR=%XAMPP_PATH%\htdocs\donfactura
set DB_NAME=dte_sistema
set DB_USER=root
set DB_PASS=123123
set PHP_PATH=%XAMPP_PATH%\php\php.exe
set MYSQL_PATH=%XAMPP_PATH%\mysql\bin\mysql.exe

REM Función para mostrar mensajes
call :log_info "Iniciando instalación Sistema BHE en XAMPP..."

REM Verificar que XAMPP está instalado
if not exist "%XAMPP_PATH%" (
    call :log_error "XAMPP no encontrado en %XAMPP_PATH%"
    call :log_info "Por favor, instale XAMPP desde https://www.apachefriends.org/"
    pause
    exit /b 1
)

call :log_success "XAMPP encontrado en %XAMPP_PATH%"

REM Verificar que los servicios están corriendo
call :log_info "Verificando servicios XAMPP..."

tasklist /fi "imagename eq httpd.exe" | find "httpd.exe" >nul
if errorlevel 1 (
    call :log_warning "Apache no está corriendo. Iniciando..."
    "%XAMPP_PATH%\apache_start.bat"
    timeout /t 3 /nobreak >nul
)

tasklist /fi "imagename eq mysqld.exe" | find "mysqld.exe" >nul
if errorlevel 1 (
    call :log_warning "MySQL no está corriendo. Iniciando..."
    "%XAMPP_PATH%\mysql_start.bat"
    timeout /t 5 /nobreak >nul
)

call :log_success "Servicios XAMPP verificados"

REM Crear estructura de directorios
call :log_info "Creando estructura de directorios..."

if not exist "%PROJECT_DIR%" mkdir "%PROJECT_DIR%"
if not exist "%PROJECT_DIR%\config" mkdir "%PROJECT_DIR%\config"
if not exist "%PROJECT_DIR%\src" mkdir "%PROJECT_DIR%\src"
if not exist "%PROJECT_DIR%\src\Core" mkdir "%PROJECT_DIR%\src\Core"
if not exist "%PROJECT_DIR%\src\Models" mkdir "%PROJECT_DIR%\src\Models"
if not exist "%PROJECT_DIR%\src\Services" mkdir "%PROJECT_DIR%\src\Services"
if not exist "%PROJECT_DIR%\src\Controllers" mkdir "%PROJECT_DIR%\src\Controllers"
if not exist "%PROJECT_DIR%\src\Middleware" mkdir "%PROJECT_DIR%\src\Middleware"
if not exist "%PROJECT_DIR%\src\Utils" mkdir "%PROJECT_DIR%\src\Utils"
if not exist "%PROJECT_DIR%\public" mkdir "%PROJECT_DIR%\public"
if not exist "%PROJECT_DIR%\storage" mkdir "%PROJECT_DIR%\storage"
if not exist "%PROJECT_DIR%\storage\certificates" mkdir "%PROJECT_DIR%\storage\certificates"
if not exist "%PROJECT_DIR%\storage\generated" mkdir "%PROJECT_DIR%\storage\generated"
if not exist "%PROJECT_DIR%\storage\temp" mkdir "%PROJECT_DIR%\storage\temp"
if not exist "%PROJECT_DIR%\storage\logs" mkdir "%PROJECT_DIR%\storage\logs"
if not exist "%PROJECT_DIR%\vendor" mkdir "%PROJECT_DIR%\vendor"
if not exist "%PROJECT_DIR%\vendor\psr" mkdir "%PROJECT_DIR%\vendor\psr"
if not exist "%PROJECT_DIR%\vendor\psr\http-message" mkdir "%PROJECT_DIR%\vendor\psr\http-message"
if not exist "%PROJECT_DIR%\vendor\psr\log" mkdir "%PROJECT_DIR%\vendor\psr\log"
if not exist "%PROJECT_DIR%\examples" mkdir "%PROJECT_DIR%\examples"
if not exist "%PROJECT_DIR%\database" mkdir "%PROJECT_DIR%\database"

call :log_success "Estructura de directorios creada"

REM Crear archivo de configuración de base de datos
call :log_info "Creando configuración de base de datos..."

(
echo ^<?php
echo return [
echo     'database' =^> [
echo         'host' =^> 'localhost',
echo         'port' =^> 3306,
echo         'database' =^> '%DB_NAME%',
echo         'username' =^> '%DB_USER%',
echo         'password' =^> '%DB_PASS%',
echo         'charset' =^> 'utf8mb4',
echo         'collation' =^> 'utf8mb4_unicode_ci',
echo         'options' =^> [
echo             PDO::ATTR_ERRMODE =^> PDO::ERRMODE_EXCEPTION,
echo             PDO::ATTR_DEFAULT_FETCH_MODE =^> PDO::FETCH_ASSOC,
echo             PDO::ATTR_EMULATE_PREPARES =^> false,
echo         ]
echo     ],
echo     'sii' =^> [
echo         'cert_url_solicitud_folios' =^> 'https://maullin.sii.cl/DTEWS/GetTokenFromSeed.jws',
echo         'cert_url_upload_dte' =^> 'https://maullin.sii.cl/DTEWS/services/wsdte',
echo         'prod_url_solicitud_folios' =^> 'https://palena.sii.cl/DTEWS/GetTokenFromSeed.jws',
echo         'prod_url_upload_dte' =^> 'https://palena.sii.cl/DTEWS/services/wsdte',
echo         'environment' =^> 'certification',
echo     ],
echo     'paths' =^> [
echo         'certificates' =^> __DIR__ . '/../storage/certificates/',
echo         'xml_temp' =^> __DIR__ . '/../storage/temp/',
echo         'xml_generated' =^> __DIR__ . '/../storage/generated/',
echo         'logs' =^> __DIR__ . '/../storage/logs/',
echo     ],
echo     'dte_types' =^> [
echo         33 =^> 'Factura Electrónica',
echo         34 =^> 'Factura Electrónica Exenta',
echo         39 =^> 'Boleta Electrónica',
echo         41 =^> 'Boleta de Honorarios Electrónica ^(BHE^)',
echo         45 =^> 'Factura de Compra Electrónica',
echo         56 =^> 'Nota de Débito Electrónica',
echo         61 =^> 'Nota de Crédito Electrónica',
echo     ]
echo ];
) > "%PROJECT_DIR%\config\database.php"

call :log_success "Configuración de base de datos creada"

REM Crear autoloader básico
call :log_info "Creando autoloader..."

(
echo ^<?php
echo spl_autoload_register^(function ^($class^) {
echo     $prefix = 'DonFactura\\DTE\\';
echo     $base_dir = __DIR__ . '/../src/';
echo     
echo     $len = strlen^($prefix^);
echo     if ^(strncmp^($prefix, $class, $len^) !== 0^) {
echo         return;
echo     }
echo     
echo     $relative_class = substr^($class, $len^);
echo     $file = $base_dir . str_replace^('\\', '/', $relative_class^) . '.php';
echo     
echo     if ^(file_exists^($file^)^) {
echo         require $file;
echo     }
echo }^);
) > "%PROJECT_DIR%\vendor\autoload.php"

call :log_success "Autoloader creado"

REM Crear .htaccess files
call :log_info "Creando archivos .htaccess..."

(
echo RewriteEngine On
echo RewriteRule ^^$ public/ [L]
echo RewriteRule ^(.*^) public/$1 [L]
) > "%PROJECT_DIR%\.htaccess"

(
echo RewriteEngine On
echo RewriteCond %%{REQUEST_FILENAME} !-f
echo RewriteCond %%{REQUEST_FILENAME} !-d
echo RewriteRule ^^(.*)$ index_basic.php [QSA,L]
echo.
echo # Headers de seguridad
echo Header always set X-Content-Type-Options "nosniff"
echo Header always set X-Frame-Options "DENY"
echo Header always set X-XSS-Protection "1; mode=block"
echo.
echo # CORS para API
echo Header always set Access-Control-Allow-Origin "*"
echo Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
echo Header always set Access-Control-Allow-Headers "Content-Type, Authorization"
) > "%PROJECT_DIR%\public\.htaccess"

call :log_success "Archivos .htaccess creados"

REM Configurar base de datos
call :log_info "Configurando base de datos %DB_NAME%..."

"%MYSQL_PATH%" -u %DB_USER% -p%DB_PASS% -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>nul

if errorlevel 1 (
    call :log_warning "No se pudo crear la base de datos automáticamente"
    call :log_info "Por favor, cree manualmente la base de datos '%DB_NAME%' en phpMyAdmin"
) else (
    call :log_success "Base de datos %DB_NAME% configurada"
)

REM Verificar PHP
call :log_info "Verificando configuración PHP..."

"%PHP_PATH%" -v >nul 2>&1
if errorlevel 1 (
    call :log_error "PHP no encontrado en %PHP_PATH%"
    pause
    exit /b 1
)

call :log_success "PHP encontrado y configurado"

REM Crear archivo de ejemplo para index_basic.php
call :log_info "Creando archivo de ejemplo index_basic.php..."

(
echo ^<?php
echo // Archivo de ejemplo para el Sistema BHE
echo // Reemplazar con el archivo real index_basic.php
echo 
echo header^('Content-Type: application/json'^);
echo 
echo echo json_encode^([
echo     'message' =^> 'Sistema BHE - Configuración completada',
echo     'status' =^> 'ready_for_files',
echo     'next_steps' =^> [
echo         'Copiar archivos del sistema BHE',
echo         'Ejecutar setup_bhe.php',
echo         'Verificar en http://localhost/donfactura'
echo     ]
echo ]^);
) > "%PROJECT_DIR%\public\index_basic.php"

call :log_success "Archivo de ejemplo creado"

REM Mostrar información final
call :log_success "¡CONFIGURACIÓN XAMPP COMPLETADA!"
echo.
echo ==========================================
echo   INFORMACIÓN DEL SISTEMA BHE
echo ==========================================
echo.
echo 📁 Directorio: %PROJECT_DIR%
echo 🗄️ Base de datos: %DB_NAME%
echo 👤 Usuario DB: %DB_USER%
echo 🔑 Password DB: %DB_PASS%
echo 🌐 URL local: http://localhost/donfactura/public/
echo.
echo ==========================================
echo   PRÓXIMOS PASOS
echo ==========================================
echo.
echo 1. Copiar archivos del sistema BHE al directorio:
echo    %PROJECT_DIR%
echo.
echo 2. Ejecutar scripts de configuración:
echo    cd %PROJECT_DIR%
echo    "%PHP_PATH%" setup_bhe.php
echo    "%PHP_PATH%" create_folios_bhe.php
echo    "%PHP_PATH%" create_certificados_bhe.php
echo.
echo 3. Verificar instalación:
echo    http://localhost/donfactura/public/bhe-features
echo.
echo 4. Acceder a phpMyAdmin:
echo    http://localhost/phpmyadmin
echo.
echo ==========================================
echo   ARCHIVOS CREADOS
echo ==========================================
echo.
echo ✅ config/database.php
echo ✅ vendor/autoload.php
echo ✅ .htaccess
echo ✅ public/.htaccess
echo ✅ public/index_basic.php (ejemplo)
echo ✅ Estructura de directorios completa
echo.
echo ¡Instalación base completada!
echo Copie los archivos del sistema BHE para continuar.
echo.
pause
goto :eof

REM Funciones auxiliares
:log_info
echo [INFO] %~1
goto :eof

:log_success
echo [SUCCESS] %~1
goto :eof

:log_warning
echo [WARNING] %~1
goto :eof

:log_error
echo [ERROR] %~1
goto :eof
