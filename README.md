# 📧 MailCore

**Sistema de correo autogestionado para envío transaccional y gestión de buzones**

MailCore es una plataforma completa de gestión de correo electrónico autohospedada, diseñada para envío transaccional y administración de buzones de múltiples dominios. Incluye servidor SMTP propio (Postfix), autenticación completa (SPF, DKIM, DMARC), panel de administración moderno (Laravel + Filament), y API REST.

---

## ✨ Características Principales

### 🏗️ Infraestructura
- ✅ Servidor SMTP propio (Postfix + Dovecot)
- ✅ Autenticación SPF, DKIM y DMARC
- ✅ TLS automático con Let's Encrypt
- ✅ IP dedicada con PTR configurado
- ✅ Protección con Fail2ban y UFW

### 🎛️ Panel de Administración (Filament 3)
- ✅ Dashboard con estadísticas en tiempo real
- ✅ Gestión de dominios con verificación DNS automática
- ✅ Gestión de buzones SMTP
- ✅ Logs de envíos con filtros avanzados
- ✅ Gestión de rebotes y supresión
- ✅ Sistema de roles y permisos
- ✅ Generación automática de claves DKIM

### 📊 Monitorización
- ✅ Métricas de envío (exitosos, fallidos, rebotes)
- ✅ Parseo automático de logs Postfix
- ✅ Detección y categorización de bounces
- ✅ Gráficos de envíos históricos
- ✅ Registro de actividad del sistema

### 🔌 API REST
- ✅ Envío de correos simples y en lote
- ✅ Autenticación con Laravel Sanctum
- ✅ Rate limiting configurable
- ✅ Modo sandbox para pruebas
- ✅ Documentación completa

### 🛡️ Seguridad
- ✅ Autenticación de dos factores (2FA)
- ✅ Sistema de roles y permisos
- ✅ Límites de envío por buzón
- ✅ Cuotas de almacenamiento
- ✅ Protección contra spam

---

## 📋 Requisitos

- **Servidor**: Ubuntu 22.04+ LTS
- **CPU**: 4 vCPU mínimo
- **RAM**: 8GB mínimo
- **Disco**: 50GB SSD
- **IP**: Dedicada con PTR configurado
- **Dominio**: Con acceso a gestión DNS

---

## 🚀 Instalación Rápida

```bash
# 1. Clonar repositorio
cd /var/www
git clone https://github.com/tuusuario/mailcore.git
cd mailcore

# 2. Ejecutar instalador automático
sudo bash install_mailcore.sh

# 3. Instalar dependencias Laravel
composer install --no-dev --optimize-autoloader

# 4. Configurar entorno
cp .env.example .env
nano .env

# 5. Generar clave de aplicación
php artisan key:generate

# 6. Migrar base de datos
php artisan migrate

# 7. Crear usuario administrador
php artisan make:filament-user
```

Para instrucciones detalladas, consulta [INSTALLATION.md](INSTALLATION.md).

---

## 🧪 Testing Local

¿Quieres probar el proyecto en local sin servidor de correo?

```bash
# Setup automático
bash setup-local.sh
```

Más opciones en [QUICKSTART.md](QUICKSTART.md) y [TESTING.md](TESTING.md).

---

## 📖 Documentación

