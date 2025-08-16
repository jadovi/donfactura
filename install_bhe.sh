#!/bin/bash
# Script de Instalación Automática - Sistema BHE
# Boletas de Honorarios Electrónicas - DTE Tipo 41

set -e  # Exit on any error

echo "🎉 INSTALADOR AUTOMÁTICO SISTEMA BHE 🎉"
echo "========================================"
echo ""

# Variables de configuración
PHP_VERSION="8.1"
DB_NAME="dte_sistema"
DB_USER="dte_user"
DB_PASS=""
PROJECT_DIR="/var/www/html/donfactura"
DOMAIN_NAME="donfactura.local"

# Función para mostrar mensajes
log_info() {
    echo -e "\033[1;34m[INFO]\033[0m $1"
}

log_success() {
    echo -e "\033[1;32m[SUCCESS]\033[0m $1"
}

log_warning() {
    echo -e "\033[1;33m[WARNING]\033[0m $1"
}

log_error() {
    echo -e "\033[1;31m[ERROR]\033[0m $1"
}

# Función para detectar distribución Linux
detect_distro() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        DISTRO=$ID
        VERSION=$VERSION_ID
    else
        log_error "No se puede detectar la distribución Linux"
        exit 1
    fi
    log_info "Distribución detectada: $DISTRO $VERSION"
}

# Función para instalar dependencias según la distribución
install_dependencies() {
    log_info "Instalando dependencias del sistema..."
    
    case $DISTRO in
        ubuntu|debian)
            sudo apt update
            sudo apt install -y apache2 \
                php$PHP_VERSION php$PHP_VERSION-common php$PHP_VERSION-mysql \
                php$PHP_VERSION-xml php$PHP_VERSION-curl php$PHP_VERSION-gd \
                php$PHP_VERSION-mbstring php$PHP_VERSION-json php$PHP_VERSION-zip \
                mariadb-server curl wget unzip
            ;;
        centos|rhel|rocky|almalinux)
            sudo dnf update -y
            sudo dnf install -y httpd \
                php php-common php-pdo php-mysqlnd php-xml php-curl \
                php-gd php-mbstring php-json mariadb-server \
                curl wget unzip
            ;;
        fedora)
            sudo dnf update -y
            sudo dnf install -y httpd \
                php php-common php-pdo php-mysqlnd php-xml php-curl \
                php-gd php-mbstring php-json mariadb-server \
                curl wget unzip
            ;;
        *)
            log_error "Distribución no soportada: $DISTRO"
            log_info "Instale manualmente: Apache, PHP 8.x, MySQL/MariaDB"
            exit 1
            ;;
    esac
    
    log_success "Dependencias instaladas correctamente"
}

# Función para configurar servicios
configure_services() {
    log_info "Configurando servicios del sistema..."
    
    # Habilitar y iniciar Apache
    case $DISTRO in
        ubuntu|debian)
            sudo systemctl enable apache2
            sudo systemctl start apache2
            sudo a2enmod rewrite
            ;;
        centos|rhel|rocky|almalinux|fedora)
            sudo systemctl enable httpd
            sudo systemctl start httpd
            ;;
    esac
    
    # Habilitar y iniciar MariaDB
    sudo systemctl enable mariadb
    sudo systemctl start mariadb
    
    log_success "Servicios configurados y iniciados"
}

# Función para configurar base de datos
setup_database() {
    log_info "Configurando base de datos..."
    
    # Generar password si no se proporciona
    if [ -z "$DB_PASS" ]; then
        DB_PASS=$(openssl rand -base64 12)
        log_info "Password generado automáticamente: $DB_PASS"
    fi
    
    # Configurar MySQL/MariaDB
    sudo mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    sudo mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
    sudo mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
    sudo mysql -e "FLUSH PRIVILEGES;"
    
    log_success "Base de datos configurada: $DB_NAME"
    log_info "Usuario: $DB_USER"
    log_info "Password: $DB_PASS"
}

# Función para crear estructura de directorios
create_directories() {
    log_info "Creando estructura de directorios..."
    
    sudo mkdir -p $PROJECT_DIR
    sudo chown $USER:$USER $PROJECT_DIR
    
    cd $PROJECT_DIR
    
    # Crear directorios principales
    mkdir -p config
    mkdir -p src/{Core,Models,Services,Controllers,Middleware,Utils}
    mkdir -p public
    mkdir -p storage/{certificates,generated,temp,logs}
    mkdir -p vendor/psr/{http-message,log}
    mkdir -p examples
    mkdir -p database
    
    log_success "Estructura de directorios creada"
}

