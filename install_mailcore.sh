#!/bin/bash

# ============================================================
# MAILCORE - Script de Instalación Automática
# ============================================================
# Este script instala y configura todos los componentes
# necesarios para ejecutar MailCore en Ubuntu 22.04+
# ============================================================

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}"
echo "============================================================"
echo "  MAILCORE - Instalación Automática"
echo "============================================================"
echo -e "${NC}"

# Verificar si se ejecuta como root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Por favor ejecute como root (sudo)${NC}"
    exit 1
fi

# Solicitar información básica
read -p "Ingrese su dominio (ej: ejemplo.com): " DOMAIN
read -p "Ingrese su hostname de correo (ej: mail.ejemplo.com): " MAIL_HOSTNAME
read -p "Ingrese su IP dedicada: " MAIL_IP
read -p "Ingrese email del administrador: " ADMIN_EMAIL

echo -e "\n${YELLOW}Configuración:${NC}"
echo "Dominio: $DOMAIN"
echo "Hostname: $MAIL_HOSTNAME"
echo "IP: $MAIL_IP"
echo "Admin Email: $ADMIN_EMAIL"
echo ""

read -p "¿Continuar con la instalación? (s/n): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Ss]$ ]]; then
    echo "Instalación cancelada."
    exit 1
fi

# ============================================================
# 1. ACTUALIZAR SISTEMA
# ============================================================
echo -e "\n${GREEN}[1/10] Actualizando sistema...${NC}"
apt update && apt upgrade -y

# ============================================================
# 2. INSTALAR DEPENDENCIAS
# ============================================================
echo -e "\n${GREEN}[2/10] Instalando dependencias...${NC}"
apt install -y \
    postfix \
    dovecot-core \
    dovecot-imapd \
    dovecot-lmtpd \
    opendkim \
    opendkim-tools \
    opendmarc \
    certbot \
    mailutils \
    git \
    ufw \
    fail2ban \
    php8.2 \
    php8.2-fpm \
    php8.2-cli \
    php8.2-mysql \
    php8.2-mbstring \
    php8.2-xml \
    php8.2-curl \
    php8.2-zip \
    php8.2-gd \
    php8.2-bcmath \
    mysql-server \
    nginx \
    supervisor \
    unzip \
    curl

# ============================================================
# 3. CONFIGURAR POSTFIX
# ============================================================
echo -e "\n${GREEN}[3/10] Configurando Postfix...${NC}"

# Detener Postfix
systemctl stop postfix

# Backup de configuración original
cp /etc/postfix/main.cf /etc/postfix/main.cf.backup

# Configuración básica de Postfix
cat > /etc/postfix/main.cf <<EOF
# MailCore - Postfix Configuration
myhostname = $MAIL_HOSTNAME
mydomain = $DOMAIN
myorigin = \$mydomain
mydestination = \$myhostname, localhost.\$mydomain, localhost
mynetworks = 127.0.0.0/8
inet_interfaces = all
inet_protocols = ipv4

# TLS Configuration
smtpd_tls_cert_file=/etc/letsencrypt/live/$MAIL_HOSTNAME/fullchain.pem
smtpd_tls_key_file=/etc/letsencrypt/live/$MAIL_HOSTNAME/privkey.pem
smtpd_use_tls=yes
smtpd_tls_auth_only=yes
smtpd_tls_security_level=may
smtp_tls_security_level=may

# SMTP Auth
smtpd_sasl_type = dovecot
smtpd_sasl_path = private/auth
smtpd_sasl_auth_enable = yes

# DKIM/DMARC
milter_default_action = accept
milter_protocol = 6
smtpd_milters = inet:localhost:8891, inet:localhost:8893
non_smtpd_milters = \$smtpd_milters

# Restrictions
smtpd_recipient_restrictions =
    permit_sasl_authenticated,
    permit_mynetworks,
    reject_unauth_destination

# Queue Configuration
maximal_queue_lifetime = 1d
bounce_queue_lifetime = 1d
EOF

# Configurar master.cf para submission
cat >> /etc/postfix/master.cf <<EOF

# Submission port
submission inet n       -       y       -       -       smtpd
  -o syslog_name=postfix/submission
  -o smtpd_tls_security_level=encrypt
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_tls_auth_only=yes
  -o smtpd_reject_unlisted_recipient=no
  -o smtpd_recipient_restrictions=permit_sasl_authenticated,reject
  -o smtpd_relay_restrictions=permit_sasl_authenticated,reject
EOF

# ============================================================
# 4. CONFIGURAR DOVECOT
# ============================================================
echo -e "\n${GREEN}[4/10] Configurando Dovecot...${NC}"

# Configuración básica de Dovecot
cat > /etc/dovecot/conf.d/10-auth.conf <<EOF
disable_plaintext_auth = yes
auth_mechanisms = plain login
!include auth-system.conf.ext
EOF

cat > /etc/dovecot/conf.d/10-mail.conf <<EOF
mail_location = maildir:/var/mail/vhosts/%d/%n
mail_privileged_group = mail
EOF

cat > /etc/dovecot/conf.d/10-master.conf <<EOF
service imap-login {
  inet_listener imap {
    port = 0
  }
  inet_listener imaps {
    port = 993
    ssl = yes
  }
}

service auth {
  unix_listener /var/spool/postfix/private/auth {
    mode = 0666
    user = postfix
    group = postfix
  }
}
EOF

