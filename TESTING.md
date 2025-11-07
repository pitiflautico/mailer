# MailCore - Guía de Testing Local

## 🚀 Opción 1: Testing Rápido (Panel Filament + Modo Sandbox)

La forma más rápida de probar el proyecto localmente sin configurar servidores de correo.

### Paso 1: Instalar Dependencias

```bash
# Clonar proyecto
git clone https://github.com/tuusuario/mailcore.git
cd mailcore

# Instalar dependencias
composer install
npm install
```

### Paso 2: Configurar Base de Datos Local

**Opción A: SQLite (más simple)**

```bash
# Crear archivo de base de datos
touch database/database.sqlite

# Configurar .env
cp .env.example .env
```

Editar `.env`:
```env
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database/database.sqlite

# Activar modo sandbox (no envía correos reales)
MAILCORE_SANDBOX_MODE=true

# Desactivar verificaciones
MAILCORE_LOG_PARSER_ENABLED=false
```

**Opción B: MySQL/MariaDB local**

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mailcore_dev
DB_USERNAME=root
DB_PASSWORD=

MAILCORE_SANDBOX_MODE=true
MAILCORE_LOG_PARSER_ENABLED=false
```

### Paso 3: Inicializar

```bash
# Generar clave
php artisan key:generate

# Migrar base de datos
php artisan migrate

# Crear seeders con datos de prueba (ver más abajo)
php artisan db:seed

# Crear usuario admin
php artisan make:filament-user
```

### Paso 4: Levantar Servidor

```bash
# Terminal 1: Laravel
php artisan serve

# Terminal 2: Vite (assets)
npm run dev
```

Accede a: `http://localhost:8000/admin`

---

## 🐳 Opción 2: Docker Compose (Completo)

Incluye MySQL, Mailpit (visor de correos), Redis, etc.

### docker-compose.yml

```yaml
version: '3.8'

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "8000:8000"
    volumes:
      - .:/var/www/html
    environment:
      - DB_HOST=mysql
      - DB_DATABASE=mailcore
      - DB_USERNAME=mailcore
      - DB_PASSWORD=secret
      - REDIS_HOST=redis
      - MAIL_HOST=mailpit
      - MAILCORE_SANDBOX_MODE=false
    depends_on:
      - mysql
      - redis
      - mailpit

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: mailcore
      MYSQL_USER: mailcore
      MYSQL_PASSWORD: secret
      MYSQL_ROOT_PASSWORD: root
    ports:
      - "3306:3306"
    volumes:
      - mysql_data:/var/lib/mysql

  redis:
    image: redis:alpine
    ports:
      - "6379:6379"

  mailpit:
    image: axllent/mailpit
    ports:
      - "8025:8025"  # Web UI
      - "1025:1025"  # SMTP
    environment:
      MP_SMTP_AUTH_ACCEPT_ANY: 1
      MP_SMTP_AUTH_ALLOW_INSECURE: 1

volumes:
  mysql_data:
```

### Dockerfile

```dockerfile
FROM php:8.2-fpm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . .

RUN composer install --no-dev --optimize-autoloader

CMD php artisan serve --host=0.0.0.0 --port=8000
```

### Usar Docker

```bash
# Levantar servicios
docker-compose up -d

# Inicializar
docker-compose exec app php artisan key:generate
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
docker-compose exec app php artisan make:filament-user

# Ver logs
docker-compose logs -f app
```

Acceder a:
- **Panel**: http://localhost:8000/admin
- **Mailpit** (ver correos): http://localhost:8025

---

## 📧 Opción 3: MailHog / Mailtrap

Captura correos sin enviarlos realmente.

### Con MailHog (Docker)

```bash
# Levantar MailHog
docker run -d -p 1025:1025 -p 8025:8025 mailhog/mailhog
```

Configurar `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null

MAILCORE_SANDBOX_MODE=false
```

Ver correos en: http://localhost:8025

