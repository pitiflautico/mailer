#!/bin/bash

################################################################################
# MailCore - Script de Instalación Completa del Servidor
#
# Este script instala y configura todos los servicios necesarios para
# ejecutar MailCore en un servidor de producción compartido.
#
# IMPORTANTE: Ejecutar como root o con sudo
#
# Uso: sudo ./setup-server.sh
################################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored messages
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

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print_error "Este script debe ejecutarse como root o con sudo"
    exit 1
fi

print_step "MAILCORE - INSTALACIÓN COMPLETA DEL SERVIDOR"

# Get configuration from user
print_info "Por favor, proporciona la siguiente información:"
echo ""

read -p "Dominio principal para MailCore (ej: mail.tudominio.com): " DOMAIN
read -p "Email del administrador: " ADMIN_EMAIL
read -sp "Contraseña para la base de datos MySQL: " DB_PASSWORD
echo ""
read -p "¿Instalar certificado SSL con Let's Encrypt? (s/n): " INSTALL_SSL
read -p "Zona horaria (ej: Europe/Madrid): " TIMEZONE

# Validate inputs
if [ -z "$DOMAIN" ] || [ -z "$ADMIN_EMAIL" ] || [ -z "$DB_PASSWORD" ]; then
    print_error "Todos los campos son obligatorios"
    exit 1
fi

# Confirm configuration
echo ""
print_warning "CONFIGURACIÓN:"
echo "  Dominio: $DOMAIN"
echo "  Email Admin: $ADMIN_EMAIL"
echo "  Zona Horaria: $TIMEZONE"
echo "  SSL: $INSTALL_SSL"
echo ""
read -p "¿Continuar con la instalación? (s/n): " CONFIRM

if [ "$CONFIRM" != "s" ] && [ "$CONFIRM" != "S" ]; then
    print_error "Instalación cancelada"
    exit 1
fi

################################################################################
# STEP 1: Update System
################################################################################

print_step "PASO 1: ACTUALIZAR SISTEMA"

print_info "Actualizando lista de paquetes..."
apt update

print_info "Actualizando paquetes instalados..."
apt upgrade -y

print_info "Instalando utilidades básicas..."
apt install -y software-properties-common curl wget git unzip zip htop \
    net-tools dnsutils build-essential

print_success "Sistema actualizado"

################################################################################
# STEP 2: Configure Timezone
################################################################################

print_step "PASO 2: CONFIGURAR ZONA HORARIA"

timedatectl set-timezone $TIMEZONE
print_success "Zona horaria configurada: $TIMEZONE"

################################################################################
# STEP 3: Install Nginx
################################################################################

print_step "PASO 3: INSTALAR NGINX"

print_info "Instalando Nginx..."
apt install -y nginx

print_info "Habilitando e iniciando Nginx..."
systemctl enable nginx
systemctl start nginx

print_success "Nginx instalado y ejecutándose"

################################################################################
# STEP 4: Install PHP 8.2
################################################################################

print_step "PASO 4: INSTALAR PHP 8.2"

print_info "Añadiendo repositorio de PHP..."
add-apt-repository ppa:ondrej/php -y
apt update

print_info "Instalando PHP 8.2 y extensiones..."
apt install -y \
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

print_info "Optimizando configuración de PHP..."
sed -i 's/memory_limit = .*/memory_limit = 512M/' /etc/php/8.2/fpm/php.ini
sed -i 's/upload_max_filesize = .*/upload_max_filesize = 50M/' /etc/php/8.2/fpm/php.ini
sed -i 's/post_max_size = .*/post_max_size = 50M/' /etc/php/8.2/fpm/php.ini
sed -i 's/max_execution_time = .*/max_execution_time = 300/' /etc/php/8.2/fpm/php.ini

print_info "Configurando PHP-FPM pool..."
cat > /etc/php/8.2/fpm/pool.d/www.conf <<'EOF'
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

systemctl restart php8.2-fpm
systemctl enable php8.2-fpm

print_success "PHP 8.2 instalado y configurado"

################################################################################
# STEP 5: Install MySQL
################################################################################

print_step "PASO 5: INSTALAR MYSQL"

print_info "Instalando MySQL Server..."
export DEBIAN_FRONTEND=noninteractive
apt install -y mysql-server

