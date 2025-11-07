#!/bin/bash

# ============================================================
# MAILCORE - Setup Local Rápido
# ============================================================

set -e

echo "🚀 MailCore - Setup Local"
echo "=========================="
echo ""

# Función para preguntar sí/no
ask_yes_no() {
    while true; do
        read -p "$1 (s/n): " yn
        case $yn in
            [Ss]* ) return 0;;
            [Nn]* ) return 1;;
            * ) echo "Por favor responde s o n.";;
        esac
    done
}

# 1. Instalar dependencias
echo "📦 [1/7] Instalando dependencias..."
if [ ! -d "vendor" ]; then
    composer install
else
    echo "   ✓ Dependencias ya instaladas"
fi

# 2. Configurar entorno
echo ""
echo "⚙️  [2/7] Configurando entorno..."
if [ ! -f ".env" ]; then
    cp .env.example .env
    echo "   ✓ Archivo .env creado"

    # Configurar para SQLite por defecto
    if ask_yes_no "¿Usar SQLite (más simple)?"; then
        sed -i 's/DB_CONNECTION=mysql/DB_CONNECTION=sqlite/' .env
        sed -i 's|DB_DATABASE=mailcore|DB_DATABASE='$(pwd)'/database/database.sqlite|' .env
        echo "   ✓ Configurado para SQLite"
    fi

    # Modo sandbox
    echo "MAILCORE_SANDBOX_MODE=true" >> .env
    echo "MAILCORE_LOG_PARSER_ENABLED=false" >> .env
else
    echo "   ✓ .env ya existe"
fi

# 3. Generar key
echo ""
echo "🔑 [3/7] Generando application key..."
if ! grep -q "APP_KEY=base64:" .env; then
    php artisan key:generate
else
    echo "   ✓ Key ya generada"
fi

# 4. Crear base de datos SQLite si es necesario
if grep -q "DB_CONNECTION=sqlite" .env; then
    echo ""
    echo "🗄️  [4/7] Creando base de datos SQLite..."
    if [ ! -f "database/database.sqlite" ]; then
        touch database/database.sqlite
        echo "   ✓ Base de datos creada"
    else
        echo "   ✓ Base de datos ya existe"
    fi
else
    echo ""
    echo "🗄️  [4/7] Usando MySQL..."
    echo "   ℹ️  Asegúrate de tener MySQL corriendo y crear la DB manualmente"
fi

# 5. Migrar base de datos
echo ""
echo "📊 [5/7] Ejecutando migraciones..."
php artisan migrate

# 6. Seedear datos de prueba
echo ""
if ask_yes_no "¿Quieres agregar datos de prueba?"; then
    echo "🌱 [6/7] Seeding datos de prueba..."
    php artisan db:seed
    echo ""
    echo "   ✅ Datos creados:"
    echo "      - 2 dominios (ejemplo.com, test.com)"
    echo "      - 2 buzones (noreply@ejemplo.com, info@ejemplo.com)"
    echo "      - 55 logs de envío"
    echo "      - Password de buzones: password123"
else
    echo ""
    echo "⏭️  [6/7] Saltando datos de prueba..."
fi

# 7. Crear usuario admin
echo ""
if ask_yes_no "¿Crear usuario administrador?"; then
    echo ""
    echo "👤 [7/7] Creando usuario administrador..."
    php artisan make:filament-user
else
    echo ""
    echo "⏭️  [7/7] Puedes crear el usuario después con: php artisan make:filament-user"
fi

echo ""
echo "✅ ¡Setup completado!"
echo ""
echo "📍 Para iniciar el servidor:"
echo "   php artisan serve"
echo ""
echo "🌐 Luego accede a:"
echo "   http://localhost:8000/admin"
echo ""
echo "📖 Ver guía completa: cat TESTING.md"
echo ""

# Preguntar si quiere iniciar servidor
if ask_yes_no "¿Iniciar servidor ahora?"; then
    echo ""
    echo "🚀 Iniciando servidor..."
    echo "   Presiona Ctrl+C para detener"
    echo ""
    php artisan serve
fi