### Con Mailtrap (Cloud)

1. Crear cuenta en https://mailtrap.io
2. Obtener credenciales SMTP
3. Configurar `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=tu-username
MAIL_PASSWORD=tu-password
MAIL_ENCRYPTION=tls

MAILCORE_SANDBOX_MODE=false
```

---

## 🌱 Seeders para Datos de Prueba

Crea datos de prueba automáticamente.

### database/seeders/DatabaseSeeder.php

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DomainSeeder::class,
            MailboxSeeder::class,
            SendLogSeeder::class,
        ]);
    }
}
```

### database/seeders/DomainSeeder.php

```php
<?php

namespace Database\Seeders;

use App\Models\Domain;
use Illuminate\Database\Seeder;

class DomainSeeder extends Seeder
{
    public function run(): void
    {
        Domain::create([
            'name' => 'ejemplo.com',
            'dkim_selector' => 'default',
            'dkim_public_key' => 'v=DKIM1; k=rsa; p=MIIBIjANBgkq...',
            'spf_verified' => true,
            'dkim_verified' => true,
            'dmarc_verified' => true,
            'is_active' => true,
            'verified_at' => now(),
        ]);

        Domain::create([
            'name' => 'test.com',
            'dkim_selector' => 'default',
            'is_active' => true,
        ]);
    }
}
```

### database/seeders/MailboxSeeder.php

```php
<?php

namespace Database\Seeders;

use App\Models\Domain;
use App\Models\Mailbox;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MailboxSeeder extends Seeder
{
    public function run(): void
    {
        $domain = Domain::where('name', 'ejemplo.com')->first();

        if ($domain) {
            Mailbox::create([
                'domain_id' => $domain->id,
                'local_part' => 'noreply',
                'email' => 'noreply@ejemplo.com',
                'password' => Hash::make('password123'),
                'quota_mb' => 1024,
                'used_mb' => 150,
                'is_active' => true,
                'can_send' => true,
                'can_receive' => true,
                'daily_send_limit' => 1000,
                'daily_send_count' => 45,
            ]);

            Mailbox::create([
                'domain_id' => $domain->id,
                'local_part' => 'info',
                'email' => 'info@ejemplo.com',
                'password' => Hash::make('password123'),
                'quota_mb' => 2048,
                'used_mb' => 500,
                'is_active' => true,
            ]);
        }
    }
}
```

### database/seeders/SendLogSeeder.php

```php
<?php

namespace Database\Seeders;

use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\SendLog;
use Illuminate\Database\Seeder;

class SendLogSeeder extends Seeder
{
    public function run(): void
    {
        $domain = Domain::first();
        $mailbox = Mailbox::first();

        if ($domain && $mailbox) {
            // Logs exitosos
            for ($i = 0; $i < 50; $i++) {
                SendLog::create([
                    'domain_id' => $domain->id,
                    'mailbox_id' => $mailbox->id,
                    'message_id' => 'test-' . uniqid() . '@ejemplo.com',
                    'from_email' => $mailbox->email,
                    'to_email' => 'usuario' . $i . '@test.com',
                    'subject' => 'Email de prueba ' . $i,
                    'body_preview' => 'Este es un correo de prueba...',
                    'status' => 'delivered',
                    'smtp_code' => 250,
                    'smtp_response' => 'OK',
                    'sent_at' => now()->subDays(rand(0, 30)),
                    'delivered_at' => now()->subDays(rand(0, 30)),
                ]);
            }

            // Algunos rebotes
            for ($i = 0; $i < 5; $i++) {
                SendLog::create([
                    'domain_id' => $domain->id,
                    'mailbox_id' => $mailbox->id,
                    'message_id' => 'bounce-' . uniqid() . '@ejemplo.com',
                    'from_email' => $mailbox->email,
                    'to_email' => 'invalid' . $i . '@test.com',
                    'subject' => 'Email rebotado ' . $i,
                    'status' => 'bounced',
                    'smtp_code' => 550,
                    'smtp_response' => 'User not found',
                    'bounced_at' => now()->subDays(rand(0, 10)),
                ]);
            }
        }
    }
}
```

### Ejecutar Seeders

```bash
php artisan db:seed
```

---

## 🧪 Tests Unitarios

### Crear Tests

```bash
php artisan make:test DomainTest
php artisan make:test MailboxTest
php artisan make:test SendLogTest
```

### tests/Feature/DomainTest.php

```php
<?php