print_info "Configurando MySQL..."

# Secure installation
mysql <<EOF
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '$DB_PASSWORD';
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
FLUSH PRIVILEGES;
EOF

print_info "Creando base de datos y usuario para MailCore..."
mysql -u root -p"$DB_PASSWORD" <<EOF
CREATE DATABASE mailcore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'mailcore'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON mailcore.* TO 'mailcore'@'localhost';
FLUSH PRIVILEGES;
EOF

print_info "Optimizando MySQL..."
cat >> /etc/mysql/mysql.conf.d/mysqld.cnf <<'EOF'

# MailCore Optimizations
max_connections = 200
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT
query_cache_type = 0
query_cache_size = 0
EOF

systemctl restart mysql
systemctl enable mysql

print_success "MySQL instalado y configurado"

################################################################################
# STEP 6: Install Redis
################################################################################

print_step "PASO 6: INSTALAR REDIS"

print_info "Instalando Redis..."
apt install -y redis-server

print_info "Configurando Redis..."
sed -i 's/supervised no/supervised systemd/' /etc/redis/redis.conf
sed -i 's/# maxmemory <bytes>/maxmemory 256mb/' /etc/redis/redis.conf
sed -i 's/# maxmemory-policy noeviction/maxmemory-policy allkeys-lru/' /etc/redis/redis.conf

systemctl restart redis-server
systemctl enable redis-server

# Verify Redis
if redis-cli ping | grep -q PONG; then
    print_success "Redis instalado y funcionando"
else
    print_error "Redis no responde correctamente"
    exit 1
fi

################################################################################
# STEP 7: Install Mail Stack
################################################################################

print_step "PASO 7: INSTALAR STACK DE MAIL"

print_info "Pre-configurando Postfix..."
debconf-set-selections <<< "postfix postfix/mailname string $DOMAIN"
debconf-set-selections <<< "postfix postfix/main_mailer_type string 'Internet Site'"

print_info "Instalando Postfix, Dovecot, OpenDKIM, OpenDMARC..."
apt install -y postfix postfix-mysql dovecot-core dovecot-imapd \
    dovecot-pop3d dovecot-lmtpd dovecot-mysql opendkim opendkim-tools \
    opendmarc spamassassin spamc

print_success "Stack de mail instalado"

################################################################################
# STEP 8: Install Composer
################################################################################

print_step "PASO 8: INSTALAR COMPOSER"

print_info "Descargando Composer..."
curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

composer --version
print_success "Composer instalado"

################################################################################
# STEP 9: Install Node.js
################################################################################

print_step "PASO 9: INSTALAR NODE.JS"

print_info "Instalando Node.js 20 LTS..."
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

node --version
npm --version
print_success "Node.js instalado"

################################################################################
# STEP 10: Install Supervisor
################################################################################

print_step "PASO 10: INSTALAR SUPERVISOR"

print_info "Instalando Supervisor..."
apt install -y supervisor

systemctl enable supervisor
systemctl start supervisor

print_success "Supervisor instalado"

################################################################################
# STEP 11: Configure Firewall
################################################################################

print_step "PASO 11: CONFIGURAR FIREWALL"

print_info "Instalando UFW..."
apt install -y ufw

print_info "Configurando reglas de firewall..."
ufw --force disable  # Disable first to avoid lock-out

# Allow SSH
ufw allow 22/tcp

# Allow HTTP/HTTPS
ufw allow 80/tcp
ufw allow 443/tcp

# Allow mail ports
ufw allow 25/tcp   # SMTP
ufw allow 587/tcp  # SMTP Submission
ufw allow 465/tcp  # SMTPS (optional)
ufw allow 993/tcp  # IMAPS
ufw allow 995/tcp  # POP3S

ufw --force enable

print_success "Firewall configurado"

################################################################################
# STEP 12: Install Fail2Ban
################################################################################

print_step "PASO 12: INSTALAR FAIL2BAN"

print_info "Instalando Fail2Ban..."
apt install -y fail2ban

print_info "Configurando Fail2Ban..."
cat > /etc/fail2ban/jail.local <<'EOF'
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

systemctl restart fail2ban
systemctl enable fail2ban

print_success "Fail2Ban configurado"