- [⚡ QuickStart Local](QUICKSTART.md) - Inicio rápido en 5 minutos
- [🧪 Guía de Testing](TESTING.md) - Testing completo local y Docker
- [📘 Guía de Instalación](INSTALLATION.md) - Instalación completa paso a paso
- [🔌 Documentación de API](API.md) - Endpoints y ejemplos de uso
- [⚙️ Configuración DNS](INSTALLATION.md#-configuración-dns) - Registros DNS requeridos

---

## 🎯 Casos de Uso

- **Notificaciones transaccionales**: Confirmaciones de registro, recuperación de contraseña, alertas
- **Email marketing**: Newsletters, campañas
- **Aplicaciones SaaS**: Sistema de notificaciones para tus aplicaciones
- **Múltiples proyectos**: Gestión centralizada de correos para varios dominios

---

## 🖥️ Panel de Administración

### Dashboard
- Estadísticas en tiempo real
- Gráficos de envíos de últimos 30 días
- Tasa de éxito/fallos
- Últimos envíos

### Módulos Principales

| Módulo | Funcionalidad |
|--------|---------------|
| **Dominios** | Alta, verificación DNS automática, generador DKIM |
| **Buzones** | Gestión de cuentas SMTP, cuotas, límites diarios |
| **Envíos** | Logs completos, filtros avanzados, parseo Postfix |
| **Rebotes** | Control de errores SMTP, parser de bounces, supresión |
| **Logs** | Registro de actividad del sistema |
| **Configuración** | Parámetros globales del sistema |
| **Usuarios** | Gestión de usuarios, roles, 2FA |

---

## 🔧 Comandos Artisan

```bash
# Parsear logs de Postfix
php artisan mailcore:parse-logs

# Verificar dominios (SPF, DKIM, DMARC)
php artisan mailcore:verify-domains

# Verificar rebotes
php artisan mailcore:check-bounces

# Generar claves DKIM
php artisan mailcore:generate-dkim tudominio.com

# Limpiar logs antiguos
php artisan mailcore:cleanup-old-logs --days=90
```

---

## 📡 API REST

### Enviar Email Simple

```bash
curl -X POST https://mail.tudominio.com/api/send \
  -H "Authorization: Bearer tu-token" \
  -H "Content-Type: application/json" \
  -d '{
    "from": "noreply@tudominio.com",
    "to": "usuario@ejemplo.com",
    "subject": "Asunto",
    "body": "Contenido del correo"
  }'
```

### Envío en Lote

```bash
curl -X POST https://mail.tudominio.com/api/send/bulk \
  -H "Authorization: Bearer tu-token" \
  -H "Content-Type: application/json" \
  -d '{
    "emails": [
      {
        "from": "noreply@tudominio.com",
        "to": "usuario1@ejemplo.com",
        "subject": "Email 1",
        "body": "Contenido 1"
      },
      {
        "from": "noreply@tudominio.com",
        "to": "usuario2@ejemplo.com",
        "subject": "Email 2",
        "body": "Contenido 2"
      }
    ]
  }'
```

Ver [API.md](API.md) para documentación completa.

---

## 📊 Arquitectura

### Componentes

```
┌─────────────────────────────────────────────┐
│          Frontend (Filament 3)              │
│  Dashboard | Dominios | Buzones | Envíos   │
└─────────────────┬───────────────────────────┘
                  │
┌─────────────────▼───────────────────────────┐
│        Backend (Laravel 11)                 │
│  API REST | Services | Commands | Jobs      │
└─────────────────┬───────────────────────────┘
                  │
┌─────────────────▼───────────────────────────┐
│          Mail Server Stack                  │
│  Postfix | Dovecot | OpenDKIM | OpenDMARC  │
└─────────────────┬───────────────────────────┘
                  │
┌─────────────────▼───────────────────────────┐
│       Database (MySQL/PostgreSQL)           │
│  Domains | Mailboxes | Logs | Bounces      │
└─────────────────────────────────────────────┘
```

### Base de Datos

- **domains**: Gestión de dominios y verificación DNS
- **mailboxes**: Buzones virtuales con cuotas y límites
- **send_logs**: Registro completo de envíos
- **bounces**: Gestión de rebotes y supresiones
- **activity_logs**: Auditoría del sistema
- **users**: Usuarios del panel

---

## 🔐 Configuración DNS

### Registros Requeridos

```dns
; MX Record
@           IN  MX  10  mail.tudominio.com.

; A Record
mail        IN  A       TU_IP

; SPF Record
@           IN  TXT     "v=spf1 a mx ip4:TU_IP -all"

; DKIM Record (generado automáticamente)
default._domainkey  IN  TXT  "v=DKIM1; k=rsa; p=MIIBIjANBgkq..."

; DMARC Record
_dmarc      IN  TXT     "v=DMARC1; p=none; rua=mailto:dmarc@tudominio.com"
```

---

## 🛠️ Desarrollo

### Stack Tecnológico

- **Backend**: Laravel 11
- **Frontend**: Filament 3 (Livewire + Alpine.js)
- **Database**: MySQL 8 / PostgreSQL 14+
- **Mail Server**: Postfix + Dovecot
- **Authentication**: OpenDKIM + OpenDMARC
- **TLS**: Let's Encrypt (Certbot)
- **Queue**: Redis (opcional) / Database
- **Cache**: Redis / Database

### Requisitos de Desarrollo

```bash
# PHP >= 8.2
php -v

# Composer
composer --version

# Node.js >= 18 (para assets)
node -v
npm -v
```

### Instalación para Desarrollo

```bash
# Clonar repo
git clone https://github.com/tuusuario/mailcore.git
cd mailcore

# Instalar dependencias
composer install
npm install

# Configurar entorno
cp .env.example .env
php artisan key:generate

# Migrar base de datos
php artisan migrate

# Compilar assets
npm run dev

# Levantar servidor
php artisan serve
```

---

## 🧪 Testing

```bash
# Ejecutar pruebas
php artisan test

# Verificar instalación
php artisan mailcore:verify-domains

# Probar envío
echo "Test" | mail -s "Test MailCore" tuemail@ejemplo.com
```

---

## 🚦 Monitorización

### Logs Importantes

```bash
# Logs de Postfix
tail -f /var/log/mail.log

# Logs de Laravel
tail -f storage/logs/laravel.log

# Logs de Nginx
tail -f /var/log/nginx/error.log
```

### Verificar Servicios

```bash
systemctl status postfix dovecot opendkim opendmarc nginx php8.2-fpm
```

---

## 🔮 Roadmap

### Próximas Características

- [ ] Dashboard multi-servidor
- [ ] Notificaciones Telegram en fallos
- [ ] Métricas de apertura y clicks
- [ ] Alertas de reputación (blacklists)
- [ ] Exportación de métricas (CSV/JSON)
- [ ] Templates de emails
- [ ] Webhooks para eventos
- [ ] Integración con proveedores externos

---

## 📄 Licencia

Este proyecto es de uso interno y no tiene fines comerciales.

---

## 👨‍💻 Autor

Desarrollado para gestión interna de proyectos.

---

## 🤝 Contribuir

Si encuentras bugs o tienes sugerencias:

1. Abre un issue
2. Describe el problema o mejora
3. Si es posible, incluye logs relevantes

---

## 📞 Soporte

Para problemas técnicos:

1. Revisa la documentación en `/docs`
2. Verifica logs del sistema
3. Consulta [INSTALLATION.md](INSTALLATION.md) para troubleshooting

---

## ⚠️ Avisos Importantes

### Reputación de IP

- Usa una IP dedicada con PTR configurado
- Evita enviar spam
- Configura correctamente SPF, DKIM y DMARC
- Monitorea blacklists regularmente

### Límites Recomendados

- Calentamiento de IP: Incrementa volumen gradualmente
- Día 1-3: 50-100 emails/día
- Día 4-7: 200-500 emails/día
- Día 8-14: 1000-2000 emails/día
- Día 15+: Sin límite (respetando buenas prácticas)

### Backups

```bash
# Backup de base de datos
mysqldump mailcore > backup_$(date +%Y%m%d).sql

# Backup de configuración
tar -czf mailcore_config_$(date +%Y%m%d).tar.gz \
  /etc/postfix \
  /etc/dovecot \
  /etc/opendkim \
  /var/www/mailcore/.env
```

---

## 🎉 ¡Gracias por usar MailCore!

Si este proyecto te ha sido útil, considera:
- ⭐ Darle una estrella en GitHub
- 🐛 Reportar bugs
- 💡 Sugerir mejoras
- 📖 Mejorar la documentación