# Función para crear archivos de configuración
create_config_files() {
    log_info "Creando archivos de configuración..."
    
    # Configuración de base de datos
    cat > config/database.php << EOF
<?php
return [
    'database' => [
        'host' => 'localhost',
        'port' => 3306,
        'database' => '$DB_NAME',
        'username' => '$DB_USER',
        'password' => '$DB_PASS',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],
    'sii' => [
        'cert_url_solicitud_folios' => 'https://maullin.sii.cl/DTEWS/GetTokenFromSeed.jws',
        'cert_url_upload_dte' => 'https://maullin.sii.cl/DTEWS/services/wsdte',
        'prod_url_solicitud_folios' => 'https://palena.sii.cl/DTEWS/GetTokenFromSeed.jws',
        'prod_url_upload_dte' => 'https://palena.sii.cl/DTEWS/services/wsdte',
        'environment' => 'certification',
    ],
    'paths' => [
        'certificates' => __DIR__ . '/../storage/certificates/',
        'xml_temp' => __DIR__ . '/../storage/temp/',
        'xml_generated' => __DIR__ . '/../storage/generated/',
        'logs' => __DIR__ . '/../storage/logs/',
    ],
    'dte_types' => [
        33 => 'Factura Electrónica',
        34 => 'Factura Electrónica Exenta',
        39 => 'Boleta Electrónica',
        41 => 'Boleta de Honorarios Electrónica (BHE)',
        45 => 'Factura de Compra Electrónica',
        56 => 'Nota de Débito Electrónica',
        61 => 'Nota de Crédito Electrónica',
    ]
];
EOF
    
    # .htaccess principal
    cat > .htaccess << EOF
RewriteEngine On
RewriteRule ^$ public/ [L]
RewriteRule (.*) public/\$1 [L]
EOF
    
    # .htaccess público
    cat > public/.htaccess << EOF
RewriteEngine On
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
EOF
    
    log_success "Archivos de configuración creados"
}

# Función para configurar VirtualHost
configure_virtualhost() {
    log_info "Configurando VirtualHost de Apache..."
    
    local vhost_file=""
    case $DISTRO in
        ubuntu|debian)
            vhost_file="/etc/apache2/sites-available/donfactura.conf"
            ;;
        centos|rhel|rocky|almalinux|fedora)
            vhost_file="/etc/httpd/conf.d/donfactura.conf"
            ;;
    esac
    
    sudo tee $vhost_file > /dev/null << EOF
<VirtualHost *:80>
    ServerName $DOMAIN_NAME
    DocumentRoot $PROJECT_DIR/public
    
    <Directory $PROJECT_DIR/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/donfactura_error.log
    CustomLog \${APACHE_LOG_DIR}/donfactura_access.log combined
    
    # Configuración PHP para BHE
    php_admin_value upload_max_filesize 10M
    php_admin_value post_max_size 10M
    php_admin_value memory_limit 256M
    php_admin_value max_execution_time 300
</VirtualHost>
EOF
    
    # Activar sitio
    case $DISTRO in
        ubuntu|debian)
            sudo a2ensite donfactura.conf
            ;;
    esac
    
    # Agregar al hosts
    if ! grep -q "$DOMAIN_NAME" /etc/hosts; then
        echo "127.0.0.1 $DOMAIN_NAME" | sudo tee -a /etc/hosts
    fi
    
    # Reiniciar Apache
    case $DISTRO in
        ubuntu|debian)
            sudo systemctl restart apache2
            ;;
        centos|rhel|rocky|almalinux|fedora)
            sudo systemctl restart httpd
            ;;
    esac
    
    log_success "VirtualHost configurado: http://$DOMAIN_NAME"
}

# Función para establecer permisos
set_permissions() {
    log_info "Configurando permisos..."
    
    # Permisos para Apache
    case $DISTRO in
        ubuntu|debian)
            sudo chown -R www-data:www-data storage/
            sudo chown -R www-data:www-data public/
            ;;
        centos|rhel|rocky|almalinux|fedora)
            sudo chown -R apache:apache storage/
            sudo chown -R apache:apache public/
            ;;
    esac
    
    sudo chmod -R 775 storage/
    sudo chmod -R 755 public/
    
    log_success "Permisos configurados"
}

