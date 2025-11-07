# 🚀 MailCore - Guía Completa de Despliegue en Producción

Esta guía cubre el despliegue completo de MailCore en un servidor de producción, incluyendo la configuración de todos los servicios necesarios en un entorno compartido con otros proyectos.

## 📋 Tabla de Contenidos

1. [Requisitos del Servidor](#requisitos-del-servidor)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Instalación Automática](#instalación-automática)
4. [Instalación Manual Paso a Paso](#instalación-manual-paso-a-paso)
5. [Configuración de Servicios](#configuración-de-servicios)
6. [DNS y Dominios](#dns-y-dominios)
7. [SSL/TLS](#ssltls)
8. [Seguridad y Firewall](#seguridad-y-firewall)
9. [Backups](#backups)
10. [Monitoreo](#monitoreo)
11. [Troubleshooting](#troubleshooting)

---

## 📦 Requisitos del Servidor

### Servidor Mínimo
- **OS**: Ubuntu 22.04 LTS / 24.04 LTS (recomendado)
- **RAM**: 4GB mínimo (8GB recomendado)
- **CPU**: 2 cores mínimo (4 cores recomendado)
- **Disco**: 50GB SSD mínimo
- **IP**: IPv4 estática dedicada (requerido para mail server)

### Software Requerido
```bash
# Stack Web
- Nginx 1.24+
- PHP 8.2+ con extensiones (ver lista completa abajo)
- MySQL 8.0+ / MariaDB 10.11+
- Redis 7.0+
- Supervisor 4.2+

# Stack Mail
- Postfix 3.6+
- Dovecot 2.3+
- OpenDKIM 2.11+
- OpenDMARC 1.4+
- SpamAssassin 4.0+ (opcional pero recomendado)

# Herramientas
- Git
- Composer 2.6+
- Node.js 20+ y npm
- Certbot (Let's Encrypt)
- UFW (firewall)
```

### Extensiones PHP Requeridas
```bash
php8.2-cli
php8.2-fpm
php8.2-mysql
php8.2-redis
php8.2-mbstring
php8.2-xml
php8.2-curl
php8.2-zip
php8.2-gd
php8.2-intl
php8.2-bcmath
php8.2-imap
php8.2-soap
php8.2-mailparse
```

---

## 🏗️ Arquitectura del Sistema

```
┌─────────────────────────────────────────────────────────────┐
│                    INTERNET / DNS                            │
└───────────────────────┬─────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────────┐
│                  Servidor (IP Pública)                       │
│                                                              │
│  ┌──────────────┐     ┌──────────────┐                      │
│  │   UFW        │────▶│   Nginx      │ :80, :443            │
│  │  (Firewall)  │     │  (Web Server)│                      │
│  └──────────────┘     └───────┬──────┘                      │
│                               │                              │
│                               ▼                              │
│                    ┌──────────────────┐                      │
│                    │   PHP-FPM 8.2    │                      │
│                    │  (Laravel App)   │                      │
│                    └────────┬─────────┘                      │
│                             │                                │
│         ┌───────────────────┼──────────────────┐             │
│         ▼                   ▼                  ▼             │
│  ┌─────────────┐     ┌─────────────┐   ┌─────────────┐     │
│  │   MySQL     │     │    Redis    │   │  Supervisor │     │
│  │ (Database)  │     │   (Cache)   │   │  (Queues)   │     │
│  └─────────────┘     └─────────────┘   └─────────────┘     │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐   │
│  │              MAIL STACK                              │   │
│  │                                                      │   │
│  │  ┌─────────────┐  ┌──────────┐  ┌──────────────┐   │   │
│  │  │   Postfix   │─▶│ OpenDKIM │─▶│  OpenDMARC   │   │   │
│  │  │ (SMTP Out)  │  │ (Sign)   │  │  (Validate)  │   │   │
│  │  └─────────────┘  └──────────┘  └──────────────┘   │   │
│  │         :25, :587                                   │   │
│  │                                                      │   │
│  │  ┌─────────────┐                                    │   │
│  │  │   Dovecot   │                                    │   │
│  │  │ (IMAP/POP3) │                                    │   │
│  │  └─────────────┘                                    │   │
│  │         :993, :995                                  │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## ⚡ Instalación Automática

### Opción 1: Script de Instalación Completa (Recomendado)

```bash
# 1. Conectar al servidor como root
ssh root@tu-servidor.com

# 2. Descargar el script de instalación
wget https://raw.githubusercontent.com/tu-repo/mailer/main/scripts/setup-server.sh
chmod +x setup-server.sh

# 3. Ejecutar instalación automática
./setup-server.sh

# Durante la instalación se te pedirá:
# - Dominio principal (ej: mail.tudominio.com)
# - Email del administrador
# - Contraseña de base de datos
# - Configuración de SSL (Let's Encrypt)
```

El script automáticamente:
- ✅ Instala todas las dependencias
- ✅ Configura Nginx con HTTPS
- ✅ Configura PHP-FPM optimizado
- ✅ Instala y configura MySQL/Redis
- ✅ Instala el stack completo de mail (Postfix, Dovecot, OpenDKIM, OpenDMARC)
- ✅ Configura SSL con Let's Encrypt
- ✅ Configura el firewall (UFW)
- ✅ Despliega la aplicación Laravel
- ✅ Configura Supervisor para queues
- ✅ Configura cron jobs
- ✅ Genera claves DKIM

### Opción 2: Script de Despliegue (Servidor Ya Configurado)

Si ya tienes un servidor con el stack LEMP configurado:

```bash
# 1. Clonar el proyecto
cd /var/www
git clone https://github.com/tu-repo/mailer.git mailcore
cd mailcore

# 2. Ejecutar script de despliegue
./scripts/deploy.sh production

# El script te guiará por:
# - Configuración de .env
# - Instalación de dependencias
# - Migraciones de base de datos
# - Configuración de permisos
# - Compilación de assets
```

---

## 🔧 Instalación Manual Paso a Paso

### Paso 1: Preparar el Servidor

```bash
# Actualizar el sistema
sudo apt update && sudo apt upgrade -y

# Instalar utilidades básicas
sudo apt install -y software-properties-common curl wget git unzip

# Configurar zona horaria
sudo timedatectl set-timezone Europe/Madrid  # Ajustar según tu zona
```

### Paso 2: Instalar Nginx

```bash
# Instalar Nginx
sudo apt install -y nginx

# Habilitar e iniciar
sudo systemctl enable nginx
sudo systemctl start nginx

# Verificar
sudo nginx -t
```

### Paso 3: Instalar PHP 8.2

```bash
# Añadir repositorio de PHP
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Instalar PHP y extensiones
sudo apt install -y \
    php8.2-fpm \
    php8.2-cli \
    php8.2-mysql \
    php8.2-redis \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-curl \
    php8.2-zip \
    php8.2-gd \
    php8.2-intl \
    php8.2-bcmath \
    php8.2-imap \
    php8.2-soap \
    php8.2-mailparse

# Configurar PHP-FPM
sudo sed -i 's/memory_limit = .*/memory_limit = 512M/' /etc/php/8.2/fpm/php.ini
sudo sed -i 's/upload_max_filesize = .*/upload_max_filesize = 50M/' /etc/php/8.2/fpm/php.ini
sudo sed -i 's/post_max_size = .*/post_max_size = 50M/' /etc/php/8.2/fpm/php.ini
sudo sed -i 's/max_execution_time = .*/max_execution_time = 300/' /etc/php/8.2/fpm/php.ini

# Optimizar PHP-FPM para producción
sudo tee /etc/php/8.2/fpm/pool.d/www.conf > /dev/null <<'EOF'
[www]
user = www-data
group = www-data
listen = /run/php/php8.2-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
EOF

# Reiniciar PHP-FPM
sudo systemctl restart php8.2-fpm
sudo systemctl enable php8.2-fpm
```

### Paso 4: Instalar MySQL

```bash
# Instalar MySQL
sudo apt install -y mysql-server

# Asegurar instalación
sudo mysql_secure_installation

# Configurar MySQL para Laravel
sudo mysql <<EOF
CREATE DATABASE mailcore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mailcore'@'localhost' IDENTIFIED BY 'TU_PASSWORD_SEGURA_AQUI';
GRANT ALL PRIVILEGES ON mailcore.* TO 'mailcore'@'localhost';
FLUSH PRIVILEGES;
EOF

# Optimizar MySQL para producción
sudo tee -a /etc/mysql/mysql.conf.d/mysqld.cnf > /dev/null <<'EOF'

# MailCore Optimizations
max_connections = 200
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
query_cache_type = 0
query_cache_size = 0
EOF

sudo systemctl restart mysql
```

### Paso 5: Instalar Redis

```bash
# Instalar Redis
sudo apt install -y redis-server

# Configurar Redis
sudo sed -i 's/supervised no/supervised systemd/' /etc/redis/redis.conf
sudo sed -i 's/# maxmemory <bytes>/maxmemory 256mb/' /etc/redis/redis.conf
sudo sed -i 's/# maxmemory-policy noeviction/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf

# Reiniciar Redis
sudo systemctl restart redis-server
sudo systemctl enable redis-server

# Verificar
redis-cli ping  # Debe responder: PONG
```

### Paso 6: Instalar Stack de Mail (Postfix, Dovecot, OpenDKIM, OpenDMARC)

```bash
# Instalar paquetes
sudo apt install -y postfix postfix-mysql dovecot-core dovecot-imapd \
    dovecot-pop3d dovecot-lmtpd dovecot-mysql opendkim opendkim-tools \
    opendmarc spamassassin spamc

# Durante la instalación de Postfix, seleccionar:
# - Tipo de configuración: Internet Site
# - Nombre de correo del sistema: mail.tudominio.com

# Configurar Postfix (ver sección "Configuración de Servicios" para detalles)
```

### Paso 7: Instalar Composer

```bash
# Descargar e instalar Composer
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# Verificar
composer --version
```

### Paso 8: Instalar Node.js y npm

```bash
# Instalar Node.js 20 LTS
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# Verificar
node --version
npm --version
```

### Paso 9: Clonar y Configurar MailCore

```bash
# Crear directorio para aplicaciones web
sudo mkdir -p /var/www
cd /var/www

# Clonar repositorio
sudo git clone https://github.com/tu-repo/mailer.git mailcore
cd mailcore

# Establecer permisos
sudo chown -R www-data:www-data /var/www/mailcore
sudo chmod -R 755 /var/www/mailcore
sudo chmod -R 775 /var/www/mailcore/storage
sudo chmod -R 775 /var/www/mailcore/bootstrap/cache

# Instalar dependencias PHP
sudo -u www-data composer install --optimize-autoloader --no-dev

# Instalar dependencias JavaScript
sudo -u www-data npm install
sudo -u www-data npm run build

# Copiar y configurar .env
sudo -u www-data cp .env.example .env
sudo -u www-data php artisan key:generate

# Editar .env (ver configuración abajo)
sudo -u www-data nano .env
```

**Configuración del .env en producción:**

```bash
APP_NAME=MailCore
APP_ENV=production
APP_KEY=base64:... # Generado automáticamente
APP_DEBUG=false
APP_TIMEZONE=Europe/Madrid
APP_URL=https://mail.tudominio.com

LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DEPRECATIONS_CHANNEL=null

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mailcore
DB_USERNAME=mailcore
DB_PASSWORD=TU_PASSWORD_SEGURA_AQUI

BROADCAST_DRIVER=redis
CACHE_DRIVER=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=587
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@tudominio.com
MAIL_FROM_NAME="${APP_NAME}"

# MailCore Configuration
MAILCORE_DKIM_SELECTOR=default
MAILCORE_DKIM_PATH=/var/mailcore/dkim
MAILCORE_POSTFIX_LOG_PATH=/var/log/mail.log

# Rate Limiting
MAILCORE_RATE_LIMIT_DAILY=10000
MAILCORE_RATE_LIMIT_HOURLY=1000

# Security
MAILCORE_ENABLE_SPAM_FILTER=true
MAILCORE_ENABLE_IP_REPUTATION=true
MAILCORE_ENABLE_CONTENT_FILTER=true
```

### Paso 10: Ejecutar Migraciones

```bash
cd /var/www/mailcore

# Ejecutar migraciones
sudo -u www-data php artisan migrate --force

# Ejecutar seeders (opcional, solo para testing)
# sudo -u www-data php artisan db:seed

# Crear usuario administrador
sudo -u www-data php artisan make:filament-user

# Optimizar Laravel para producción
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache
```

### Paso 11: Configurar Nginx

```bash
# Crear configuración de Nginx para MailCore
sudo tee /etc/nginx/sites-available/mailcore.conf > /dev/null <<'EOF'
# MailCore - Nginx Configuration
server {
    listen 80;
    listen [::]:80;
    server_name mail.tudominio.com;

    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name mail.tudominio.com;

    root /var/www/mailcore/public;
    index index.php index.html;

    # SSL Configuration (managed by Certbot)
    ssl_certificate /etc/letsencrypt/live/mail.tudominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/mail.tudominio.com/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Logs
    access_log /var/log/nginx/mailcore-access.log;
    error_log /var/log/nginx/mailcore-error.log;

    # Client body size
    client_max_body_size 50M;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss application/json application/javascript;

    # Location blocks
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
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

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
EOF

# Habilitar el sitio
sudo ln -s /etc/nginx/sites-available/mailcore.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default  # Remover sitio por defecto

# Verificar configuración
sudo nginx -t

# Reiniciar Nginx
sudo systemctl restart nginx
```

### Paso 12: Configurar SSL con Let's Encrypt

```bash
# Instalar Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtener certificado SSL
sudo certbot --nginx -d mail.tudominio.com

# El certificado se renovará automáticamente. Verificar:
sudo certbot renew --dry-run

# Configurar renovación automática (ya viene configurado)
sudo systemctl status certbot.timer
```

### Paso 13: Configurar Supervisor para Queues

```bash
# Crear configuración de Supervisor
sudo tee /etc/supervisor/conf.d/mailcore-worker.conf > /dev/null <<'EOF'
[program:mailcore-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/mailcore/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --timeout=300
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/mailcore/storage/logs/worker.log
stopwaitsecs=3600
EOF

# Recargar y iniciar
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start mailcore-worker:*

# Verificar estado
sudo supervisorctl status
```

### Paso 14: Configurar Cron Jobs

```bash
# Añadir cron jobs de Laravel
sudo -u www-data crontab -e

# Añadir esta línea:
* * * * * cd /var/www/mailcore && php artisan schedule:run >> /dev/null 2>&1
```

### Paso 15: Configurar Firewall (UFW)

```bash
# Habilitar UFW
sudo ufw --force enable

# Permitir conexiones SSH
sudo ufw allow 22/tcp

# Permitir HTTP/HTTPS
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Permitir puertos de mail
sudo ufw allow 25/tcp   # SMTP
sudo ufw allow 587/tcp  # SMTP Submission
sudo ufw allow 465/tcp  # SMTPS (opcional)
sudo ufw allow 993/tcp  # IMAPS
sudo ufw allow 995/tcp  # POP3S

# Verificar reglas
sudo ufw status verbose
```

---

## ⚙️ Configuración de Servicios

### Configuración de Postfix

```bash
# Backup de configuración original
sudo cp /etc/postfix/main.cf /etc/postfix/main.cf.backup

# Configurar Postfix
sudo tee /etc/postfix/main.cf > /dev/null <<'EOF'
# Hostname y dominio
myhostname = mail.tudominio.com
mydomain = tudominio.com
myorigin = $mydomain
mydestination = $myhostname, localhost.$mydomain, localhost

# Network
inet_interfaces = all
inet_protocols = ipv4

# Relay
relayhost =
mynetworks = 127.0.0.0/8 [::1]/128

# Virtual domains (gestionados por MailCore)
virtual_mailbox_domains = mysql:/etc/postfix/mysql-virtual-mailbox-domains.cf
virtual_mailbox_maps = mysql:/etc/postfix/mysql-virtual-mailbox-maps.cf
virtual_alias_maps = mysql:/etc/postfix/mysql-virtual-alias-maps.cf

# SMTP settings
smtpd_banner = $myhostname ESMTP
biff = no
append_dot_mydomain = no
readme_directory = no
compatibility_level = 3.6

# TLS parameters
smtpd_tls_cert_file=/etc/letsencrypt/live/mail.tudominio.com/fullchain.pem
smtpd_tls_key_file=/etc/letsencrypt/live/mail.tudominio.com/privkey.pem
smtpd_tls_security_level=may
smtpd_tls_session_cache_database = btree:${data_directory}/smtpd_scache
smtp_tls_session_cache_database = btree:${data_directory}/smtp_scache
smtpd_tls_protocols = !SSLv2, !SSLv3, !TLSv1, !TLSv1.1
smtpd_tls_mandatory_protocols = !SSLv2, !SSLv3, !TLSv1, !TLSv1.1
smtp_tls_protocols = !SSLv2, !SSLv3, !TLSv1, !TLSv1.1
smtp_tls_mandatory_protocols = !SSLv2, !SSLv3, !TLSv1, !TLSv1.1

# SASL Authentication
smtpd_sasl_type = dovecot
smtpd_sasl_path = private/auth
smtpd_sasl_auth_enable = yes
smtpd_sasl_security_options = noanonymous
smtpd_sasl_local_domain = $myhostname
broken_sasl_auth_clients = yes

# Anti-spam settings
smtpd_helo_required = yes
smtpd_helo_restrictions =
    permit_mynetworks,
    permit_sasl_authenticated,
    reject_invalid_helo_hostname,
    reject_non_fqdn_helo_hostname

smtpd_sender_restrictions =
    permit_mynetworks,
    permit_sasl_authenticated,
    reject_non_fqdn_sender,
    reject_unknown_sender_domain

smtpd_recipient_restrictions =
    permit_mynetworks,
    permit_sasl_authenticated,
    reject_non_fqdn_recipient,
    reject_unknown_recipient_domain,
    reject_unauth_destination,
    reject_rbl_client zen.spamhaus.org,
    reject_rbl_client bl.spamcop.net,
    reject_rbl_client cbl.abuseat.org,
    permit

smtpd_data_restrictions = reject_unauth_pipelining

# Rate limiting
smtpd_client_connection_count_limit = 10
smtpd_client_connection_rate_limit = 30
smtpd_client_message_rate_limit = 100
smtpd_client_recipient_rate_limit = 200
smtpd_client_new_tls_session_rate_limit = 10

# Size limits
message_size_limit = 52428800
mailbox_size_limit = 0

# DKIM/DMARC
milter_default_action = accept
milter_protocol = 6
smtpd_milters = inet:127.0.0.1:8891,inet:127.0.0.1:8893
non_smtpd_milters = $smtpd_milters

# Other
recipient_delimiter = +
alias_maps = hash:/etc/aliases
alias_database = hash:/etc/aliases
home_mailbox = Maildir/
EOF

# Configurar master.cf para submission
sudo tee -a /etc/postfix/master.cf > /dev/null <<'EOF'

# SMTP Submission (port 587) with authentication
submission inet n       -       y       -       -       smtpd
  -o syslog_name=postfix/submission
  -o smtpd_tls_security_level=encrypt
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_sasl_type=dovecot
  -o smtpd_sasl_path=private/auth
  -o smtpd_reject_unlisted_recipient=no
  -o smtpd_client_restrictions=permit_sasl_authenticated,reject
  -o smtpd_helo_restrictions=
  -o smtpd_sender_restrictions=
  -o smtpd_recipient_restrictions=permit_sasl_authenticated,reject
  -o milter_macro_daemon_name=ORIGINATING
EOF

# Crear archivos de consulta MySQL
sudo tee /etc/postfix/mysql-virtual-mailbox-domains.cf > /dev/null <<'EOF'
user = mailcore
password = TU_PASSWORD_MYSQL
hosts = 127.0.0.1
dbname = mailcore
query = SELECT name FROM domains WHERE name='%s' AND is_active=1
EOF

sudo tee /etc/postfix/mysql-virtual-mailbox-maps.cf > /dev/null <<'EOF'
user = mailcore
password = TU_PASSWORD_MYSQL
hosts = 127.0.0.1
dbname = mailcore
query = SELECT CONCAT(local_part, '@', d.name) FROM mailboxes m JOIN domains d ON m.domain_id=d.id WHERE CONCAT(m.local_part, '@', d.name)='%s' AND m.is_active=1
EOF

sudo tee /etc/postfix/mysql-virtual-alias-maps.cf > /dev/null <<'EOF'
user = mailcore
password = TU_PASSWORD_MYSQL
hosts = 127.0.0.1
dbname = mailcore
query = SELECT destination FROM aliases WHERE source='%s' AND is_active=1
EOF

# Proteger archivos de configuración
sudo chmod 640 /etc/postfix/mysql-*.cf
sudo chown root:postfix /etc/postfix/mysql-*.cf

# Reiniciar Postfix
sudo systemctl restart postfix
```

### Configuración de OpenDKIM

```bash
# Crear directorios
sudo mkdir -p /etc/opendkim/keys

# Configurar OpenDKIM
sudo tee /etc/opendkim.conf > /dev/null <<'EOF'
# Log
Syslog yes
SyslogSuccess yes
LogWhy yes

# Modes
Mode sv
Canonicalization relaxed/simple

# Keys
KeyTable /etc/opendkim/KeyTable
SigningTable refile:/etc/opendkim/SigningTable
ExternalIgnoreList refile:/etc/opendkim/TrustedHosts
InternalHosts refile:/etc/opendkim/TrustedHosts

# Socket
Socket inet:8891@localhost

# Other
PidFile /run/opendkim/opendkim.pid
UserID opendkim:opendkim
UMask 002
EOF

# Crear archivo de hosts confiables
sudo tee /etc/opendkim/TrustedHosts > /dev/null <<'EOF'
127.0.0.1
localhost
*.tudominio.com
EOF

# Los archivos KeyTable y SigningTable serán generados automáticamente
# por MailCore cuando se creen dominios

# Establecer permisos
sudo chown -R opendkim:opendkim /etc/opendkim
sudo chmod -R 700 /etc/opendkim/keys

# Reiniciar OpenDKIM
sudo systemctl restart opendkim
```

### Configuración de OpenDMARC

```bash
# Configurar OpenDMARC
sudo tee /etc/opendmarc.conf > /dev/null <<'EOF'
# Authentication
AuthservID mail.tudominio.com
TrustedAuthservIDs mail.tudominio.com

# Reporting
ReportingOptions v

# Socket
Socket inet:8893@localhost

# Other
PidFile /run/opendmarc/opendmarc.pid
UserID opendmarc:opendmarc
UMask 0002
Syslog true
EOF

# Reiniciar OpenDMARC
sudo systemctl restart opendmarc
```

### Configuración de Dovecot

```bash
# Configurar autenticación
sudo tee /etc/dovecot/conf.d/10-auth.conf > /dev/null <<'EOF'
disable_plaintext_auth = yes
auth_mechanisms = plain login

!include auth-sql.conf.ext
EOF

# Configurar SQL auth
sudo tee /etc/dovecot/conf.d/auth-sql.conf.ext > /dev/null <<'EOF'
passdb {
  driver = sql
  args = /etc/dovecot/dovecot-sql.conf.ext
}

userdb {
  driver = sql
  args = /etc/dovecot/dovecot-sql.conf.ext
}
EOF

# Configurar conexión SQL
sudo tee /etc/dovecot/dovecot-sql.conf.ext > /dev/null <<'EOF'
driver = mysql
connect = host=127.0.0.1 dbname=mailcore user=mailcore password=TU_PASSWORD_MYSQL
default_pass_scheme = ARGON2ID

password_query = SELECT CONCAT(m.local_part,'@',d.name) as user, m.password FROM mailboxes m JOIN domains d ON m.domain_id=d.id WHERE CONCAT(m.local_part,'@',d.name)='%u' AND m.is_active=1

user_query = SELECT CONCAT('/var/mail/vmail/',d.name,'/',m.local_part) as home, 5000 as uid, 5000 as gid FROM mailboxes m JOIN domains d ON m.domain_id=d.id WHERE CONCAT(m.local_part,'@',d.name)='%u'
EOF

# Configurar mail location
sudo tee /etc/dovecot/conf.d/10-mail.conf > /dev/null <<'EOF'
mail_location = maildir:~/Maildir
mail_privileged_group = mail
first_valid_uid = 5000
first_valid_gid = 5000
EOF

# Configurar SSL
sudo tee /etc/dovecot/conf.d/10-ssl.conf > /dev/null <<'EOF'
ssl = required
ssl_cert = </etc/letsencrypt/live/mail.tudominio.com/fullchain.pem
ssl_key = </etc/letsencrypt/live/mail.tudominio.com/privkey.pem
ssl_min_protocol = TLSv1.2
ssl_cipher_list = ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384
ssl_prefer_server_ciphers = yes
EOF

# Configurar SASL para Postfix
sudo tee /etc/dovecot/conf.d/10-master.conf > /dev/null <<'EOF'
service auth {
  unix_listener /var/spool/postfix/private/auth {
    mode = 0660
    user = postfix
    group = postfix
  }
}
EOF

# Crear usuario vmail
sudo groupadd -g 5000 vmail
sudo useradd -g vmail -u 5000 vmail -d /var/mail/vmail -m

# Establecer permisos
sudo chown -R vmail:vmail /var/mail/vmail
sudo chmod 700 /var/mail/vmail

# Reiniciar Dovecot
sudo systemctl restart dovecot
```

---

## 🌐 DNS y Dominios

Para que tu servidor de correo funcione correctamente, debes configurar los siguientes registros DNS:

### Registros DNS Requeridos

```dns
# A Record - Apuntar dominio a IP del servidor
mail.tudominio.com.    A    123.456.789.10

# MX Record - Servidor de correo
tudominio.com.         MX   10 mail.tudominio.com.

# SPF Record - Autorización de envío
tudominio.com.         TXT  "v=spf1 ip4:123.456.789.10 a:mail.tudominio.com -all"

# DMARC Record - Política de autenticación
_dmarc.tudominio.com.  TXT  "v=DMARC1; p=quarantine; rua=mailto:postmaster@tudominio.com; ruf=mailto:postmaster@tudominio.com; fo=1"

# DKIM Record - Se generará automáticamente por MailCore
# Ejemplo:
default._domainkey.tudominio.com. TXT "v=DKIM1; k=rsa; p=MIGfMA0GCS..."

# PTR Record (Reverse DNS) - Configurar con tu proveedor de hosting
10.789.456.123.in-addr.arpa. PTR mail.tudominio.com.
```

### Generar y Publicar Claves DKIM

```bash
# MailCore genera automáticamente las claves DKIM al crear un dominio
# Para generar manualmente:
cd /var/www/mailcore
sudo -u www-data php artisan mailcore:generate-dkim tudominio.com

# La clave pública se mostrará en consola para añadir al DNS
```

### Verificar DNS

```bash
# Verificar registros MX
dig MX tudominio.com +short

# Verificar SPF
dig TXT tudominio.com +short | grep spf

# Verificar DKIM
dig TXT default._domainkey.tudominio.com +short

# Verificar DMARC
dig TXT _dmarc.tudominio.com +short

# Verificar PTR (Reverse DNS)
dig -x 123.456.789.10 +short
```

---

## 🔒 SSL/TLS

### Obtener Certificados SSL

```bash
# Para el dominio principal (MailCore web)
sudo certbot --nginx -d mail.tudominio.com

# Para múltiples dominios (si tienes varios)
sudo certbot --nginx -d mail.tudominio.com -d webmail.tudominio.com
```

### Renovación Automática

Los certificados Let's Encrypt se renuevan automáticamente. Verificar:

```bash
# Test de renovación
sudo certbot renew --dry-run

# Ver timer de renovación
sudo systemctl status certbot.timer

# Logs de renovación
sudo journalctl -u certbot.timer
```

---

## 🛡️ Seguridad y Firewall

### Configuración de UFW

```bash
# Estado del firewall
sudo ufw status verbose

# Reglas básicas
sudo ufw default deny incoming
sudo ufw default allow outgoing

# SSH (cambiar 22 si usas otro puerto)
sudo ufw allow 22/tcp

# Web
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp

# Mail
sudo ufw allow 25/tcp
sudo ufw allow 587/tcp
sudo ufw allow 993/tcp
sudo ufw allow 995/tcp

# Habilitar
sudo ufw --force enable
```

### Fail2Ban (Protección contra fuerza bruta)

```bash
# Instalar Fail2Ban
sudo apt install -y fail2ban

# Configurar filtros
sudo tee /etc/fail2ban/jail.local > /dev/null <<'EOF'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true
port = 22
logpath = /var/log/auth.log

[nginx-http-auth]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log

[postfix]
enabled = true
port = smtp,465,submission
logpath = /var/log/mail.log
maxretry = 3

[dovecot]
enabled = true
port = pop3,pop3s,imap,imaps,submission
logpath = /var/log/mail.log
maxretry = 3
EOF

# Reiniciar Fail2Ban
sudo systemctl restart fail2ban
sudo systemctl enable fail2ban

# Ver estado
sudo fail2ban-client status
```

### Hardening del Sistema

```bash
# Deshabilitar root login por SSH
sudo sed -i 's/#PermitRootLogin yes/PermitRootLogin no/' /etc/ssh/sshd_config
sudo systemctl restart sshd

# Configurar actualizaciones automáticas de seguridad
sudo apt install -y unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades
```

---

## 💾 Backups

### Script de Backup Automático

```bash
# Crear script de backup
sudo tee /usr/local/bin/mailcore-backup.sh > /dev/null <<'EOF'
#!/bin/bash

# Configuration
BACKUP_DIR="/var/backups/mailcore"
RETENTION_DAYS=30
DATE=$(date +%Y%m%d_%H%M%S)
DB_USER="mailcore"
DB_PASS="TU_PASSWORD_MYSQL"
DB_NAME="mailcore"

# Create backup directory
mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Backup application files
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /var/www/mailcore \
    --exclude=/var/www/mailcore/storage/logs/* \
    --exclude=/var/www/mailcore/node_modules \
    --exclude=/var/www/mailcore/.git

# Backup DKIM keys
tar -czf $BACKUP_DIR/dkim_$DATE.tar.gz /etc/opendkim/keys

# Backup mail data
tar -czf $BACKUP_DIR/mail_$DATE.tar.gz /var/mail/vmail

# Remove old backups
find $BACKUP_DIR -name "*.gz" -type f -mtime +$RETENTION_DAYS -delete

echo "Backup completed: $DATE"
EOF

# Hacer ejecutable
sudo chmod +x /usr/local/bin/mailcore-backup.sh

# Añadir a cron (ejecutar diariamente a las 2 AM)
(sudo crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/mailcore-backup.sh >> /var/log/mailcore-backup.log 2>&1") | sudo crontab -
```

### Restaurar desde Backup

```bash
# Restaurar base de datos
gunzip < /var/backups/mailcore/db_FECHA.sql.gz | mysql -u mailcore -p mailcore

# Restaurar archivos
sudo tar -xzf /var/backups/mailcore/files_FECHA.tar.gz -C /

# Restaurar DKIM keys
sudo tar -xzf /var/backups/mailcore/dkim_FECHA.tar.gz -C /

# Restaurar mail data
sudo tar -xzf /var/backups/mailcore/mail_FECHA.tar.gz -C /

# Reiniciar servicios
sudo systemctl restart php8.2-fpm nginx postfix dovecot opendkim opendmarc
sudo supervisorctl restart all
```

---

## 📊 Monitoreo

### Verificación de Servicios

```bash
# Script de verificación de estado
sudo tee /usr/local/bin/mailcore-status.sh > /dev/null <<'EOF'
#!/bin/bash

echo "=== MailCore System Status ==="
echo ""

# Web services
echo "Web Services:"
systemctl is-active nginx | sed 's/^/  Nginx: /'
systemctl is-active php8.2-fpm | sed 's/^/  PHP-FPM: /'
echo ""

# Database
echo "Database:"
systemctl is-active mysql | sed 's/^/  MySQL: /'
systemctl is-active redis | sed 's/^/  Redis: /'
echo ""

# Mail services
echo "Mail Services:"
systemctl is-active postfix | sed 's/^/  Postfix: /'
systemctl is-active dovecot | sed 's/^/  Dovecot: /'
systemctl is-active opendkim | sed 's/^/  OpenDKIM: /'
systemctl is-active opendmarc | sed 's/^/  OpenDMARC: /'
echo ""

# Queue workers
echo "Queue Workers:"
sudo supervisorctl status mailcore-worker:* | awk '{print "  "$1": "$2}'
echo ""

# Disk usage
echo "Disk Usage:"
df -h /var/www/mailcore | tail -1 | awk '{print "  /var/www/mailcore: "$5" used"}'
df -h /var/mail/vmail | tail -1 | awk '{print "  /var/mail/vmail: "$5" used"}'
echo ""

# Recent errors
echo "Recent Errors (last hour):"
ERROR_COUNT=$(sudo grep -i error /var/log/nginx/mailcore-error.log /var/www/mailcore/storage/logs/laravel.log 2>/dev/null | grep "$(date +%Y-%m-%d)" | wc -l)
echo "  Total errors: $ERROR_COUNT"
EOF

sudo chmod +x /usr/local/bin/mailcore-status.sh

# Ejecutar
sudo /usr/local/bin/mailcore-status.sh
```

### Logs Importantes

```bash
# Logs de la aplicación
sudo tail -f /var/www/mailcore/storage/logs/laravel.log

# Logs de Nginx
sudo tail -f /var/log/nginx/mailcore-error.log
sudo tail -f /var/log/nginx/mailcore-access.log

# Logs de mail
sudo tail -f /var/log/mail.log

# Logs de workers
sudo tail -f /var/www/mailcore/storage/logs/worker.log
```

---

## 🔍 Troubleshooting

### Problemas Comunes

#### 1. Emails no se envían

```bash
# Verificar cola de Postfix
sudo mailq

# Ver logs de mail
sudo tail -50 /var/log/mail.log

# Verificar estado de Postfix
sudo systemctl status postfix

# Probar envío manual
echo "Test email" | mail -s "Test" destino@example.com
```

#### 2. Problemas de permisos

```bash
# Restaurar permisos correctos
cd /var/www/mailcore
sudo chown -R www-data:www-data .
sudo chmod -R 755 .
sudo chmod -R 775 storage bootstrap/cache
```

#### 3. Queue workers no procesan

```bash
# Ver estado de Supervisor
sudo supervisorctl status

# Reiniciar workers
sudo supervisorctl restart mailcore-worker:*

# Ver logs de workers
sudo tail -f /var/www/mailcore/storage/logs/worker.log
```

#### 4. Emails marcados como spam

```bash
# Verificar SPF
dig TXT tudominio.com +short

# Verificar DKIM
dig TXT default._domainkey.tudominio.com +short

# Verificar DMARC
dig TXT _dmarc.tudominio.com +short

# Test de score de spam
https://www.mail-tester.com/
```

#### 5. SSL/TLS issues

```bash
# Verificar certificados
sudo certbot certificates

# Renovar certificados
sudo certbot renew --force-renewal

# Test SSL
openssl s_client -connect mail.tudominio.com:443 -servername mail.tudominio.com
```

### Comandos Útiles

```bash
# Reiniciar todos los servicios de MailCore
sudo systemctl restart nginx php8.2-fpm mysql redis postfix dovecot opendkim opendmarc
sudo supervisorctl restart all

# Limpiar caché de Laravel
cd /var/www/mailcore
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan route:clear
sudo -u www-data php artisan view:clear

# Ver uso de recursos
htop

# Ver conexiones activas
sudo netstat -tulpn | grep -E ':(80|443|25|587|993|995)'

# Verificar base de datos
sudo mysql -u mailcore -p mailcore -e "SHOW TABLES;"

# Test de conectividad SMTP
telnet mail.tudominio.com 587
```

---

## 📝 Post-Deployment Checklist

Después del despliegue, verifica:

- [ ] Todos los servicios están corriendo
- [ ] Nginx responde en HTTP y HTTPS
- [ ] SSL está correctamente configurado
- [ ] Panel de administración accesible en https://mail.tudominio.com/admin
- [ ] Base de datos conectada correctamente
- [ ] Redis funcionando
- [ ] Queue workers procesando jobs
- [ ] Cron jobs configurados
- [ ] Postfix enviando emails
- [ ] Dovecot recibiendo emails
- [ ] DKIM firmando emails
- [ ] SPF, DKIM y DMARC configurados en DNS
- [ ] Firewall configurado correctamente
- [ ] Fail2Ban activo
- [ ] Backups automatizados configurados
- [ ] Logs rotando correctamente
- [ ] Monitoreo funcionando

---

## 🆘 Soporte

Si encuentras problemas durante el despliegue:

1. Revisa los logs mencionados en este documento
2. Ejecuta el script de verificación: `sudo /usr/local/bin/mailcore-status.sh`
3. Consulta la documentación de TROUBLESHOOTING.md
4. Revisa el README.md del proyecto

---

## 📚 Referencias

- [Laravel Deployment Documentation](https://laravel.com/docs/deployment)
- [Postfix Documentation](http://www.postfix.org/documentation.html)
- [Dovecot Wiki](https://wiki.dovecot.org/)
- [OpenDKIM Documentation](http://www.opendkim.org/)
- [Let's Encrypt Documentation](https://letsencrypt.org/docs/)
- [Nginx Documentation](https://nginx.org/en/docs/)

---

**Última actualización**: 2024
**Versión**: 1.0