namespace Tests\Feature;

use App\Models\Domain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_domain(): void
    {
        $domain = Domain::create([
            'name' => 'test.com',
            'dkim_selector' => 'default',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('domains', [
            'name' => 'test.com',
        ]);
    }

    public function test_domain_verification_percentage(): void
    {
        $domain = Domain::create([
            'name' => 'test.com',
            'spf_verified' => true,
            'dkim_verified' => true,
            'dmarc_verified' => false,
        ]);

        $this->assertEquals(66, $domain->getVerificationPercentage());
    }
}
```

### Ejecutar Tests

```bash
# Todos los tests
php artisan test

# Test específico
php artisan test --filter=DomainTest
```

---

## 🎯 Testing del API

### Con cURL

```bash
# Crear token primero en el panel

# Test health check
curl http://localhost:8000/api/health

# Test envío (modo sandbox)
curl -X POST http://localhost:8000/api/send \
  -H "Authorization: Bearer tu-token" \
  -H "Content-Type: application/json" \
  -d '{
    "from": "noreply@ejemplo.com",
    "to": "test@test.com",
    "subject": "Test Local",
    "body": "Este es un test local"
  }'
```

### Con Postman

1. Importar colección
2. Configurar variables:
   - `base_url`: http://localhost:8000
   - `token`: tu-token-api

---

## 📋 Checklist de Testing Local

### ✅ Panel Filament
- [ ] Login funciona
- [ ] Dashboard muestra estadísticas
- [ ] Crear dominio
- [ ] Crear buzón
- [ ] Ver logs de envíos
- [ ] Filtros funcionan

### ✅ API
- [ ] Health check responde
- [ ] Envío simple funciona
- [ ] Envío bulk funciona
- [ ] Validación de errores

### ✅ Servicios
- [ ] DkimService genera claves (aunque no sean verificables localmente)
- [ ] MailService envía correos (modo sandbox)
- [ ] Comandos Artisan se ejecutan sin error

---

## 🐛 Troubleshooting Local

### Error: "No application encryption key"
```bash
php artisan key:generate
```

### Error: "SQLSTATE[HY000] [1049] Unknown database"
```bash
# Crear base de datos
mysql -u root -p
CREATE DATABASE mailcore_dev;
```

### Error: "Class 'Filament\...' not found"
```bash
composer install
php artisan filament:upgrade
```

### Assets no cargan
```bash
npm install
npm run dev
```

---

## 💡 Recomendaciones

1. **Modo Sandbox**: Siempre activado en local (`MAILCORE_SANDBOX_MODE=true`)
2. **Seeders**: Usa datos de prueba para ver el panel con contenido
3. **MailHog/Mailpit**: Para ver correos sin enviar realmente
4. **SQLite**: Más simple para desarrollo rápido
5. **Docker**: Si quieres un entorno más completo

---

## 🎓 Flujo Recomendado de Testing

```bash
# 1. Setup inicial
composer install && npm install
cp .env.example .env
php artisan key:generate

# 2. Base de datos
touch database/database.sqlite
php artisan migrate

# 3. Datos de prueba
php artisan db:seed

# 4. Usuario admin
php artisan make:filament-user

# 5. Levantar servidor
php artisan serve &
npm run dev &

# 6. Abrir navegador
open http://localhost:8000/admin
```

---

Listo para testear localmente sin necesidad de servidor de correo real!