# Crear directorio para correos virtuales
mkdir -p /var/mail/vhosts/$DOMAIN
groupadd -g 5000 vmail || true
useradd -g vmail -u 5000 vmail -d /var/mail || true
chown -R vmail:vmail /var/mail

# ============================================================
# 5. CONFIGURAR OPENDKIM
# ============================================================
echo -e "\n${GREEN}[5/10] Configurando OpenDKIM...${NC}"

mkdir -p /etc/opendkim/keys/$DOMAIN

cat > /etc/opendkim.conf <<EOF
Syslog                  yes
LogWhy                  yes
UMask                   007
Socket                  inet:8891@localhost
PidFile                 /run/opendkim/opendkim.pid
OversignHeaders         From
TrustAnchorFile         /usr/share/dns/root.key
UserID                  opendkim

Mode                    sv
SubDomains              no
AutoRestart             yes
AutoRestartRate         10/1M
Background              yes
DNSTimeout              5
SignatureAlgorithm      rsa-sha256

KeyTable                refile:/etc/opendkim/key.table
SigningTable            refile:/etc/opendkim/signing.table
ExternalIgnoreList      /etc/opendkim/trusted.hosts
InternalHosts           /etc/opendkim/trusted.hosts
EOF

# Configurar tablas de DKIM (se completarán desde Laravel)
touch /etc/opendkim/key.table
touch /etc/opendkim/signing.table

cat > /etc/opendkim/trusted.hosts <<EOF
127.0.0.1
localhost
$MAIL_HOSTNAME
*.$DOMAIN
EOF

chown -R opendkim:opendkim /etc/opendkim
chmod -R 700 /etc/opendkim/keys

# ============================================================
# 6. CONFIGURAR OPENDMARC
# ============================================================
echo -e "\n${GREEN}[6/10] Configurando OpenDMARC...${NC}"

cat > /etc/opendmarc.conf <<EOF
AuthservID $MAIL_HOSTNAME
PidFile /run/opendmarc/opendmarc.pid
RejectFailures false
Socket inet:8893@localhost
Syslog true
TrustedAuthservIDs $MAIL_HOSTNAME
UMask 0002
UserID opendmarc:opendmarc
IgnoreHosts /etc/opendmarc/ignore.hosts
HistoryFile /var/run/opendmarc/opendmarc.dat
EOF

echo "127.0.0.1" > /etc/opendmarc/ignore.hosts

# ============================================================
# 7. OBTENER CERTIFICADO SSL
# ============================================================
echo -e "\n${GREEN}[7/10] Obteniendo certificado SSL...${NC}"

# Detener servicios temporalmente
systemctl stop nginx postfix dovecot || true

# Obtener certificado
certbot certonly --standalone -d $MAIL_HOSTNAME --email $ADMIN_EMAIL --agree-tos --non-interactive

# Configurar renovación automática
echo "0 0 * * * root certbot renew --quiet && systemctl reload postfix dovecot nginx" > /etc/cron.d/certbot-renewal

# ============================================================
# 8. CONFIGURAR FIREWALL
# ============================================================
echo -e "\n${GREEN}[8/10] Configurando firewall...${NC}"

ufw --force reset
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp      # SSH
ufw allow 25/tcp      # SMTP
ufw allow 587/tcp     # Submission
ufw allow 993/tcp     # IMAPS
ufw allow 80/tcp      # HTTP
ufw allow 443/tcp     # HTTPS
ufw --force enable

# ============================================================
# 9. CONFIGURAR FAIL2BAN
# ============================================================
echo -e "\n${GREEN}[9/10] Configurando Fail2ban...${NC}"

cat > /etc/fail2ban/jail.local <<EOF
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true

[postfix]
enabled = true
port = smtp,465,submission
logpath = /var/log/mail.log

[dovecot]
enabled = true
port = pop3,pop3s,imap,imaps,submission,465,sieve
logpath = /var/log/mail.log
EOF

systemctl enable fail2ban
systemctl restart fail2ban

# ============================================================
# 10. INSTALAR COMPOSER Y CONFIGURAR LARAVEL
# ============================================================
echo -e "\n${GREEN}[10/10] Instalando Composer...${NC}"

# Instalar Composer
if [ ! -f /usr/local/bin/composer ]; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo -e "\n${GREEN}Instalación completada!${NC}\n"

echo -e "${YELLOW}Próximos pasos:${NC}"
echo "1. Configurar DNS records:"
echo "   MX    @    $MAIL_HOSTNAME    10"
echo "   A     mail    $MAIL_IP"
echo ""
echo "2. Instalar dependencias de Laravel:"
echo "   cd /var/www/mailcore"
echo "   composer install"
echo "   php artisan key:generate"
echo "   php artisan migrate"
echo ""
echo "3. Generar claves DKIM para su dominio:"
echo "   php artisan mailcore:generate-dkim $DOMAIN"
echo ""
echo "4. Verificar registros DNS:"
echo "   php artisan mailcore:verify-domains"
echo ""
echo "5. Acceder al panel en: https://$MAIL_HOSTNAME/admin"
echo ""

# Reiniciar servicios
systemctl enable postfix dovecot opendkim opendmarc nginx
systemctl restart postfix dovecot opendkim opendmarc

echo -e "${GREEN}¡Todos los servicios están corriendo!${NC}"