################################################################################
# STEP 13: Clone MailCore
################################################################################

print_step "PASO 13: CLONAR MAILCORE"

print_info "Clonando repositorio de MailCore..."
cd /var/www

# Si ya existe, hacer backup
if [ -d "mailcore" ]; then
    print_warning "Directorio mailcore ya existe, creando backup..."
    mv mailcore mailcore.backup.$(date +%Y%m%d_%H%M%S)
fi

# Clone from GitHub (cambiar URL por tu repositorio)
print_warning "NOTA: Debes cambiar la URL del repositorio"
# git clone https://github.com/tu-usuario/mailcore.git mailcore

# Por ahora, crear estructura
mkdir -p mailcore
print_warning "Debes clonar tu repositorio en /var/www/mailcore"

print_success "Directorio de MailCore preparado"

################################################################################
# STEP 14: Install SSL Certificate
################################################################################

if [ "$INSTALL_SSL" == "s" ] || [ "$INSTALL_SSL" == "S" ]; then
    print_step "PASO 14: INSTALAR CERTIFICADO SSL"

    print_info "Instalando Certbot..."
    apt install -y certbot python3-certbot-nginx

    print_info "Obteniendo certificado SSL para $DOMAIN..."
    print_warning "Asegúrate de que el dominio $DOMAIN apunta a este servidor"

    read -p "¿El dominio ya apunta a este servidor? (s/n): " DNS_READY

    if [ "$DNS_READY" == "s" ] || [ "$DNS_READY" == "S" ]; then
        certbot --nginx -d $DOMAIN --non-interactive --agree-tos --email $ADMIN_EMAIL || \
            print_warning "No se pudo obtener el certificado SSL. Puedes intentarlo manualmente después."
    else
        print_warning "Configura el DNS primero y luego ejecuta: sudo certbot --nginx -d $DOMAIN"
    fi

    print_success "Certbot instalado"
else
    print_info "Saltando instalación de SSL"
fi

################################################################################
# STEP 15: Configure Postfix
################################################################################

print_step "PASO 15: CONFIGURAR POSTFIX"

print_info "Configurando Postfix..."

# Backup original config
cp /etc/postfix/main.cf /etc/postfix/main.cf.backup

cat > /etc/postfix/main.cf <<EOF
# Basic settings
myhostname = $DOMAIN
mydomain = $(echo $DOMAIN | cut -d. -f2-)
myorigin = \$mydomain
mydestination = \$myhostname, localhost.\$mydomain, localhost
inet_interfaces = all
inet_protocols = ipv4

# Network
relayhost =
mynetworks = 127.0.0.0/8 [::1]/128

# Virtual domains
virtual_mailbox_domains = mysql:/etc/postfix/mysql-virtual-mailbox-domains.cf
virtual_mailbox_maps = mysql:/etc/postfix/mysql-virtual-mailbox-maps.cf
virtual_alias_maps = mysql:/etc/postfix/mysql-virtual-alias-maps.cf

# SMTP settings
smtpd_banner = \$myhostname ESMTP
biff = no
append_dot_mydomain = no
readme_directory = no
compatibility_level = 3.6

# TLS parameters
smtpd_tls_cert_file=/etc/ssl/certs/ssl-cert-snakeoil.pem
smtpd_tls_key_file=/etc/ssl/private/ssl-cert-snakeoil.key
smtpd_tls_security_level=may
smtp_tls_security_level=may
smtpd_tls_protocols = !SSLv2, !SSLv3, !TLSv1, !TLSv1.1
smtp_tls_protocols = !SSLv2, !SSLv3, !TLSv1, !TLSv1.1

# SASL Authentication
smtpd_sasl_type = dovecot
smtpd_sasl_path = private/auth
smtpd_sasl_auth_enable = yes
smtpd_sasl_security_options = noanonymous
broken_sasl_auth_clients = yes

# Anti-spam settings
smtpd_helo_required = yes
smtpd_recipient_restrictions =
    permit_mynetworks,
    permit_sasl_authenticated,
    reject_non_fqdn_recipient,
    reject_unknown_recipient_domain,
    reject_unauth_destination,
    reject_rbl_client zen.spamhaus.org,
    reject_rbl_client bl.spamcop.net,
    permit

