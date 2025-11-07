# 📊 MailCore - Configuración de Servicios y Accesos

Esta guía contiene toda la información sobre usuarios, contraseñas, configuración de colas, workers y monitoreo de servicios.

## 📋 Tabla de Contenidos

1. [Usuarios y Contraseñas](#usuarios-y-contraseñas)
2. [Health Check / Status Page](#health-check--status-page)
3. [Configuración de Colas (Queues)](#configuración-de-colas-queues)
4. [Workers y Supervisor](#workers-y-supervisor)
5. [Monitoreo de Servicios](#monitoreo-de-servicios)
6. [Comandos Útiles](#comandos-útiles)

---

## 🔐 Usuarios y Contraseñas

### 1. Usuario Administrador de MailCore (Panel Web)

El usuario administrador se crea durante el despliegue.

**Crear usuario administrador:**
```bash
cd /var/www/mailcore
php artisan make:filament-user
```

Se te pedirá:
- **Nombre**: Tu nombre
- **Email**: tu@email.com
- **Password**: Tu contraseña segura

**Ubicación del panel:**
```
URL: https://mail.tudominio.com/admin
```

**Nota**: Las contraseñas se almacenan encriptadas con Argon2ID (bcrypt).

---

### 2. Base de Datos MySQL

**Durante la instalación se configuran estos datos:**

```bash
# Usuario root de MySQL
Usuario: root
Contraseña: La que configuraste durante mysql_secure_installation

# Usuario de MailCore
Usuario: mailcore
Contraseña: La que configuraste en el script de instalación
Base de datos: mailcore
Host: 127.0.0.1
Puerto: 3306
```

**Ubicación de la configuración:**
```
Archivo: /var/www/mailcore/.env

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mailcore
DB_USERNAME=mailcore
DB_PASSWORD=TU_PASSWORD_AQUI
```

**Conectar a MySQL:**
```bash
# Como usuario mailcore
mysql -u mailcore -p mailcore

# Como root
sudo mysql

# Desde phpMyAdmin (si lo instalaste)
https://mail.tudominio.com:8080/phpmyadmin
```

---

### 3. Postfix (Servidor de Correo)

**Archivos de configuración MySQL:**
```bash
/etc/postfix/mysql-virtual-mailbox-domains.cf
/etc/postfix/mysql-virtual-mailbox-maps.cf
/etc/postfix/mysql-virtual-alias-maps.cf
```

**Cada archivo contiene:**
```
user = mailcore
password = TU_PASSWORD_MYSQL
hosts = 127.0.0.1
dbname = mailcore
```

**Permisos:**
```bash
sudo chmod 640 /etc/postfix/mysql-*.cf
sudo chown root:postfix /etc/postfix/mysql-*.cf
```

---

### 4. Dovecot (IMAP/POP3)

**Archivo de configuración SQL:**
```
/etc/dovecot/dovecot-sql.conf.ext
```

**Contenido:**
```
driver = mysql
connect = host=127.0.0.1 dbname=mailcore user=mailcore password=TU_PASSWORD_MYSQL
default_pass_scheme = ARGON2ID
```

**Permisos:**
```bash
sudo chmod 640 /etc/dovecot/dovecot-sql.conf.ext
sudo chown root:dovecot /etc/dovecot/dovecot-sql.conf.ext
```

---

### 5. Usuario vmail (Almacenamiento de Correos)

```bash
Usuario: vmail
UID: 5000
GID: 5000
Home: /var/mail/vmail
```

**Creado automáticamente durante la instalación:**
```bash
sudo groupadd -g 5000 vmail
sudo useradd -g vmail -u 5000 vmail -d /var/mail/vmail -m
```

---

### 6. Claves de Aplicación

**APP_KEY (Laravel):**
```bash
# En .env
APP_KEY=base64:XXXXXXXXXXXXXXXXXXXXX

# Generar nueva clave (solo en nueva instalación)
php artisan key:generate
```

**ADVERTENCIA**: Nunca regeneres APP_KEY en producción con datos existentes, romperá el cifrado.

---

### 7. API Tokens (Sanctum)

Los tokens de API se generan desde el panel de administración.

**Generar token desde código:**
```php
$user = User::find(1);
$token = $user->createToken('api-token')->plainTextToken;
```

**Usar en API:**
```bash
curl -H "Authorization: Bearer TOKEN_AQUI" \
     https://mail.tudominio.com/api/send
```

---

## 📈 Health Check / Status Page

### Acceder a la Página de Estado

```bash
# Página visual interactiva
https://mail.tudominio.com/status

# API JSON
https://mail.tudominio.com/api/health
```

### ¿Qué Verifica?

La página de estado verifica:

1. **Application** (Laravel)
   - Estado de la aplicación
   - Versión de PHP
   - Versión de Laravel
   - Entorno (production/staging)
   - Debug mode

2. **Database** (MySQL)
   - Conexión a base de datos
   - Número de tablas
   - Estadísticas (dominios, mailboxes, emails enviados)

3. **Redis**
   - Conexión a Redis
   - Versión
   - Uptime
   - Clientes conectados
   - Memoria usada

4. **Cache**
   - Operaciones de lectura/escritura
   - Driver configurado

5. **Queue System**
   - Conexión de colas
   - Jobs pendientes
   - Jobs fallidos

6. **Storage**
   - Permisos de escritura
   - Espacio en disco
   - Uso de disco

7. **Mail Services**
   - Postfix (SMTP)
   - Dovecot (IMAP/POP3)
   - OpenDKIM
   - OpenDMARC

### Auto-refresh

La página se auto-refresca cada **60 segundos**.

### API Response

```json
{
  "status": "healthy",
  "timestamp": "2024-11-07T10:30:00Z",
  "services": {
    "application": {
      "name": "Application",
      "status": "healthy",
      "message": "Laravel application is running",
      "details": { ... }
    },
    "database": {
      "name": "Database",
      "status": "healthy",
      "message": "Database connection successful",
      "details": { ... }
    },
    ...
  }
}
```

### Códigos de Estado HTTP

- **200**: All systems healthy
- **503**: Some services degraded

### Usar en Monitoreo Externo

```bash
# Uptime Robot
URL: https://mail.tudominio.com/api/health
Método: GET
Esperado: 200 OK

# Check específico con curl
curl -f https://mail.tudominio.com/api/health || echo "Service down!"
```

---

## 🔄 Configuración de Colas (Queues)

### ¿Qué son las Colas?

Las colas (queues) permiten procesar tareas en segundo plano sin bloquear la aplicación web.

### Colas en MailCore

MailCore usa colas para:

1. **Envío de emails** (prioridad alta)
2. **Procesamiento de bounces** (prioridad media)
3. **Generación de reportes** (prioridad baja)
4. **Logs y análisis** (prioridad baja)
5. **Limpieza de datos** (prioridad baja)

### Configuración de Colas

**Archivo:** `/var/www/mailcore/.env`

```bash
# Driver de colas (redis recomendado)
QUEUE_CONNECTION=redis

# Redis configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Colas Disponibles

```php
// config/queue.php

'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],
```

### Colas Predefinidas

- **default**: Cola principal para emails y tareas generales
- **high**: Prioridad alta (envío de emails urgentes)
- **low**: Prioridad baja (limpieza, logs)
- **notifications**: Notificaciones del sistema

### Enviar Job a Cola Específica

```php
// Enviar a cola por defecto
SendEmailJob::dispatch($emailData);

// Enviar a cola específica
SendEmailJob::dispatch($emailData)->onQueue('high');

// Delay
SendEmailJob::dispatch($emailData)->delay(now()->addMinutes(5));

// Con prioridad
SendEmailJob::dispatch($emailData)->onQueue('high')->delay(0);
```

### Ver Estado de las Colas

```bash
# Ver jobs en cola (Redis)
redis-cli LLEN queues:default
redis-cli LLEN queues:high
redis-cli LLEN queues:low

# Ver failed jobs
cd /var/www/mailcore
php artisan queue:failed

# Ver detalles de un job fallido
php artisan queue:failed:show JOB_ID
```

### Reintentar Jobs Fallidos

```bash
# Reintentar todos
php artisan queue:retry all

# Reintentar job específico
php artisan queue:retry JOB_ID

# Limpiar jobs fallidos
php artisan queue:flush
```

---

## 👷 Workers y Supervisor

### ¿Qué es Supervisor?

Supervisor es un sistema de control de procesos que mantiene los workers de Laravel corriendo.

### Configuración de Supervisor

**Archivo:** `/etc/supervisor/conf.d/mailcore-worker.conf`

```ini
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
```

### Parámetros Explicados

- **numprocs=4**: 4 workers corriendo en paralelo
- **--sleep=3**: 3 segundos de espera si no hay jobs
- **--tries=3**: Reintentar jobs 3 veces si fallan
- **--max-time=3600**: Reiniciar worker cada hora (previene memory leaks)
- **--timeout=300**: Timeout de 5 minutos por job
- **user=www-data**: Usuario que ejecuta los workers
- **autostart=true**: Iniciar automáticamente al boot
- **autorestart=true**: Reiniciar si se cae

### Comandos de Supervisor

```bash
# Ver estado de workers
sudo supervisorctl status

# Iniciar workers
sudo supervisorctl start mailcore-worker:*

# Detener workers
sudo supervisorctl stop mailcore-worker:*

# Reiniciar workers
sudo supervisorctl restart mailcore-worker:*

# Recargar configuración
sudo supervisorctl reread
sudo supervisorctl update

# Ver logs en tiempo real
sudo tail -f /var/www/mailcore/storage/logs/worker.log
```

### Escalar Workers

Si necesitas más workers para mayor carga:

```bash
# Editar configuración
sudo nano /etc/supervisor/conf.d/mailcore-worker.conf

# Cambiar numprocs
numprocs=8  # De 4 a 8 workers

# Recargar
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart mailcore-worker:*
```

### Workers Dedicados por Cola

Para mejor rendimiento, puedes crear workers dedicados:

```bash
# Crear nuevo archivo
sudo nano /etc/supervisor/conf.d/mailcore-worker-high.conf
```

```ini
[program:mailcore-worker-high]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/mailcore/artisan queue:work redis --queue=high --sleep=1 --tries=3 --max-time=3600
user=www-data
numprocs=2
autostart=true
autorestart=true
stdout_logfile=/var/www/mailcore/storage/logs/worker-high.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
```

---

## 📊 Monitoreo de Servicios

### 1. Monitoreo Web (Status Page)

```bash
# Acceder
https://mail.tudominio.com/status
```

- Auto-refresh cada 60 segundos
- Vista visual de todos los servicios
- Detalles técnicos de cada servicio

### 2. Monitoreo API

```bash
# Endpoint
curl https://mail.tudominio.com/api/health

# Con autenticación (opcional)
curl -H "Authorization: Bearer TOKEN" \
     https://mail.tudominio.com/api/health

# Solo código de estado
curl -o /dev/null -s -w "%{http_code}\n" \
     https://mail.tudominio.com/api/health
```

### 3. Script de Monitoreo

Creado automáticamente durante la instalación:

```bash
# Ejecutar manualmente
sudo /usr/local/bin/mailcore-status.sh

# Ver solo un servicio
systemctl status postfix
systemctl status dovecot
systemctl status opendkim
systemctl status nginx
systemctl status php8.2-fpm
systemctl status mysql
systemctl status redis
```

### 4. Monitoreo de Workers

```bash
# Estado de workers
sudo supervisorctl status mailcore-worker:*

# Ver si están procesando
ps aux | grep "queue:work"

# Ver cuántos jobs procesados
# (revisar logs)
tail -100 /var/www/mailcore/storage/logs/worker.log | grep "Processed"
```

### 5. Monitoreo de Colas

```bash
# Jobs pendientes en todas las colas
cd /var/www/mailcore
php artisan queue:work --once --stop-when-empty

# Jobs fallidos
php artisan queue:failed

# Listar todos los jobs en Redis
redis-cli
> KEYS queues:*
> LLEN queues:default
```

### 6. Monitoreo de Mail

```bash
# Ver cola de Postfix
sudo mailq

# Ver últimos 50 emails enviados
sudo tail -50 /var/log/mail.log

# Buscar emails rechazados
sudo grep "reject" /var/log/mail.log | tail -20

# Ver bounces
sudo grep "bounced" /var/log/mail.log | tail -20

# Estadísticas de envío (última hora)
sudo grep "$(date +%b\ %d\ %H)" /var/log/mail.log | grep "status=sent" | wc -l
```

### 7. Monitoreo de Recursos

```bash
# CPU y memoria
htop

# Uso de disco
df -h

# Conexiones de red
sudo netstat -tupln | grep LISTEN

# Ver procesos de MailCore
ps aux | grep -E "php|nginx|mysql|redis|postfix|dovecot"

# Memoria de Redis
redis-cli info memory

# Tamaño de base de datos
sudo mysql -e "SELECT table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.tables
WHERE table_schema = 'mailcore'
GROUP BY table_schema;"
```

### 8. Logs Importantes

```bash
# Laravel (aplicación)
tail -f /var/www/mailcore/storage/logs/laravel.log

# Workers (colas)
tail -f /var/www/mailcore/storage/logs/worker.log

# Nginx access
tail -f /var/log/nginx/mailcore-access.log

# Nginx errors
tail -f /var/log/nginx/mailcore-error.log

# Mail (Postfix + Dovecot)
tail -f /var/log/mail.log

# Auth (SSH, logins)
tail -f /var/log/auth.log

# Fail2Ban
tail -f /var/log/fail2ban.log

# UFW (firewall)
tail -f /var/log/ufw.log

# MySQL
tail -f /var/log/mysql/error.log

# PHP-FPM
tail -f /var/log/php8.2-fpm.log
```

### 9. Alertas Automáticas

**Configurar alertas por email:**

```bash
# Crear script de alertas
sudo nano /usr/local/bin/mailcore-alerts.sh
```

```bash
#!/bin/bash

ADMIN_EMAIL="admin@tudominio.com"

# Check if workers are running
WORKER_COUNT=$(sudo supervisorctl status mailcore-worker:* | grep RUNNING | wc -l)

if [ "$WORKER_COUNT" -lt 4 ]; then
    echo "WARNING: Only $WORKER_COUNT workers running!" | \
        mail -s "MailCore Alert: Workers Down" $ADMIN_EMAIL
fi

# Check disk space
DISK_USAGE=$(df -h / | tail -1 | awk '{print $5}' | sed 's/%//')

if [ "$DISK_USAGE" -gt 80 ]; then
    echo "WARNING: Disk usage at $DISK_USAGE%!" | \
        mail -s "MailCore Alert: Disk Space" $ADMIN_EMAIL
fi

# Check failed jobs
FAILED_JOBS=$(cd /var/www/mailcore && php artisan queue:failed --format=json | jq '. | length')

if [ "$FAILED_JOBS" -gt 10 ]; then
    echo "WARNING: $FAILED_JOBS failed jobs!" | \
        mail -s "MailCore Alert: Failed Jobs" $ADMIN_EMAIL
fi
```

```bash
sudo chmod +x /usr/local/bin/mailcore-alerts.sh

# Ejecutar cada 30 minutos
(sudo crontab -l 2>/dev/null; echo "*/30 * * * * /usr/local/bin/mailcore-alerts.sh") | sudo crontab -
```

### 10. Integración con Servicios Externos

**UptimeRobot:**
```
URL: https://mail.tudominio.com/api/health
Type: Keyword Monitor
Keyword: "healthy"
Check interval: 5 minutes
```

**Pingdom:**
```
URL: https://mail.tudominio.com/status
Check: HTTP
Expected: 200 OK
```

**New Relic / DataDog:**
```bash
# Instalar agente según documentación del servicio
# Configurar para monitorear:
# - PHP-FPM
# - Nginx
# - MySQL
# - Redis
```

---

## 🛠️ Comandos Útiles

### Aplicación Laravel

```bash
cd /var/www/mailcore

# Ver configuración
php artisan config:show

# Limpiar cachés
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimizar para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Verificar estado de la aplicación
php artisan about

# Ejecutar migraciones
php artisan migrate

# Ver rutas
php artisan route:list

# Crear usuario admin
php artisan make:filament-user
```

### Colas y Workers

```bash
# Procesar un job
php artisan queue:work --once

# Procesar jobs de cola específica
php artisan queue:work redis --queue=high

# Ver jobs fallidos
php artisan queue:failed

# Reintentar todos los fallidos
php artisan queue:retry all

# Limpiar jobs fallidos
php artisan queue:flush

# Monitorear colas en tiempo real
php artisan queue:monitor redis:default --max=100
```

### Base de Datos

```bash
# Conectar a MySQL
mysql -u mailcore -p mailcore

# Backup
mysqldump -u mailcore -p mailcore > backup.sql

# Restore
mysql -u mailcore -p mailcore < backup.sql

# Ver tablas
mysql -u mailcore -p -e "USE mailcore; SHOW TABLES;"

# Estadísticas
mysql -u mailcore -p -e "
    SELECT 'Domains' as table_name, COUNT(*) as count FROM mailcore.domains
    UNION
    SELECT 'Mailboxes', COUNT(*) FROM mailcore.mailboxes
    UNION
    SELECT 'Sent Emails', COUNT(*) FROM mailcore.send_logs;
"
```

### Redis

```bash
# Conectar a Redis
redis-cli

# Ver todas las keys
redis-cli KEYS '*'

# Ver info
redis-cli INFO

# Limpiar caché
redis-cli FLUSHDB

# Ver tamaño de colas
redis-cli LLEN queues:default

# Monitorear comandos
redis-cli MONITOR
```

### Servicios de Mail

```bash
# Ver cola de Postfix
sudo mailq

# Vaciar cola
sudo postsuper -d ALL

# Reiniciar servicios
sudo systemctl restart postfix
sudo systemctl restart dovecot
sudo systemctl restart opendkim
sudo systemctl restart opendmarc

# Test SMTP
telnet localhost 25

# Ver conexiones activas
sudo netstat -tupln | grep -E "25|587|993|995"
```

### Nginx y PHP

```bash
# Test configuración
sudo nginx -t

# Recargar Nginx
sudo systemctl reload nginx

# Reiniciar PHP-FPM
sudo systemctl restart php8.2-fpm

# Ver procesos PHP-FPM
ps aux | grep php-fpm

# Ver conexiones
sudo netstat -tupln | grep -E "80|443"
```

### Logs

```bash
# Ver logs en tiempo real
tail -f /var/www/mailcore/storage/logs/laravel.log
tail -f /var/log/nginx/mailcore-error.log
tail -f /var/log/mail.log

# Buscar en logs
grep "error" /var/www/mailcore/storage/logs/laravel.log
grep "rejected" /var/log/mail.log

# Ver últimas 100 líneas
tail -100 /var/log/mail.log

# Buscar por fecha
grep "$(date +%b\ %d)" /var/log/mail.log
```

---

## 🔑 Resumen de Credenciales

| Servicio | Usuario | Archivo de Configuración |
|----------|---------|--------------------------|
| Panel Web Admin | Tu email configurado | Base de datos (users table) |
| MySQL Database | mailcore | `/var/www/mailcore/.env` |
| Postfix | mailcore | `/etc/postfix/mysql-*.cf` |
| Dovecot | mailcore | `/etc/dovecot/dovecot-sql.conf.ext` |
| Usuario vmail | vmail (UID 5000) | Sistema operativo |
| API Tokens | Via Sanctum | Panel de admin o código |

---

## 📊 URLs de Acceso

| Servicio | URL |
|----------|-----|
| Panel de Administración | https://mail.tudominio.com/admin |
| Status Page (Web) | https://mail.tudominio.com/status |
| Health Check API | https://mail.tudominio.com/api/health |
| API de Envío | https://mail.tudominio.com/api/send |
| Unsubscribe | https://mail.tudominio.com/unsubscribe/{token} |

---

## 🚨 Troubleshooting Rápido

### Workers no procesan jobs

```bash
# Verificar que están corriendo
sudo supervisorctl status mailcore-worker:*

# Reiniciar
sudo supervisorctl restart mailcore-worker:*

# Ver logs
tail -f /var/www/mailcore/storage/logs/worker.log
```

### Emails no se envían

```bash
# Ver cola de Postfix
sudo mailq

# Ver logs
tail -f /var/log/mail.log

# Test de Postfix
echo "Test" | mail -s "Test" destino@example.com
```

### Status page muestra servicios caídos

```bash
# Verificar servicios
sudo /usr/local/bin/mailcore-status.sh

# Reiniciar servicios específicos
sudo systemctl restart SERVICIO
```

### Alta carga de CPU

```bash
# Ver procesos
htop

# Ver workers
ps aux | grep queue:work

# Reducir número de workers si es necesario
sudo nano /etc/supervisor/conf.d/mailcore-worker.conf
# Cambiar numprocs
sudo supervisorctl update
```

---

**Última actualización**: 2024-11-07
**Versión**: 1.0
