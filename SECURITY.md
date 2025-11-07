# 🔒 MailCore - Guía de Seguridad

Esta guía cubre todos los aspectos de seguridad para MailCore en producción.

## 📋 Tabla de Contenidos

1. [Firewall (UFW)](#firewall-ufw)
2. [SSL/TLS](#ssltls)
3. [Fail2Ban](#fail2ban)
4. [Hardening del Sistema](#hardening-del-sistema)
5. [Seguridad de Aplicación](#seguridad-de-aplicación)
6. [Seguridad de Mail Server](#seguridad-de-mail-server)
7. [Monitoreo y Alertas](#monitoreo-y-alertas)
8. [Checklist de Seguridad](#checklist-de-seguridad)

---

## 🛡️ Firewall (UFW)

### Instalación y Configuración Básica

```bash
# Instalar UFW
sudo apt install -y ufw

# Configurar políticas por defecto
sudo ufw default deny incoming
sudo ufw default allow outgoing

# Permitir SSH (IMPORTANTE: hacer esto primero)
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

# Habilitar UFW
sudo ufw --force enable

# Ver estado
sudo ufw status verbose
```

### Reglas Avanzadas

```bash
# Permitir desde IP específica
sudo ufw allow from 203.0.113.0 to any port 22

# Permitir rango de IPs
sudo ufw allow from 203.0.113.0/24 to any port 3306

# Limitar intentos de conexión (protección contra fuerza bruta)
sudo ufw limit 22/tcp
sudo ufw limit 587/tcp

# Denegar IP específica
sudo ufw deny from 203.0.113.100

# Eliminar regla
sudo ufw delete allow 80/tcp

# Resetear todas las reglas
sudo ufw reset
```

### Reglas para Servidor Compartido

```bash
# Si tienes MySQL pero solo para uso local
sudo ufw deny 3306/tcp

# Si tienes Redis pero solo para uso local
sudo ufw deny 6379/tcp

# Si necesitas acceso remoto a MySQL desde IP específica
sudo ufw allow from 203.0.113.50 to any port 3306

# Ver reglas numeradas
sudo ufw status numbered

# Eliminar regla por número
sudo ufw delete 5
```

### Logging

```bash
# Habilitar logging
sudo ufw logging on

# Nivel de logging (low, medium, high, full)
sudo ufw logging medium

# Ver logs
sudo tail -f /var/log/ufw.log

# Analizar intentos bloqueados
sudo grep "UFW BLOCK" /var/log/ufw.log | tail -20
```

---

## 🔐 SSL/TLS

### Obtener Certificados con Let's Encrypt

```bash
# Instalar Certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtener certificado para un dominio
sudo certbot --nginx -d mail.tudominio.com

# Obtener certificado para múltiples dominios
sudo certbot --nginx -d mail.tudominio.com -d webmail.tudominio.com

# Obtener certificado wildcard (requiere DNS challenge)
sudo certbot certonly --manual --preferred-challenges dns -d *.tudominio.com

# Forzar renovación
sudo certbot renew --force-renewal

# Test de renovación (dry-run)
sudo certbot renew --dry-run
```

### Configuración SSL Óptima

La configuración recomendada ya está en los archivos de Nginx:

```nginx
# TLS versions
ssl_protocols TLSv1.2 TLSv1.3;

# Ciphers (Mozilla Modern)
ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;

# Prefer server ciphers
ssl_prefer_server_ciphers off;

# Session cache
ssl_session_cache shared:SSL:10m;
ssl_session_timeout 10m;

# OCSP Stapling
ssl_stapling on;
ssl_stapling_verify on;

# HSTS (31536000 seconds = 1 year)
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
```

### Verificar Configuración SSL

```bash
# Test local
openssl s_client -connect mail.tudominio.com:443 -servername mail.tudominio.com

# Ver certificado
openssl s_client -connect mail.tudominio.com:443 -servername mail.tudominio.com 2>/dev/null | openssl x509 -noout -dates

# Ver cipher suites
openssl s_client -connect mail.tudominio.com:443 -servername mail.tudominio.com 2>/dev/null | grep "Cipher"

# Test online
# https://www.ssllabs.com/ssltest/
```

### Renovación Automática

Let's Encrypt configura automáticamente un systemd timer:

```bash
# Ver timer
sudo systemctl status certbot.timer

# Ver próxima ejecución
sudo systemctl list-timers | grep certbot

# Logs de renovación
sudo journalctl -u certbot.service

# Test manual de renovación
sudo certbot renew --dry-run
```

### Certificados para Mail Server

```bash
# Postfix necesita acceso a los certificados
sudo chmod 755 /etc/letsencrypt/live/
sudo chmod 755 /etc/letsencrypt/archive/

# En /etc/postfix/main.cf
smtpd_tls_cert_file=/etc/letsencrypt/live/mail.tudominio.com/fullchain.pem
smtpd_tls_key_file=/etc/letsencrypt/live/mail.tudominio.com/privkey.pem

# En /etc/dovecot/conf.d/10-ssl.conf
ssl_cert = </etc/letsencrypt/live/mail.tudominio.com/fullchain.pem
ssl_key = </etc/letsencrypt/live/mail.tudominio.com/privkey.pem

# Reiniciar servicios después de renovación
sudo systemctl restart postfix dovecot
```

---

## 🚫 Fail2Ban

### Instalación y Configuración

```bash
# Instalar Fail2Ban
sudo apt install -y fail2ban

# Copiar configuración por defecto
sudo cp /etc/fail2ban/jail.conf /etc/fail2ban/jail.local

# Editar configuración
sudo nano /etc/fail2ban/jail.local
```

### Configuración Recomendada

```bash
# Crear /etc/fail2ban/jail.local
sudo tee /etc/fail2ban/jail.local > /dev/null <<'EOF'
[DEFAULT]
# Ban settings
bantime = 3600
findtime = 600
maxretry = 5
destemail = admin@tudominio.com
sendername = Fail2Ban
action = %(action_mwl)s

# SSH
[sshd]
enabled = true
port = 22
logpath = /var/log/auth.log
maxretry = 3
bantime = 7200

# Nginx HTTP Auth
[nginx-http-auth]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log
maxretry = 5

# Nginx Limit Request
[nginx-limit-req]
enabled = true
port = http,https
logpath = /var/log/nginx/error.log
maxretry = 10

# Nginx Bad Bots
[nginx-badbots]
enabled = true
port = http,https
logpath = /var/log/nginx/access.log
maxretry = 2

# Postfix
[postfix]
enabled = true
mode = aggressive
port = smtp,465,submission
logpath = /var/log/mail.log
maxretry = 3
bantime = 3600

# Dovecot
[dovecot]
enabled = true
port = pop3,pop3s,imap,imaps,submission
logpath = /var/log/mail.log
maxretry = 3
bantime = 3600

# PHP errors (brute force)
[php-url-fopen]
enabled = true
port = http,https
logpath = /var/log/nginx/access.log
maxretry = 5

# MailCore specific (Laravel)
[mailcore-auth]
enabled = true
port = http,https
logpath = /var/www/mailcore/storage/logs/laravel.log
maxretry = 5
bantime = 1800
findtime = 300
EOF

# Reiniciar Fail2Ban
sudo systemctl restart fail2ban
sudo systemctl enable fail2ban
```

### Filtros Personalizados

Para proteger el login de Laravel/Filament:

```bash
# Crear filtro personalizado
sudo tee /etc/fail2ban/filter.d/mailcore-auth.conf > /dev/null <<'EOF'
[Definition]
failregex = .*authentication attempt.*from <HOST>.*
            .*Failed login attempt.*<HOST>.*
ignoreregex =
EOF

# Crear jail personalizada
sudo tee -a /etc/fail2ban/jail.local > /dev/null <<'EOF'

[mailcore-auth]
enabled = true
filter = mailcore-auth
logpath = /var/www/mailcore/storage/logs/laravel.log
port = http,https
maxretry = 5
findtime = 600
bantime = 1800
EOF

sudo systemctl restart fail2ban
```

### Comandos de Gestión

```bash
# Ver estado
sudo fail2ban-client status

# Ver estado de una jail específica
sudo fail2ban-client status sshd
sudo fail2ban-client status postfix

# Ver IPs baneadas
sudo fail2ban-client get sshd banned

# Desbanear IP
sudo fail2ban-client set sshd unbanip 203.0.113.100

# Banear IP manualmente
sudo fail2ban-client set sshd banip 203.0.113.100

# Ver logs
sudo tail -f /var/log/fail2ban.log

# Recargar configuración
sudo fail2ban-client reload
```

---

## 🔧 Hardening del Sistema

### 1. SSH Security

```bash
# Editar configuración SSH
sudo nano /etc/ssh/sshd_config

# Configuraciones recomendadas:
Port 22                              # Cambiar a otro puerto si es posible
PermitRootLogin no                   # Deshabilitar login como root
PasswordAuthentication yes           # O 'no' si usas solo keys
PubkeyAuthentication yes             # Habilitar autenticación por clave
PermitEmptyPasswords no              # No permitir passwords vacías
MaxAuthTries 3                       # Máximo 3 intentos
ClientAliveInterval 300              # Timeout 5 minutos
ClientAliveCountMax 2                # 2 checks antes de desconectar
AllowUsers usuario1 usuario2         # Solo usuarios específicos
Protocol 2                           # Solo SSH v2
X11Forwarding no                     # Deshabilitar X11
UseDNS no                            # Faster connections

# Reiniciar SSH
sudo systemctl restart sshd
```

### 2. Automatic Security Updates

```bash
# Instalar unattended-upgrades
sudo apt install -y unattended-upgrades apt-listchanges

# Configurar
sudo dpkg-reconfigure -plow unattended-upgrades

# Verificar configuración
sudo nano /etc/apt/apt.conf.d/50unattended-upgrades

# Configuración recomendada:
Unattended-Upgrade::Allowed-Origins {
    "${distro_id}:${distro_codename}";
    "${distro_id}:${distro_codename}-security";
    "${distro_id}ESMApps:${distro_codename}-apps-security";
    "${distro_id}ESM:${distro_codename}-infra-security";
};
Unattended-Upgrade::AutoFixInterruptedDpkg "true";
Unattended-Upgrade::Remove-Unused-Dependencies "true";
Unattended-Upgrade::Automatic-Reboot "false";

# Ver logs
sudo tail -f /var/log/unattended-upgrades/unattended-upgrades.log
```

### 3. Disable Unused Services

```bash
# Ver servicios activos
sudo systemctl list-units --type=service --state=running

# Deshabilitar servicios no necesarios
sudo systemctl disable bluetooth
sudo systemctl stop bluetooth

# Ejemplo: deshabilitar Avahi (si no lo usas)
sudo systemctl disable avahi-daemon
sudo systemctl stop avahi-daemon
```

### 4. Kernel Hardening (sysctl)

```bash
# Editar configuración
sudo nano /etc/sysctl.d/99-custom.conf

# Añadir:
# IP Forwarding (solo si no es necesario)
net.ipv4.ip_forward = 0

# SYN Cookies (protección contra SYN flood)
net.ipv4.tcp_syncookies = 1

# Ignorar pings (opcional)
net.ipv4.icmp_echo_ignore_all = 0
net.ipv4.icmp_echo_ignore_broadcasts = 1

# Ignorar ICMP redirects
net.ipv4.conf.all.accept_redirects = 0
net.ipv6.conf.all.accept_redirects = 0

# Ignorar source routed packets
net.ipv4.conf.all.accept_source_route = 0
net.ipv6.conf.all.accept_source_route = 0

# Log martians
net.ipv4.conf.all.log_martians = 1

# Reverse path filtering
net.ipv4.conf.all.rp_filter = 1
net.ipv4.conf.default.rp_filter = 1

# TCP hardening
net.ipv4.tcp_max_syn_backlog = 2048
net.ipv4.tcp_synack_retries = 2
net.ipv4.tcp_syn_retries = 5

# Aplicar cambios
sudo sysctl -p /etc/sysctl.d/99-custom.conf
```

### 5. File Permissions

```bash
# MailCore permissions
sudo chown -R www-data:www-data /var/www/mailcore
sudo chmod -R 755 /var/www/mailcore
sudo chmod -R 775 /var/www/mailcore/storage
sudo chmod -R 775 /var/www/mailcore/bootstrap/cache

# Proteger .env
sudo chmod 600 /var/www/mailcore/.env

# DKIM keys
sudo chown -R opendkim:opendkim /etc/opendkim/keys
sudo chmod 700 /etc/opendkim/keys

# MySQL config
sudo chmod 600 /etc/postfix/mysql-*.cf
sudo chown root:postfix /etc/postfix/mysql-*.cf
```

### 6. Disable IPv6 (si no lo usas)

```bash
# Editar GRUB
sudo nano /etc/default/grub

# Añadir a GRUB_CMDLINE_LINUX
GRUB_CMDLINE_LINUX="ipv6.disable=1"

# Actualizar GRUB
sudo update-grub

# Reiniciar
sudo reboot
```

---

## 🛡️ Seguridad de Aplicación

### Laravel Best Practices

```bash
# 1. Nunca dejar debug en producción
APP_DEBUG=false

# 2. Usar HTTPS
APP_URL=https://mail.tudominio.com

# 3. Regenerar APP_KEY en cada instalación
php artisan key:generate

# 4. Configurar CORS correctamente (en config/cors.php)

# 5. Validar TODOS los inputs
# Ver los Request classes en app/Http/Requests/

# 6. Usar rate limiting
# Ya configurado en app/Http/Middleware/AntiSpamMiddleware.php

# 7. Sanitizar outputs
# Blade hace esto automáticamente con {{ }}
# Nunca usar {!! !!} con input de usuario

# 8. Proteger contra CSRF
# Laravel incluye esto por defecto

# 9. Usar prepared statements
# Eloquent hace esto automáticamente

# 10. Logs seguros
# Nunca loguear passwords o tokens
```

### Security Headers (ya configurados en Nginx)

```nginx
# X-Frame-Options
add_header X-Frame-Options "SAMEORIGIN" always;

# X-Content-Type-Options
add_header X-Content-Type-Options "nosniff" always;

# X-XSS-Protection
add_header X-XSS-Protection "1; mode=block" always;

# Referrer Policy
add_header Referrer-Policy "no-referrer-when-downgrade" always;

# HSTS
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

# Content Security Policy
add_header Content-Security-Policy "default-src 'self';" always;

# Permissions Policy
add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
```

---

## 📧 Seguridad de Mail Server

### SPF, DKIM, DMARC

```bash
# SPF Record (DNS TXT)
v=spf1 ip4:123.456.789.10 a:mail.tudominio.com -all

# DKIM Record (generar con manage-dkim.sh)
sudo ./scripts/manage-dkim.sh generate tudominio.com

# DMARC Record (DNS TXT)
_dmarc.tudominio.com TXT "v=DMARC1; p=quarantine; rua=mailto:dmarc@tudominio.com; ruf=mailto:dmarc@tudominio.com; fo=1"
```

### Postfix Security

```bash
# Rate limiting (ya en main.cf)
smtpd_client_connection_count_limit = 10
smtpd_client_connection_rate_limit = 30
smtpd_client_message_rate_limit = 100

# RBL blacklists
reject_rbl_client zen.spamhaus.org
reject_rbl_client bl.spamcop.net
reject_rbl_client cbl.abuseat.org

# Reject invalid recipients
reject_unauth_destination
reject_unknown_recipient_domain
reject_non_fqdn_recipient

# Require HELO
smtpd_helo_required = yes
```

### Reverse DNS (PTR)

```bash
# Verificar PTR
dig -x 123.456.789.10 +short

# Debe devolver: mail.tudominio.com

# Configurar con tu proveedor de hosting
```

### Test de Mail Server

```bash
# Test de mail
# https://www.mail-tester.com/

# Test de blacklists
# https://mxtoolbox.com/blacklists.aspx

# Test de SPF/DKIM/DMARC
# https://dkimvalidator.com/
```

---

## 📊 Monitoreo y Alertas

### Script de Monitoreo

```bash
# Crear script
sudo tee /usr/local/bin/security-check.sh > /dev/null <<'EOF'
#!/bin/bash

echo "=== Security Check Report ==="
echo "Date: $(date)"
echo ""

# Failed SSH attempts
echo "Failed SSH attempts (last 24h):"
sudo grep "Failed password" /var/log/auth.log | grep "$(date +%b\ %d)" | wc -l

# Banned IPs (Fail2Ban)
echo ""
echo "Currently banned IPs:"
sudo fail2ban-client status sshd | grep "Banned IP list"

# Disk usage
echo ""
echo "Disk usage:"
df -h / | tail -1 | awk '{print "  Root: "$5" used"}'

# Security updates
echo ""
echo "Security updates available:"
/usr/lib/update-notifier/apt-check --human-readable

# SSL expiry
echo ""
echo "SSL certificate expiry:"
echo | openssl s_client -servername mail.tudominio.com -connect mail.tudominio.com:443 2>/dev/null | openssl x509 -noout -dates | grep "notAfter"

# Firewall status
echo ""
echo "Firewall status:"
sudo ufw status | head -5

echo ""
echo "=== End of Report ==="
EOF

sudo chmod +x /usr/local/bin/security-check.sh

# Ejecutar
sudo /usr/local/bin/security-check.sh

# Programar ejecución diaria
(sudo crontab -l 2>/dev/null; echo "0 8 * * * /usr/local/bin/security-check.sh | mail -s 'Security Report' admin@tudominio.com") | sudo crontab -
```

### Logs Importantes

```bash
# Auth logs (SSH, login attempts)
sudo tail -f /var/log/auth.log

# Mail logs
sudo tail -f /var/log/mail.log

# Nginx logs
sudo tail -f /var/log/nginx/error.log

# Fail2Ban logs
sudo tail -f /var/log/fail2ban.log

# UFW logs
sudo tail -f /var/log/ufw.log

# Laravel logs
sudo tail -f /var/www/mailcore/storage/logs/laravel.log
```

---

## ✅ Checklist de Seguridad

### Configuración Inicial

- [ ] Firewall (UFW) configurado y activo
- [ ] Fail2Ban instalado y configurado
- [ ] SSH hardening aplicado
- [ ] Root login deshabilitado
- [ ] Actualizaciones automáticas configuradas
- [ ] Timezone configurado correctamente

### SSL/TLS

- [ ] Certificado SSL instalado
- [ ] HTTPS forzado (HTTP redirect)
- [ ] HSTS header configurado
- [ ] SSL A+ en SSLLabs
- [ ] Auto-renovación configurada

### Aplicación

- [ ] APP_DEBUG=false en producción
- [ ] APP_KEY único generado
- [ ] Permisos de archivos correctos
- [ ] .env protegido (chmod 600)
- [ ] Security headers configurados
- [ ] CSRF protection activo
- [ ] Rate limiting configurado

### Mail Server

- [ ] SPF record configurado
- [ ] DKIM keys generados y publicados
- [ ] DMARC record configurado
- [ ] Reverse DNS (PTR) configurado
- [ ] TLS en Postfix configurado
- [ ] Rate limiting en Postfix
- [ ] RBL checks activos

### Monitoreo

- [ ] Logs rotando correctamente
- [ ] Backups automáticos configurados
- [ ] Monitoreo de servicios
- [ ] Alertas de Fail2Ban
- [ ] Chequeo de SSL expiry

### Tests

- [ ] mail-tester.com score > 9/10
- [ ] SSLLabs score A+
- [ ] No en blacklists (MXToolbox)
- [ ] DKIM signature válida
- [ ] SPF pass
- [ ] DMARC pass

---

## 🆘 Respuesta a Incidentes

### Si detectas actividad sospechosa:

1. **Verificar logs inmediatamente**
   ```bash
   sudo tail -100 /var/log/auth.log
   sudo tail -100 /var/log/mail.log
   ```

2. **Banear IP sospechosa**
   ```bash
   sudo ufw deny from IP_SOSPECHOSA
   sudo fail2ban-client set sshd banip IP_SOSPECHOSA
   ```

3. **Revisar usuarios activos**
   ```bash
   who
   w
   last
   ```

4. **Revisar procesos sospechosos**
   ```bash
   ps aux | grep -v root
   top
   ```

5. **Revisar conexiones de red**
   ```bash
   sudo netstat -tulpn
   sudo ss -tulpn
   ```

6. **Cambiar contraseñas críticas**
   ```bash
   sudo passwd usuario
   # Cambiar passwords de DB
   # Rotar APP_KEY si es necesario
   ```

7. **Revisar archivos modificados recientemente**
   ```bash
   sudo find /var/www/mailcore -type f -mtime -1
   ```

8. **Auditoría completa**
   ```bash
   sudo apt install -y lynis
   sudo lynis audit system
   ```

---

## 📚 Recursos

- [OWASP Top 10](https://owasp.org/www-project-top-ten/)
- [Laravel Security Best Practices](https://laravel.com/docs/security)
- [Mozilla SSL Configuration Generator](https://ssl-config.mozilla.org/)
- [Postfix Security](http://www.postfix.org/BASIC_CONFIGURATION_README.html)
- [Fail2Ban Documentation](https://www.fail2ban.org/wiki/index.php/Main_Page)

---

**Última actualización**: 2024
**Versión**: 1.0