# Rate limiting
smtpd_client_connection_count_limit = 10
smtpd_client_connection_rate_limit = 30

# Size limits
message_size_limit = 52428800

# DKIM/DMARC
milter_default_action = accept
milter_protocol = 6
smtpd_milters = inet:127.0.0.1:8891,inet:127.0.0.1:8893
non_smtpd_milters = \$smtpd_milters

# Other
recipient_delimiter = +
alias_maps = hash:/etc/aliases
alias_database = hash:/etc/aliases
home_mailbox = Maildir/
EOF

# Configure submission port
cat >> /etc/postfix/master.cf <<'EOF'

# SMTP Submission (port 587) with authentication
submission inet n       -       y       -       -       smtpd
  -o syslog_name=postfix/submission
  -o smtpd_tls_security_level=encrypt
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_sasl_type=dovecot
  -o smtpd_sasl_path=private/auth
  -o smtpd_reject_unlisted_recipient=no
  -o smtpd_client_restrictions=permit_sasl_authenticated,reject
  -o milter_macro_daemon_name=ORIGINATING
EOF

# Create MySQL query files
cat > /etc/postfix/mysql-virtual-mailbox-domains.cf <<EOF
user = mailcore
password = $DB_PASSWORD
hosts = 127.0.0.1
dbname = mailcore
query = SELECT name FROM domains WHERE name='%s' AND is_active=1
EOF

cat > /etc/postfix/mysql-virtual-mailbox-maps.cf <<EOF
user = mailcore
password = $DB_PASSWORD
hosts = 127.0.0.1
dbname = mailcore
query = SELECT CONCAT(local_part, '@', d.name) FROM mailboxes m JOIN domains d ON m.domain_id=d.id WHERE CONCAT(m.local_part, '@', d.name)='%s' AND m.is_active=1
EOF

cat > /etc/postfix/mysql-virtual-alias-maps.cf <<EOF
user = mailcore
password = $DB_PASSWORD
hosts = 127.0.0.1
dbname = mailcore
query = SELECT destination FROM aliases WHERE source='%s' AND is_active=1
EOF

chmod 640 /etc/postfix/mysql-*.cf
chown root:postfix /etc/postfix/mysql-*.cf

systemctl restart postfix

print_success "Postfix configurado"

################################################################################
# STEP 16: Configure OpenDKIM
################################################################################

print_step "PASO 16: CONFIGURAR OPENDKIM"

print_info "Configurando OpenDKIM..."

mkdir -p /etc/opendkim/keys
chown -R opendkim:opendkim /etc/opendkim
chmod 700 /etc/opendkim/keys

cat > /etc/opendkim.conf <<'EOF'
Syslog yes
SyslogSuccess yes
LogWhy yes
Mode sv
Canonicalization relaxed/simple
KeyTable /etc/opendkim/KeyTable
SigningTable refile:/etc/opendkim/SigningTable
ExternalIgnoreList refile:/etc/opendkim/TrustedHosts
InternalHosts refile:/etc/opendkim/TrustedHosts
Socket inet:8891@localhost
PidFile /run/opendkim/opendkim.pid
UserID opendkim:opendkim
UMask 002
EOF

cat > /etc/opendkim/TrustedHosts <<EOF
127.0.0.1
localhost
*.$DOMAIN
EOF

touch /etc/opendkim/KeyTable
touch /etc/opendkim/SigningTable

systemctl restart opendkim

print_success "OpenDKIM configurado"

################################################################################
# STEP 17: Configure OpenDMARC
################################################################################

print_step "PASO 17: CONFIGURAR OPENDMARC"

print_info "Configurando OpenDMARC..."

cat > /etc/opendmarc.conf <<EOF
AuthservID $DOMAIN
TrustedAuthservIDs $DOMAIN
ReportingOptions v
Socket inet:8893@localhost
PidFile /run/opendmarc/opendmarc.pid
UserID opendmarc:opendmarc
UMask 0002
Syslog true
EOF

systemctl restart opendmarc

print_success "OpenDMARC configurado"

################################################################################
# STEP 18: Configure Dovecot
################################################################################

print_step "PASO 18: CONFIGURAR DOVECOT"

print_info "Configurando Dovecot..."

