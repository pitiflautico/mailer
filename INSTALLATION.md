# MailCore - Guía de Instalación

## 📋 Requisitos del Sistema

### Servidor
- **OS**: Ubuntu 22.04+ LTS
- **CPU**: 4 vCPU mínimo
- **RAM**: 8GB mínimo
- **Disco**: 50GB SSD
- **IP**: Dedicada con PTR configurado
- **Acceso**: Root SSH

### DNS
- Dominio registrado
- Acceso a gestión DNS (Cloudflare, GoDaddy, DigitalOcean, etc.)
- PTR record configurado: `mail.tudominio.com` → `TU_IP`

## 🚀 Instalación Automática

### 1. Clonar Repositorio

```bash
cd /var/www
git clone https://github.com/tuusuario/mailcore.git
cd mailcore
```

### 2. Ejecutar Script de Instalación

```bash
sudo bash install_mailcore.sh
```

El script te pedirá:
- Dominio (ej: ejemplo.com)
- Hostname de correo (ej: mail.ejemplo.com)
- IP dedicada
- Email del administrador

### 3. Configurar Laravel

```bash
# Instalar dependencias
composer install --no-dev --optimize-autoloader

# Copiar archivo de entorno
cp .env.example .env

# Editar configuración
nano .env
```

Configurar en `.env`:
```env
APP_URL=https://mail.tudominio.com

DB_DATABASE=mailcore
DB_USERNAME=mailcore
DB_PASSWORD=tu_password_seguro

MAILCORE_DOMAIN=tudominio.com
MAILCORE_HOSTNAME=mail.tudominio.com
MAILCORE_IP=TU_IP_DEDICADA
```

### 4. Inicializar Base de Datos

```bash
# Crear base de datos MySQL
mysql -u root -p

CREATE DATABASE mailcore;
CREATE USER 'mailcore'@'localhost' IDENTIFIED BY 'tu_password_seguro';
GRANT ALL PRIVILEGES ON mailcore.* TO 'mailcore'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Ejecutar migraciones
php artisan migrate

# Crear usuario administrador
php artisan make:filament-user
```

### 5. Configurar Nginx

```bash
nano /etc/nginx/sites-available/mailcore
```

```nginx
server {
    listen 443 ssl http2;
    server_name mail.tudominio.com;

    ssl_certificate /etc/letsencrypt/live/mail.tudominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/mail.tudominio.com/privkey.pem;

    root /var/www/mailcore/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}

server {
    listen 80;
    server_name mail.tudominio.com;
    return 301 https://$server_name$request_uri;
}
```

```bash
ln -s /etc/nginx/sites-available/mailcore /etc/nginx/sites-enabled/
nginx -t
systemctl restart nginx
```

### 6. Configurar Permisos

```bash
chown -R www-data:www-data /var/www/mailcore
chmod -R 755 /var/www/mailcore
chmod -R 775 /var/www/mailcore/storage
chmod -R 775 /var/www/mailcore/bootstrap/cache
```

### 7. Configurar Cron

```bash
crontab -e
```

Agregar:
```cron
* * * * * cd /var/www/mailcore && php artisan schedule:run >> /dev/null 2>&1
```

### 8. Configurar Queue Worker

```bash
nano /etc/supervisor/conf.d/mailcore-worker.conf
```

```ini
[program:mailcore-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/mailcore/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/mailcore/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start mailcore-worker:*
```

## 🔐 Configuración DNS

### Registros Requeridos

| Tipo | Nombre | Valor | Prioridad | TTL |
|------|--------|-------|-----------|-----|
| A | mail | TU_IP | - | 3600 |
| MX | @ | mail.tudominio.com | 10 | 3600 |
| TXT | @ | v=spf1 a mx ip4:TU_IP -all | - | 3600 |
| TXT | _dmarc | v=DMARC1; p=none; rua=mailto:dmarc@tudominio.com | - | 3600 |
| TXT | default._domainkey | (generado automáticamente) | - | 3600 |

### Generar Claves DKIM

```bash
php artisan mailcore:generate-dkim tudominio.com
```

El comando mostrará el registro TXT que debes agregar a tu DNS.

### Verificar DNS

```bash
php artisan mailcore:verify-domains
```

Espera 10-30 minutos para que los registros DNS se propaguen.

## ✅ Verificación Post-Instalación

### 1. Verificar Servicios

```bash
systemctl status postfix
systemctl status dovecot
systemctl status opendkim
systemctl status opendmarc
systemctl status nginx
systemctl status php8.2-fpm
```

### 2. Verificar Logs

```bash
tail -f /var/log/mail.log
tail -f /var/www/mailcore/storage/logs/laravel.log
```

### 3. Probar Envío de Correo

```bash
echo "Prueba MailCore" | mail -s "Test" tuemail@gmail.com
```

### 4. Acceder al Panel

Navega a: `https://mail.tudominio.com/admin`

Inicia sesión con el usuario creado anteriormente.

## 📊 Configuración Inicial en el Panel

1. **Agregar Dominio**
   - Ve a Dominios → Crear
   - Ingresa tu dominio
   - El sistema generará las claves DKIM automáticamente

2. **Verificar Dominio**
   - Click en "Verificar DNS"
   - Asegúrate de que SPF, DKIM y DMARC estén verificados

3. **Crear Buzón**
   - Ve a Buzones → Crear
   - Selecciona el dominio
   - Ingresa usuario y contraseña
   - Configura cuota y límites

4. **Probar Envío**
   - Ve a la pestaña de API o usa un cliente SMTP
   - Envía un correo de prueba

## 🔧 Comandos Útiles

```bash
# Parsear logs de Postfix
php artisan mailcore:parse-logs

# Verificar dominios
php artisan mailcore:verify-domains

# Verificar rebotes
php artisan mailcore:check-bounces

# Limpiar logs antiguos
php artisan mailcore:cleanup-old-logs --days=90

# Ver estado del sistema
php artisan queue:work --once
```

## 🐛 Solución de Problemas

### Error: "Permission denied" en storage

```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Postfix no inicia

```bash
postfix check
tail -f /var/log/mail.err
```

### DKIM no verifica

```bash
opendkim-testkey -d tudominio.com -s default -vvv
```

### Correos van a spam

1. Verifica PTR record
2. Verifica SPF, DKIM, DMARC en https://www.mail-tester.com
3. Revisa reputación de IP en https://mxtoolbox.com/blacklists.aspx

## 📞 Soporte

Para más información, revisa:
- README.md
- CONFIGURATION.md
- API.md
