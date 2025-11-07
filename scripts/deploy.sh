#!/bin/bash

################################################################################
# MailCore - Script de Despliegue
#
# Este script despliega MailCore en un servidor ya configurado.
# Asume que el stack LEMP y servicios de mail ya están instalados.
#
# Uso: ./deploy.sh [environment]
#   environment: production, staging (default: production)
################################################################################

set -e  # Exit on error

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Functions
print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_step() {
    echo ""
    echo -e "${GREEN}===================================================================${NC}"
    echo -e "${GREEN}  $1${NC}"
    echo -e "${GREEN}===================================================================${NC}"
    echo ""
}

# Check if script is run from correct directory
if [ ! -f "artisan" ]; then
    print_error "Este script debe ejecutarse desde el directorio raíz de MailCore"
    exit 1
fi

# Get environment
ENVIRONMENT=${1:-production}

if [ "$ENVIRONMENT" != "production" ] && [ "$ENVIRONMENT" != "staging" ]; then
    print_error "Environment debe ser 'production' o 'staging'"
    exit 1
fi

print_step "MAILCORE - DESPLIEGUE ($ENVIRONMENT)"

################################################################################
# STEP 1: Check Prerequisites
################################################################################

print_step "PASO 1: VERIFICAR REQUISITOS"

# Check PHP
if ! command -v php &> /dev/null; then
    print_error "PHP no está instalado"
    exit 1
fi
PHP_VERSION=$(php -r "echo PHP_VERSION;")
print_success "PHP $PHP_VERSION instalado"

# Check Composer
if ! command -v composer &> /dev/null; then
    print_error "Composer no está instalado"
    exit 1
fi
print_success "Composer instalado"

# Check Node.js
if ! command -v node &> /dev/null; then
    print_error "Node.js no está instalado"
    exit 1
fi
NODE_VERSION=$(node --version)
print_success "Node.js $NODE_VERSION instalado"

# Check MySQL
if ! command -v mysql &> /dev/null; then
    print_error "MySQL no está instalado"
    exit 1
fi
print_success "MySQL instalado"

# Check Redis
if ! redis-cli ping &> /dev/null; then
    print_error "Redis no está corriendo"
    exit 1
fi
print_success "Redis corriendo"

################################################################################
# STEP 2: Configure Environment
################################################################################

print_step "PASO 2: CONFIGURAR ENTORNO"

# Check if .env exists
if [ ! -f ".env" ]; then
    print_info "Creando archivo .env desde .env.example..."
    cp .env.example .env

    print_warning "Debes configurar el archivo .env antes de continuar"
    echo ""
    read -p "¿Quieres editar .env ahora? (s/n): " EDIT_ENV

    if [ "$EDIT_ENV" == "s" ] || [ "$EDIT_ENV" == "S" ]; then
        ${EDITOR:-nano} .env
    else
        print_error "Por favor, configura .env y vuelve a ejecutar este script"
        exit 1
    fi
else
    print_success "Archivo .env encontrado"
fi

# Validate .env
print_info "Validando configuración de .env..."

# Check required variables
REQUIRED_VARS=("DB_DATABASE" "DB_USERNAME" "DB_PASSWORD" "APP_URL")
MISSING_VARS=()

for VAR in "${REQUIRED_VARS[@]}"; do
    if ! grep -q "^$VAR=" .env || grep -q "^$VAR=$" .env; then
        MISSING_VARS+=("$VAR")
    fi
done