# Función para crear autoloader básico
create_autoloader() {
    log_info "Creando autoloader..."
    
    cat > vendor/autoload.php << 'EOF'
<?php
spl_autoload_register(function ($class) {
    $prefix = 'DonFactura\\DTE\\';
    $base_dir = __DIR__ . '/../src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});
EOF
    
    log_success "Autoloader creado"
}

# Función para mostrar información final
show_final_info() {
    echo ""
    echo "🎉 ¡INSTALACIÓN COMPLETADA EXITOSAMENTE! 🎉"
    echo "==========================================="
    echo ""
    log_success "Sistema BHE instalado correctamente"
    echo ""
    echo "📋 INFORMACIÓN DEL SISTEMA:"
    echo "   • URL: http://$DOMAIN_NAME"
    echo "   • Directorio: $PROJECT_DIR"
    echo "   • Base de datos: $DB_NAME"
    echo "   • Usuario DB: $DB_USER"
    echo "   • Password DB: $DB_PASS"
    echo ""
    echo "🔧 PRÓXIMOS PASOS:"
    echo "   1. Copiar archivos del sistema BHE al directorio:"
    echo "      $PROJECT_DIR"
    echo ""
    echo "   2. Ejecutar scripts de configuración:"
    echo "      cd $PROJECT_DIR"
    echo "      php setup_bhe.php"
    echo "      php create_folios_bhe.php"
    echo "      php create_certificados_bhe.php"
    echo ""
    echo "   3. Verificar instalación:"
    echo "      http://$DOMAIN_NAME/bhe-features"
    echo ""
    echo "📄 ARCHIVOS DE CONFIGURACIÓN CREADOS:"
    echo "   • config/database.php"
    echo "   • .htaccess"
    echo "   • public/.htaccess"
    echo "   • VirtualHost Apache"
    echo ""
    echo "📞 SOPORTE:"
    echo "   • Logs: $PROJECT_DIR/storage/logs/"
    echo "   • Apache logs: /var/log/apache*/donfactura_*.log"
    echo ""
    echo "¡Guarde la información de la base de datos en un lugar seguro!"
}

# Función principal
main() {
    echo "Iniciando instalación del Sistema BHE..."
    echo ""
    
    # Verificar que se ejecuta como usuario con sudo
    if ! sudo -n true 2>/dev/null; then
        log_error "Este script requiere permisos sudo"
        exit 1
    fi
    
    # Solicitar información básica
    read -p "Dominio/nombre del sitio [$DOMAIN_NAME]: " input_domain
    DOMAIN_NAME=${input_domain:-$DOMAIN_NAME}
    
    read -p "Directorio de instalación [$PROJECT_DIR]: " input_dir
    PROJECT_DIR=${input_dir:-$PROJECT_DIR}
    
    read -p "Nombre de la base de datos [$DB_NAME]: " input_db
    DB_NAME=${input_db:-$DB_NAME}
    
    read -p "Usuario de la base de datos [$DB_USER]: " input_user
    DB_USER=${input_user:-$DB_USER}
    
    read -s -p "Password de la base de datos (vacío para auto-generar): " input_pass
    DB_PASS=${input_pass:-$DB_PASS}
    echo ""
    echo ""
    
    # Ejecutar instalación
    detect_distro
    install_dependencies
    configure_services
    setup_database
    create_directories
    create_config_files
    create_autoloader
    configure_virtualhost
    set_permissions
    show_final_info
}

# Función de ayuda
show_help() {
    echo "Script de Instalación Automática - Sistema BHE"
    echo ""
    echo "Uso: $0 [opciones]"
    echo ""
    echo "Opciones:"
    echo "  -h, --help     Mostrar esta ayuda"
    echo "  -d DOMAIN      Dominio del sitio (default: donfactura.local)"
    echo "  -p PATH        Directorio de instalación (default: /var/www/html/donfactura)"
    echo "  --db-name      Nombre de la base de datos (default: dte_sistema)"
    echo "  --db-user      Usuario de la base de datos (default: dte_user)"
    echo "  --db-pass      Password de la base de datos"
    echo ""
    echo "Ejemplo:"
    echo "  $0 -d midominio.com -p /var/www/miapp"
    echo ""
}

# Procesar argumentos de línea de comandos
while [[ $# -gt 0 ]]; do
    case $1 in
        -h|--help)
            show_help
            exit 0
            ;;
        -d|--domain)
            DOMAIN_NAME="$2"
            shift 2
            ;;
        -p|--path)
            PROJECT_DIR="$2"
            shift 2
            ;;
        --db-name)
            DB_NAME="$2"
            shift 2
            ;;
        --db-user)
            DB_USER="$2"
            shift 2
            ;;
        --db-pass)
            DB_PASS="$2"
            shift 2
            ;;
        *)
            log_error "Opción desconocida: $1"
            show_help
            exit 1
            ;;
    esac
done

# Ejecutar script principal
main
