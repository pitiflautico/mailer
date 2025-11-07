#!/bin/bash
set -e

echo "🚀 MailCore - Iniciando..."

# Esperar a que MySQL esté listo
echo "⏳ Esperando MySQL..."
until mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" -e "SELECT 1" &> /dev/null
do
    echo "MySQL no está listo - esperando..."
    sleep 2
done
echo "✅ MySQL está listo!"

# Instalar dependencias si no existen
if [ ! -d "vendor" ]; then
    echo "📦 Instalando dependencias de Composer..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Generar key si no existe
if ! grep -q "APP_KEY=base64:" .env; then
    echo "🔑 Generando application key..."
    php artisan key:generate
fi

# Ejecutar migraciones
echo "🗄️ Ejecutando migraciones..."
php artisan migrate --force

# Seedear si la DB está vacía
DOMAIN_COUNT=$(php artisan tinker --execute="echo App\Models\Domain::count();")
if [ "$DOMAIN_COUNT" -eq "0" ]; then
    echo "🌱 Seeding base de datos..."
    php artisan db:seed --force
fi

# Limpiar caché
echo "🧹 Limpiando caché..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✅ MailCore iniciado correctamente!"
echo ""
echo "📍 Accesos:"
echo "   - Panel Admin: http://localhost:8000/admin"
echo "   - API: http://localhost:8000/api"
echo "   - Mailpit: http://localhost:8025"
echo "   - phpMyAdmin: http://localhost:8080"
echo ""

# Iniciar servidor
exec php artisan serve --host=0.0.0.0 --port=8000