# Create vmail user
groupadd -g 5000 vmail 2>/dev/null || true
useradd -g vmail -u 5000 vmail -d /var/mail/vmail -m 2>/dev/null || true

mkdir -p /var/mail/vmail
chown -R vmail:vmail /var/mail/vmail
chmod 700 /var/mail/vmail

# Configure SQL auth
cat > /etc/dovecot/dovecot-sql.conf.ext <<EOF
driver = mysql
connect = host=127.0.0.1 dbname=mailcore user=mailcore password=$DB_PASSWORD
default_pass_scheme = ARGON2ID

password_query = SELECT CONCAT(m.local_part,'@',d.name) as user, m.password FROM mailboxes m JOIN domains d ON m.domain_id=d.id WHERE CONCAT(m.local_part,'@',d.name)='%u' AND m.is_active=1

user_query = SELECT CONCAT('/var/mail/vmail/',d.name,'/',m.local_part) as home, 5000 as uid, 5000 as gid FROM mailboxes m JOIN domains d ON m.domain_id=d.id WHERE CONCAT(m.local_part,'@',d.name)='%u'
EOF

chmod 640 /etc/dovecot/dovecot-sql.conf.ext
chown root:dovecot /etc/dovecot/dovecot-sql.conf.ext

# Enable SQL auth
cat > /etc/dovecot/conf.d/10-auth.conf <<'EOF'
disable_plaintext_auth = yes
auth_mechanisms = plain login
!include auth-sql.conf.ext
EOF

cat > /etc/dovecot/conf.d/auth-sql.conf.ext <<'EOF'
passdb {
  driver = sql
  args = /etc/dovecot/dovecot-sql.conf.ext
}
userdb {
  driver = sql
  args = /etc/dovecot/dovecot-sql.conf.ext
}
EOF

# Configure mail location
cat > /etc/dovecot/conf.d/10-mail.conf <<'EOF'
mail_location = maildir:~/Maildir
mail_privileged_group = mail
first_valid_uid = 5000
first_valid_gid = 5000
EOF

# Configure master services
cat > /etc/dovecot/conf.d/10-master.conf <<'EOF'
service auth {
  unix_listener /var/spool/postfix/private/auth {
    mode = 0660
    user = postfix
    group = postfix
  }
}
EOF

systemctl restart dovecot

print_success "Dovecot configurado"

################################################################################
# STEP 19: Create Backup Script
################################################################################

print_step "PASO 19: CONFIGURAR BACKUPS"

print_info "Creando script de backup..."

cat > /usr/local/bin/mailcore-backup.sh <<EOF
#!/bin/bash
BACKUP_DIR="/var/backups/mailcore"
RETENTION_DAYS=30
DATE=\$(date +%Y%m%d_%H%M%S)
DB_USER="mailcore"
DB_PASS="$DB_PASSWORD"
DB_NAME="mailcore"

mkdir -p \$BACKUP_DIR

# Backup database
mysqldump -u \$DB_USER -p\$DB_PASS \$DB_NAME | gzip > \$BACKUP_DIR/db_\$DATE.sql.gz

