@echo off
:: Setup script para Frontend DonFactura (Windows)
:: Autor: DonFactura Team
:: Version: 1.0

echo.
echo 🚀 Configurando Frontend DonFactura...
echo ==================================
echo.

:: Verificar que estamos en el directorio correcto
if not exist "index.html" (
    echo ❌ Este script debe ejecutarse desde el directorio frontend/
    pause
    exit /b 1
)

if not exist "config.js" (
    echo ❌ Archivo config.js no encontrado
    pause
    exit /b 1
)

echo ✅ Directorio frontend encontrado

:: Verificar Python
python --version >nul 2>&1
if %errorlevel% equ 0 (
    set PYTHON_CMD=python
    echo ✅ Python encontrado
) else (
    py --version >nul 2>&1
    if %errorlevel% equ 0 (
        set PYTHON_CMD=py
        echo ✅ Python encontrado ^(py launcher^)
    ) else (
        echo ❌ Python no está instalado
        echo Por favor instala Python 3.x desde https://python.org
        pause
        exit /b 1
    )
)

:: Obtener versión de Python
for /f "tokens=2" %%i in ('%PYTHON_CMD% --version 2^>^&1') do set PYTHON_VERSION=%%i
echo ℹ️  Versión de Python: %PYTHON_VERSION%

:: Verificar API Backend
echo.
echo 🔍 Verificando conexión con API Backend...

curl -s http://localhost:8000/health >nul 2>&1
if %errorlevel% equ 0 (
    echo ✅ API Backend está corriendo en http://localhost:8000
    set API_RUNNING=true
) else (
    echo ⚠️  API Backend no responde en http://localhost:8000
    set API_RUNNING=false
)

:: Configurar puerto
echo.
echo 🔧 Configuración del servidor web:
set /p PORT="Puerto (default: 3000): "
if "%PORT%"=="" set PORT=3000

:: Verificar puerto disponible
netstat -an | findstr :%PORT% >nul 2>&1
if %errorlevel% equ 0 (
    echo ⚠️  El puerto %PORT% está en uso
    set /p NEW_PORT="¿Usar otro puerto? (ingrese número o Enter para continuar): "
    if not "%NEW_PORT%"=="" set PORT=%NEW_PORT%
)

:: Mostrar resumen
echo.
echo 📋 Resumen de Configuración:
echo    Puerto:           %PORT%
echo    API Backend:      
if "%API_RUNNING%"=="true" (
    echo                      ✅ Corriendo
) else (
    echo                      ❌ No disponible
)
echo    Sistema:          Windows
echo    Python:           %PYTHON_VERSION%
echo.

:: Mostrar instrucciones si API no está corriendo
if "%API_RUNNING%"=="false" (
    echo ⚠️  IMPORTANTE: API Backend no está corriendo
    echo Para usar todas las funcionalidades:
    echo 1. Ve al directorio raíz del proyecto
    echo 2. Ejecuta: cd public ^&^& php -S localhost:8000 index_basic.php
    echo 3. Recarga esta página
    echo.
)

echo 💡 Consejos:
echo    - Usa demo.html para testing avanzado
echo    - Revisa config.js para personalizar configuración
echo    - Los logs se guardan en el directorio logs/
echo.

:: Mostrar URLs de acceso
echo 📡 URLs de Acceso:
echo    Local:     http://localhost:%PORT%
echo    Principal: http://localhost:%PORT%/index.html
echo    Demo:      http://localhost:%PORT%/demo.html
echo.

:: Preguntar si abrir navegador
set /p OPEN_BROWSER="¿Abrir automáticamente en el navegador? (y/n): "
if /i "%OPEN_BROWSER%"=="y" (
    echo ℹ️  Abriendo navegador en 3 segundos...
    timeout /t 3 /nobreak >nul
    start http://localhost:%PORT%/index.html
)

echo.
echo 🚀 Iniciando servidor web...
echo Presiona Ctrl+C para detener.
echo.

:: Iniciar servidor Python
%PYTHON_CMD% -m http.server %PORT%

pause
