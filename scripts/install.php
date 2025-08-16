<?php
/**
 * Script de instalación para DonFactura DTE API
 */

echo "=== INSTALADOR DONFACTURA DTE API ===\n\n";

// Verificar versión de PHP
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    die("Error: Se requiere PHP 8.0 o superior. Versión actual: " . PHP_VERSION . "\n");
}

echo "✓ PHP " . PHP_VERSION . " detectado\n";

// Verificar extensiones necesarias
$extensiones = ['pdo', 'pdo_mysql', 'openssl', 'curl', 'simplexml', 'json'];
$faltantes = [];

foreach ($extensiones as $ext) {
    if (!extension_loaded($ext)) {
        $faltantes[] = $ext;
    }
}

if (!empty($faltantes)) {
    die("Error: Faltan las siguientes extensiones PHP: " . implode(', ', $faltantes) . "\n");
}

echo "✓ Todas las extensiones PHP necesarias están disponibles\n";

// Verificar si Composer está instalado
exec('composer --version', $output, $return);
if ($return !== 0) {
    die("Error: Composer no está instalado. Por favor instala Composer primero.\n");
}

echo "✓ Composer detectado\n";

// Instalar dependencias
echo "\n--- Instalando dependencias ---\n";
system('composer install --no-dev --optimize-autoloader');

// Crear directorios necesarios
$directorios = [
    'storage',
    'storage/certificates',
    'storage/temp',
    'storage/generated',
    'storage/logs'
];

echo "\n--- Creando directorios ---\n";
foreach ($directorios as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✓ Creado: $dir\n";
        } else {
            echo "✗ Error creando: $dir\n";
        }
    } else {
        echo "- Ya existe: $dir\n";
    }
}

// Verificar permisos de escritura
echo "\n--- Verificando permisos ---\n";
foreach ($directorios as $dir) {
    if (is_writable($dir)) {
        echo "✓ Escritura OK: $dir\n";
    } else {
        echo "✗ Sin permisos de escritura: $dir\n";
        echo "  Ejecuta: chmod 755 $dir\n";
    }
}

// Configurar base de datos
echo "\n--- Configuración de Base de Datos ---\n";
echo "Por favor configura la base de datos manualmente:\n";
echo "1. Inicia XAMPP con MariaDB\n";
echo "2. Ejecuta: mysql -u root -p123123 < database/create_database.sql\n";
echo "3. Verifica la configuración en config/database.php\n";

// Crear archivo de ejemplo .env si no existe
if (!file_exists('.env')) {
    $envContent = "# Configuración de entorno DonFactura
APP_ENV=development
APP_DEBUG=true

# Base de datos
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=dte_sistema
DB_USERNAME=root
DB_PASSWORD=123123

# SII
SII_ENVIRONMENT=certification
";
    
    if (file_put_contents('.env', $envContent)) {
        echo "✓ Archivo .env creado\n";
    }
}

// Mostrar información final
echo "\n=== INSTALACIÓN COMPLETADA ===\n";
echo "Para iniciar el servidor de desarrollo:\n";
echo "  cd public\n";
echo "  php -S localhost:8000\n\n";
echo "La API estará disponible en: http://localhost:8000\n";
echo "Documentación: http://localhost:8000 (página principal)\n\n";
echo "Próximos pasos:\n";
echo "1. Configurar base de datos (ver instrucciones arriba)\n";
echo "2. Subir certificado digital PFX\n";
echo "3. Solicitar folios CAF al SII\n";
echo "4. Comenzar a generar DTE\n\n";
echo "¡Listo para usar! 🚀\n";
?>