if [ ${#MISSING_VARS[@]} -gt 0 ]; then
    print_error "Faltan variables requeridas en .env:"
    for VAR in "${MISSING_VARS[@]}"; do
        echo "  - $VAR"
    done
    exit 1
fi

print_success "Configuración de .env válida"

################################################################################
# STEP 3: Install Dependencies
################################################################################

print_step "PASO 3: INSTALAR DEPENDENCIAS"

print_info "Instalando dependencias de Composer..."
if [ "$ENVIRONMENT" == "production" ]; then
    composer install --optimize-autoloader --no-dev --no-interaction
else
    composer install --optimize-autoloader --no-interaction
fi
print_success "Dependencias de Composer instaladas"

print_info "Instalando dependencias de npm..."
npm ci
print_success "Dependencias de npm instaladas"

################################################################################
# STEP 4: Generate App Key
################################################################################

print_step "PASO 4: GENERAR APP KEY"

# Check if key exists
if grep -q "APP_KEY=base64:" .env; then
    print_success "APP_KEY ya existe"
else
    print_info "Generando APP_KEY..."
    php artisan key:generate --force
    print_success "APP_KEY generada"
fi

################################################################################
# STEP 5: Build Assets
################################################################################

print_step "PASO 5: COMPILAR ASSETS"

print_info "Compilando assets de frontend..."
if [ "$ENVIRONMENT" == "production" ]; then
    npm run build
else
    npm run dev
fi
print_success "Assets compilados"

################################################################################
# STEP 6: Set Permissions
################################################################################

print_step "PASO 6: CONFIGURAR PERMISOS"

print_info "Configurando permisos de archivos..."

# Set ownership
sudo chown -R www-data:www-data .

# Set base permissions
sudo chmod -R 755 .

# Set storage and cache permissions
sudo chmod -R 775 storage bootstrap/cache

print_success "Permisos configurados"

################################################################################
# STEP 7: Database Migration
################################################################################

print_step "PASO 7: MIGRAR BASE DE DATOS"

print_warning "Se ejecutarán las migraciones de la base de datos"
read -p "¿Continuar? (s/n): " RUN_MIGRATIONS

if [ "$RUN_MIGRATIONS" == "s" ] || [ "$RUN_MIGRATIONS" == "S" ]; then
    print_info "Ejecutando migraciones..."
    php artisan migrate --force
    print_success "Migraciones completadas"
else
    print_warning "Migraciones saltadas"
fi

################################################################################
# STEP 8: Seed Database (Optional)
################################################################################

if [ "$ENVIRONMENT" != "production" ]; then
    print_step "PASO 8: SEEDERS (OPCIONAL)"

    read -p "¿Quieres ejecutar los seeders? (s/n): " RUN_SEEDERS

    if [ "$RUN_SEEDERS" == "s" ] || [ "$RUN_SEEDERS" == "S" ]; then
        print_info "Ejecutando seeders..."
        php artisan db:seed --force
        print_success "Seeders completados"
    fi
else
    print_info "Saltando seeders en producción"
fi

################################################################################
# STEP 9: Create Admin User
################################################################################

print_step "PASO 9: CREAR USUARIO ADMINISTRADOR"

# Check if users exist
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();")

if [ "$USER_COUNT" -eq 0 ]; then
    print_info "Creando usuario administrador..."
    php artisan make:filament-user
    print_success "Usuario administrador creado"
else
    print_info "Ya existen usuarios en el sistema ($USER_COUNT usuarios)"
    read -p "¿Crear otro usuario administrador? (s/n): " CREATE_USER

    if [ "$CREATE_USER" == "s" ] || [ "$CREATE_USER" == "S" ]; then
        php artisan make:filament-user
    fi
fi

################################################################################
# STEP 10: Optimize Application
################################################################################

print_step "PASO 10: OPTIMIZAR APLICACIÓN"

print_info "Limpiando cachés..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

if [ "$ENVIRONMENT" == "production" ]; then
    print_info "Cacheando configuración para producción..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
    print_success "Aplicación optimizada para producción"
else
    print_success "Cachés limpiadas"
fi

################################################################################
# STEP 11: Setup Supervisor
################################################################################

print_step "PASO 11: CONFIGURAR SUPERVISOR"

CURRENT_DIR=$(pwd)
SUPERVISOR_CONF="/etc/supervisor/conf.d/mailcore-worker.conf"

# Check if supervisor config exists
if [ ! -f "$SUPERVISOR_CONF" ]; then
    print_info "Creando configuración de Supervisor..."

    sudo tee $SUPERVISOR_CONF > /dev/null <<EOF
[program:mailcore-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php $CURRENT_DIR/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --timeout=300
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=$CURRENT_DIR/storage/logs/worker.log
stopwaitsecs=3600
EOF

    sudo supervisorctl reread
    sudo supervisorctl update
    print_success "Supervisor configurado"
else
    print_info "Configuración de Supervisor ya existe"
    read -p "¿Reiniciar workers? (s/n): " RESTART_WORKERS

    if [ "$RESTART_WORKERS" == "s" ] || [ "$RESTART_WORKERS" == "S" ]; then
        sudo supervisorctl restart mailcore-worker:*
        print_success "Workers reiniciados"
    fi
fi

# Verify supervisor status
sudo supervisorctl status mailcore-worker:*

################################################################################
# STEP 12: Setup Cron Jobs
################################################################################

print_step "PASO 12: CONFIGURAR CRON JOBS"

# Check if cron job exists
if sudo -u www-data crontab -l 2>/dev/null | grep -q "$CURRENT_DIR"; then
    print_success "Cron job ya existe"
else
    print_info "Añadiendo cron job para Laravel Scheduler..."
    (sudo -u www-data crontab -l 2>/dev/null; echo "* * * * * cd $CURRENT_DIR && php artisan schedule:run >> /dev/null 2>&1") | sudo -u www-data crontab -
    print_success "Cron job añadido"
fi

################################################################################
# STEP 13: Configure Nginx
################################################################################

print_step "PASO 13: CONFIGURAR NGINX"

# Get domain from .env
DOMAIN=$(grep "^APP_URL=" .env | cut -d '=' -f2 | sed 's|https\?://||' | sed 's|/$||')

NGINX_CONF="/etc/nginx/sites-available/mailcore.conf"

if [ ! -f "$NGINX_CONF" ]; then
    print_info "Creando configuración de Nginx..."

    sudo tee $NGINX_CONF > /dev/null <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name $DOMAIN;

    root $CURRENT_DIR/public;
    index index.php index.html;

    # SSL (managed by Certbot)
    ssl_certificate /etc/letsencrypt/live/$DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/$DOMAIN/privkey.pem;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Logs
    access_log /var/log/nginx/mailcore-access.log;
    error_log /var/log/nginx/mailcore-error.log;

    client_max_body_size 50M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_buffer_size 128k;
        fastcgi_buffers 4 256k;
        fastcgi_busy_buffers_size 256k;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF

    sudo ln -sf $NGINX_CONF /etc/nginx/sites-enabled/
    sudo nginx -t && sudo systemctl reload nginx
    print_success "Nginx configurado"
else
    print_success "Configuración de Nginx ya existe"
    read -p "¿Recargar Nginx? (s/n): " RELOAD_NGINX

    if [ "$RELOAD_NGINX" == "s" ] || [ "$RELOAD_NGINX" == "S" ]; then
        sudo nginx -t && sudo systemctl reload nginx
        print_success "Nginx recargado"
    fi
fi

################################################################################
# STEP 14: Configure DKIM
################################################################################

print_step "PASO 14: CONFIGURAR DKIM"

print_info "Para configurar DKIM para tus dominios, usa los comandos de MailCore:"
echo ""
echo "  php artisan mailcore:generate-dkim tudominio.com"
echo ""
print_warning "Las claves DKIM deben generarse después de añadir dominios en el panel de administración"

################################################################################
# STEP 15: Health Check
################################################################################

print_step "PASO 15: VERIFICACIÓN DE SALUD"

print_info "Verificando servicios..."

# Check web server
if curl -s -o /dev/null -w "%{http_code}" "http://localhost" | grep -q "200\|301\|302"; then
    print_success "Servidor web respondiendo"
else
    print_warning "Servidor web no responde"
fi

# Check database connection
if php artisan tinker --execute="DB::connection()->getPdo();" &> /dev/null; then
    print_success "Conexión a base de datos OK"
else
    print_error "No se puede conectar a la base de datos"
fi

# Check Redis
if php artisan tinker --execute="Cache::put('test', 'ok', 10);" &> /dev/null; then
    print_success "Conexión a Redis OK"
else
    print_warning "No se puede conectar a Redis"
fi

# Check queue workers
WORKER_COUNT=$(sudo supervisorctl status mailcore-worker:* | grep RUNNING | wc -l)
if [ "$WORKER_COUNT" -gt 0 ]; then
    print_success "Workers corriendo: $WORKER_COUNT"
else
    print_warning "No hay workers corriendo"
fi

################################################################################
# FINAL: Summary
################################################################################

print_step "DESPLIEGUE COMPLETADO"

echo ""
print_success "¡MailCore ha sido desplegado exitosamente!"
echo ""
print_info "INFORMACIÓN DEL DESPLIEGUE:"
echo ""
echo "  🌐 URL: https://$DOMAIN"
echo "  🔐 Admin Panel: https://$DOMAIN/admin"
echo "  📁 Directorio: $CURRENT_DIR"
echo "  🏷️  Entorno: $ENVIRONMENT"
echo ""
echo "  📊 Estado de servicios:"
echo "     - Web Server: $(systemctl is-active nginx)"
echo "     - PHP-FPM: $(systemctl is-active php8.2-fpm)"
echo "     - MySQL: $(systemctl is-active mysql)"
echo "     - Redis: $(redis-cli ping 2>/dev/null || echo 'FAILED')"
echo "     - Workers: $WORKER_COUNT corriendo"
echo ""
echo "  📝 Próximos pasos:"
echo ""
echo "     1. Acceder al panel de administración: https://$DOMAIN/admin"
echo "     2. Añadir dominios desde el panel"
echo "     3. Generar claves DKIM: php artisan mailcore:generate-dkim DOMINIO"
echo "     4. Configurar DNS (SPF, DKIM, DMARC)"
echo "     5. Probar envío de emails desde la API"
echo ""
echo "  🔧 Comandos útiles:"
echo "     - Ver logs: tail -f storage/logs/laravel.log"
echo "     - Reiniciar workers: sudo supervisorctl restart mailcore-worker:*"
echo "     - Limpiar caché: php artisan cache:clear"
echo "     - Ver cola: php artisan queue:work --once"
echo ""
echo "  📚 Documentación:"
echo "     - DEPLOYMENT.md: Guía completa de despliegue"
echo "     - API.md: Documentación de la API"
echo "     - LEGAL_COMPLIANCE.md: Cumplimiento legal"
echo ""

if [ "$ENVIRONMENT" == "production" ]; then
    print_warning "IMPORTANTE PARA PRODUCCIÓN:"
    echo "  - Configura backups automáticos"
    echo "  - Configura monitoreo (uptimerobot, etc.)"
    echo "  - Revisa los logs regularmente"
    echo "  - Mantén el sistema actualizado"
    echo "  - Configura alertas de Fail2Ban"
fi

echo ""
print_info "¡Gracias por usar MailCore!"
echo ""