# Backup application
tar -czf \$BACKUP_DIR/files_\$DATE.tar.gz /var/www/mailcore \\
    --exclude=/var/www/mailcore/storage/logs/* \\
    --exclude=/var/www/mailcore/node_modules \\
    --exclude=/var/www/mailcore/.git 2>/dev/null

# Backup DKIM
tar -czf \$BACKUP_DIR/dkim_\$DATE.tar.gz /etc/opendkim/keys 2>/dev/null

# Backup mail
tar -czf \$BACKUP_DIR/mail_\$DATE.tar.gz /var/mail/vmail 2>/dev/null

# Remove old backups
find \$BACKUP_DIR -name "*.gz" -type f -mtime +\$RETENTION_DAYS -delete

echo "Backup completed: \$DATE"
EOF

chmod +x /usr/local/bin/mailcore-backup.sh

# Add to cron (daily at 2 AM)
(crontab -l 2>/dev/null; echo "0 2 * * * /usr/local/bin/mailcore-backup.sh >> /var/log/mailcore-backup.log 2>&1") | crontab -

print_success "Script de backup configurado"

################################################################################
# STEP 20: Create Status Script
################################################################################

print_step "PASO 20: CREAR SCRIPT DE ESTADO"

cat > /usr/local/bin/mailcore-status.sh <<'EOF'
#!/bin/bash

echo "=== MailCore System Status ==="
echo ""

echo "Web Services:"
systemctl is-active nginx | sed 's/^/  Nginx: /'
systemctl is-active php8.2-fpm | sed 's/^/  PHP-FPM: /'
echo ""

echo "Database:"
systemctl is-active mysql | sed 's/^/  MySQL: /'
systemctl is-active redis | sed 's/^/  Redis: /'
echo ""

echo "Mail Services:"
systemctl is-active postfix | sed 's/^/  Postfix: /'
systemctl is-active dovecot | sed 's/^/  Dovecot: /'
systemctl is-active opendkim | sed 's/^/  OpenDKIM: /'
systemctl is-active opendmarc | sed 's/^/  OpenDMARC: /'
echo ""

echo "Security:"
systemctl is-active fail2ban | sed 's/^/  Fail2Ban: /'
ufw status | grep -q "Status: active" && echo "  UFW: active" || echo "  UFW: inactive"
echo ""

echo "Disk Usage:"
df -h / | tail -1 | awk '{print "  Root: "$5" used"}'
df -h /var/www 2>/dev/null | tail -1 | awk '{print "  /var/www: "$5" used"}' || echo "  /var/www: N/A"
df -h /var/mail/vmail 2>/dev/null | tail -1 | awk '{print "  Mail: "$5" used"}' || echo "  Mail: N/A"
EOF

chmod +x /usr/local/bin/mailcore-status.sh

print_success "Script de estado creado"

################################################################################
# STEP 21: Enable Automatic Updates
################################################################################

print_step "PASO 21: CONFIGURAR ACTUALIZACIONES AUTOMÁTICAS"

print_info "Instalando actualizaciones automáticas de seguridad..."
apt install -y unattended-upgrades
dpkg-reconfigure -plow unattended-upgrades

print_success "Actualizaciones automáticas configuradas"

################################################################################
# FINAL: Summary
################################################################################

print_step "INSTALACIÓN COMPLETADA"

echo ""
print_success "¡El servidor ha sido configurado exitosamente!"
echo ""
print_info "RESUMEN DE LA INSTALACIÓN:"
echo ""
echo "  📦 Software instalado:"
echo "     - Nginx, PHP 8.2, MySQL, Redis"
echo "     - Postfix, Dovecot, OpenDKIM, OpenDMARC"
echo "     - Composer, Node.js, Supervisor"
echo "     - Fail2Ban, UFW (firewall)"
echo ""
echo "  🔒 Seguridad:"
echo "     - Firewall configurado"
echo "     - Fail2Ban activo"
echo "     - Actualizaciones automáticas habilitadas"
echo ""
echo "  💾 Backups:"
echo "     - Backup automático diario a las 2 AM"
echo "     - Ubicación: /var/backups/mailcore"
echo "     - Retención: 30 días"
echo ""
echo "  📝 Próximos pasos:"
echo ""
echo "     1. Clonar MailCore en /var/www/mailcore"
echo "     2. Ejecutar: cd /var/www/mailcore && ./scripts/deploy.sh"
echo "     3. Configurar DNS (A, MX, SPF, DKIM, DMARC)"
echo "     4. Acceder a: https://$DOMAIN/admin"
echo ""
echo "  🔧 Comandos útiles:"
echo "     - Ver estado: sudo /usr/local/bin/mailcore-status.sh"
echo "     - Ver logs: sudo tail -f /var/log/mail.log"
echo "     - Backup manual: sudo /usr/local/bin/mailcore-backup.sh"
echo ""
echo "  📚 Documentación:"
echo "     - Ver DEPLOYMENT.md para más detalles"
echo "     - Ver TESTING.md para testing local"
echo ""

print_warning "IMPORTANTE: Guarda esta información en un lugar seguro:"
echo ""
echo "  Dominio: $DOMAIN"
echo "  Email Admin: $ADMIN_EMAIL"
echo "  Password MySQL: $DB_PASSWORD"
echo "  Directorio Web: /var/www/mailcore"
echo ""

print_info "Gracias por usar MailCore!"
echo ""
