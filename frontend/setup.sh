#!/bin/bash

# Setup script para Frontend DonFactura
# Autor: DonFactura Team
# Versión: 1.0

echo "🚀 Configurando Frontend DonFactura..."
echo "=================================="

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Función para mostrar mensajes
show_message() {
    echo -e "${GREEN}✅ $1${NC}"
}

show_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

show_error() {
    echo -e "${RED}❌ $1${NC}"
}

show_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

# Verificar si estamos en el directorio correcto
if [ ! -f "index.html" ] || [ ! -f "config.js" ]; then
    show_error "Este script debe ejecutarse desde el directorio frontend/"
    exit 1
fi

show_info "Directorio frontend encontrado"

# Detectar sistema operativo
OS="unknown"
case "$(uname -s)" in
    Linux*)     OS="Linux";;
    Darwin*)    OS="Mac";;
    CYGWIN*|MINGW32*|MSYS*|MINGW*) OS="Windows";;
esac

show_info "Sistema operativo detectado: $OS"

# Función para verificar comando
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Verificar Python
if command_exists python3; then
    PYTHON_CMD="python3"
    show_message "Python3 encontrado"
elif command_exists python; then
    PYTHON_CMD="python"
    show_message "Python encontrado"
else
    show_error "Python no está instalado"
    echo "Por favor instala Python 3.x para continuar"
    exit 1
fi

# Verificar versión de Python
PYTHON_VERSION=$($PYTHON_CMD --version 2>&1 | cut -d' ' -f2 | cut -d'.' -f1-2)
show_info "Versión de Python: $PYTHON_VERSION"

# Función para obtener IP local
get_local_ip() {
    case "$OS" in
        "Linux")
            hostname -I | awk '{print $1}'
            ;;
        "Mac")
            ifconfig | grep "inet " | grep -v 127.0.0.1 | awk '{print $2}' | head -1
            ;;
        "Windows")
            ipconfig | grep "IPv4" | head -1 | awk '{print $NF}'
            ;;
        *)
            echo "localhost"
            ;;
    esac
}

LOCAL_IP=$(get_local_ip)
show_info "IP local detectada: $LOCAL_IP"

# Verificar API Backend
echo ""
echo "🔍 Verificando conexión con API Backend..."

API_URL="http://localhost:8000"
if command_exists curl; then
    if curl -s "$API_URL/health" > /dev/null 2>&1; then
        show_message "API Backend está corriendo en $API_URL"
        API_RUNNING=true
    else
        show_warning "API Backend no responde en $API_URL"
        API_RUNNING=false
    fi
else
    show_warning "curl no está instalado, no se puede verificar API Backend"
    API_RUNNING=false
fi

# Configurar archivo config.js si es necesario
echo ""
echo "⚙️  Configurando archivo config.js..."

if [ "$API_RUNNING" = false ]; then
    show_warning "Actualizando config.js con configuración por defecto"
    # Aquí podrías modificar config.js si fuera necesario
fi

# Crear directorio de logs local si no existe
if [ ! -d "logs" ]; then
    mkdir logs
    show_message "Directorio logs creado"
fi

# Verificar permisos
if [ ! -w "." ]; then
    show_error "No tienes permisos de escritura en este directorio"
    exit 1
fi

# Función para abrir navegador
open_browser() {
    local url=$1
    case "$OS" in
        "Linux")
            if command_exists xdg-open; then
                xdg-open "$url"
            elif command_exists firefox; then
                firefox "$url" &
            elif command_exists chromium-browser; then
                chromium-browser "$url" &
            fi
            ;;
        "Mac")
            open "$url"
            ;;
        "Windows")
            start "$url"
            ;;
    esac
}

# Función para iniciar servidor
start_server() {
    local port=$1
    local interface=$2
    
    echo ""
    echo "🌐 Iniciando servidor web..."
    show_info "Comando: $PYTHON_CMD -m http.server $port --bind $interface"
    
    # Mostrar URLs de acceso
    echo ""
    echo "📡 URLs de Acceso:"
    echo "   Local:    http://localhost:$port"
    echo "   Red:      http://$LOCAL_IP:$port"
    echo ""
    echo "📄 Páginas disponibles:"
    echo "   Principal:  http://localhost:$port/index.html"
    echo "   Demo:       http://localhost:$port/demo.html"
    echo ""
    
    # Preguntar si abrir navegador
    echo -n "¿Abrir automáticamente en el navegador? (y/n): "
    read -r response
    if [[ "$response" =~ ^[Yy] ]]; then
        show_info "Abriendo navegador en 3 segundos..."
        sleep 3
        open_browser "http://localhost:$port/index.html"
    fi
    
    echo ""
    echo "🚀 Servidor iniciado. Presiona Ctrl+C para detener."
    echo ""
    
    # Iniciar servidor
    $PYTHON_CMD -m http.server "$port" --bind "$interface"
}

# Preguntar configuración del servidor
echo ""
echo "🔧 Configuración del servidor web:"

# Puerto
echo -n "Puerto (default: 3000): "
read -r PORT
PORT=${PORT:-3000}

# Interfaz
echo -n "Permitir acceso desde red local? (y/n, default: n): "
read -r NETWORK_ACCESS
if [[ "$NETWORK_ACCESS" =~ ^[Yy] ]]; then
    BIND_ADDRESS="0.0.0.0"
    show_info "Servidor accesible desde la red"
else
    BIND_ADDRESS="127.0.0.1"
    show_info "Servidor solo accesible localmente"
fi

# Verificar que el puerto esté libre
if command_exists netstat; then
    if netstat -ln | grep ":$PORT " > /dev/null; then
        show_warning "El puerto $PORT está en uso"
        echo -n "¿Usar otro puerto? (ingrese número o Enter para continuar): "
        read -r NEW_PORT
        if [ -n "$NEW_PORT" ]; then
            PORT=$NEW_PORT
        fi
    fi
fi

# Mostrar resumen de configuración
echo ""
echo "📋 Resumen de Configuración:"
echo "   Puerto:           $PORT"
echo "   Interfaz:         $BIND_ADDRESS"
echo "   API Backend:      $([ "$API_RUNNING" = true ] && echo "✅ Corriendo" || echo "❌ No disponible")"
echo "   Sistema:          $OS"
echo "   Python:           $PYTHON_VERSION"
echo ""

# Mostrar instrucciones finales
if [ "$API_RUNNING" = false ]; then
    show_warning "IMPORTANTE: API Backend no está corriendo"
    echo "Para usar todas las funcionalidades:"
    echo "1. Ve al directorio raíz del proyecto"
    echo "2. Ejecuta: cd public && php -S localhost:8000 index_basic.php"
    echo "3. Recarga esta página"
    echo ""
fi

echo "💡 Consejos:"
echo "   - Usa demo.html para testing avanzado"
echo "   - Revisa config.js para personalizar configuración"
echo "   - Los logs se guardan en el directorio logs/"
echo ""

# Iniciar servidor
start_server "$PORT" "$BIND_ADDRESS"
