#!/bin/bash

# MailCore - Postfix Configuration Script
# This script configures Postfix for use with MailCore

set -e

echo "========================================"
echo "MailCore - Postfix Configuration"
echo "========================================"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "❌ Error: This script must be run as root"
    echo "Run: sudo ./scripts/configure-postfix.sh"
    exit 1
fi

# Get domain and hostname
read -p "Enter your main domain (e.g., example.com): " MAIN_DOMAIN
read -p "Enter your mail hostname (e.g., mail.example.com): " MAIL_HOSTNAME
read -p "Enter your server IP address: " SERVER_IP

echo ""
echo "Configuration:"
echo "  Domain: $MAIN_DOMAIN"
echo "  Hostname: $MAIL_HOSTNAME"
echo "  IP: $SERVER_IP"
echo ""
read -p "Is this correct? (y/n): " CONFIRM

if [ "$CONFIRM" != "y" ] && [ "$CONFIRM" != "Y" ]; then
    echo "❌ Configuration cancelled"
    exit 1
fi

# Backup existing configuration
echo "📦 Backing up existing Postfix configuration..."
cp /etc/postfix/main.cf /etc/postfix/main.cf.backup.$(date +%Y%m%d_%H%M%S)

# Configure Postfix main.cf
echo "⚙️ Configuring Postfix main.cf..."
cat > /etc/postfix/main.cf << EOF
# MailCore Postfix Configuration
# Generated: $(date)

# Basic Settings
myhostname = $MAIL_HOSTNAME
mydomain = $MAIN_DOMAIN
myorigin = \$mydomain
mydestination = localhost

# Network Settings
inet_interfaces = all
inet_protocols = ipv4
mynetworks = 127.0.0.0/8, $SERVER_IP/32

# Mail Settings
relayhost =
home_mailbox = Maildir/

# Virtual Domains (MySQL)
virtual_mailbox_domains = mysql:/etc/postfix/mysql-virtual-mailbox-domains.cf
virtual_mailbox_maps = mysql:/etc/postfix/mysql-virtual-mailbox-maps.cf
virtual_alias_maps = mysql:/etc/postfix/mysql-virtual-alias-maps.cf

# TLS Settings
smtpd_tls_cert_file = /etc/ssl/certs/ssl-cert-snakeoil.pem
smtpd_tls_key_file = /etc/ssl/private/ssl-cert-snakeoil.key
smtpd_use_tls = yes
smtpd_tls_security_level = may
smtp_tls_security_level = may
smtpd_tls_protocols = !SSLv2, !SSLv3, !TLSv1, !TLSv1.1
smtpd_tls_ciphers = high
smtpd_tls_mandatory_ciphers = high

# SASL Authentication
smtpd_sasl_type = dovecot
smtpd_sasl_path = private/auth
smtpd_sasl_auth_enable = yes
smtpd_sasl_security_options = noanonymous
smtpd_sasl_local_domain = \$myhostname
broken_sasl_auth_clients = yes

# SMTP Restrictions
smtpd_recipient_restrictions =
    permit_mynetworks,
    permit_sasl_authenticated,
    reject_unauth_destination,
    reject_invalid_hostname,
    reject_non_fqdn_hostname,
    reject_non_fqdn_sender,
    reject_non_fqdn_recipient,
    reject_unknown_sender_domain,
    reject_unknown_recipient_domain

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

# Message Size
message_size_limit = 52428800
mailbox_size_limit = 0

# OpenDKIM
milter_default_action = accept
milter_protocol = 6
smtpd_milters = inet:localhost:8891
non_smtpd_milters = \$smtpd_milters

# Misc
biff = no
append_dot_mydomain = no
readme_directory = no
compatibility_level = 2
EOF

# Configure MySQL connection files
echo "🔗 Configuring MySQL connection files..."

# Read database credentials from .env
DB_HOST=$(grep '^DB_HOST=' /home/user/mailer/.env | cut -d'=' -f2)
DB_DATABASE=$(grep '^DB_DATABASE=' /home/user/mailer/.env | cut -d'=' -f2)
DB_USERNAME=$(grep '^DB_USERNAME=' /home/user/mailer/.env | cut -d'=' -f2)
DB_PASSWORD=$(grep '^DB_PASSWORD=' /home/user/mailer/.env | cut -d'=' -f2)

# Virtual mailbox domains
cat > /etc/postfix/mysql-virtual-mailbox-domains.cf << EOF
user = $DB_USERNAME
password = $DB_PASSWORD
hosts = $DB_HOST
dbname = $DB_DATABASE
query = SELECT 1 FROM domains WHERE name='%s' AND is_active=1
EOF

# Virtual mailbox maps
cat > /etc/postfix/mysql-virtual-mailbox-maps.cf << EOF
user = $DB_USERNAME
password = $DB_PASSWORD
hosts = $DB_HOST
dbname = $DB_DATABASE
query = SELECT 1 FROM mailboxes WHERE email='%s' AND is_active=1
EOF

# Virtual alias maps
cat > /etc/postfix/mysql-virtual-alias-maps.cf << EOF
user = $DB_USERNAME
password = $DB_PASSWORD
hosts = $DB_HOST
dbname = $DB_DATABASE
query = SELECT email FROM mailboxes WHERE email='%s' AND is_active=1
EOF

# Set proper permissions
chmod 640 /etc/postfix/mysql-*.cf
chown root:postfix /etc/postfix/mysql-*.cf

# Configure master.cf for submission
echo "📧 Configuring submission port..."
postconf -M submission/inet="submission inet n - n - - smtpd"
postconf -P "submission/inet/syslog_name=postfix/submission"
postconf -P "submission/inet/smtpd_tls_security_level=encrypt"
postconf -P "submission/inet/smtpd_sasl_auth_enable=yes"
postconf -P "submission/inet/smtpd_client_restrictions=permit_sasl_authenticated,reject"

# Restart Postfix
echo "🔄 Restarting Postfix..."
systemctl restart postfix
systemctl enable postfix

# Check status
echo ""
echo "✅ Postfix configuration completed!"
echo ""
postfix check
systemctl status postfix --no-pager

echo ""
echo "========================================"
echo "Next Steps:"
echo "========================================"
echo "1. Configure DNS records (SPF, DKIM, DMARC)"
echo "2. Configure OpenDKIM"
echo "3. Configure Dovecot (if receiving emails)"
echo "4. Test sending emails from MailCore"
echo ""
echo "To test Postfix:"
echo "  echo 'Test email' | mail -s 'Test' test@example.com"
echo ""
