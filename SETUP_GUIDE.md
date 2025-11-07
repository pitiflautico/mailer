# MailCore - Guía de Configuración Completa

Esta guía te ayudará a configurar completamente tu servidor de correo con MailCore.

## 📋 Índice

1. [Requisitos Previos](#requisitos-previos)
2. [Configuración de la Base de Datos](#configuración-de-la-base-de-datos)
3. [Configuración de Correo](#configuración-de-correo)
4. [Crear Dominio y Buzones](#crear-dominio-y-buzones)
5. [Configurar DNS](#configurar-dns)
6. [Enviar Correos de Prueba](#enviar-correos-de-prueba)
7. [Verificación y Troubleshooting](#verificación-y-troubleshooting)

---

## 1. Requisitos Previos

- PHP 8.2+
- MySQL/MariaDB
- Postfix (servidor SMTP)
- Dovecot (servidor IMAP/POP3)
- OpenDKIM
- Servidor web (Nginx/Apache)
- Acceso SSH al servidor
- Dominio con acceso al panel de DNS

---

## 2. Configuración de la Base de Datos

### Ejecutar Migraciones

```bash
cd /ruta/a/tu/proyecto
php artisan migrate --force
```

Las migraciones crearán todas las tablas necesarias:
- `domains` - Dominios de correo
- `mailboxes` - Buzones de correo
- `send_logs` - Registro de correos enviados
- `mail_events` - Eventos de correo
- Y más...

---

## 3. Configuración de Correo

### Verificar el archivo `.env`

Asegúrate de que tu archivo `.env` tenga estas configuraciones:

```env
# Configuración de correo SMTP
MAIL_MAILER=smtp
MAIL_HOST=localhost
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@tudominio.com"
MAIL_FROM_NAME="MailCore"

# Configuración de MailCore
MAILCORE_HOSTNAME=mail.tudominio.com
MAILCORE_IP=tu.ip.del.servidor
MAILCORE_DKIM_PATH=/etc/opendkim/keys
```

### Verificar permisos del directorio DKIM

```bash
# Asegúrate de que el directorio existe y tiene permisos correctos
sudo mkdir -p /etc/opendkim/keys
sudo chown -R www-data:www-data /etc/opendkim
sudo chmod -R 755 /etc/opendkim
```

---

## 4. Crear Dominio y Buzones

### 4.1 Accede al Panel de Administración

1. Ve a `https://tudominio.com/admin`
2. Inicia sesión con tus credenciales

### 4.2 Crear un Dominio

1. Ve a **Dominios** en el menú lateral
2. Haz clic en **Nuevo Dominio**
3. Ingresa el nombre del dominio (ej: `tudominio.com`)
4. El selector DKIM se autocompletará con `default`
5. Haz clic en **Crear**

**¡Las claves DKIM se generarán automáticamente!** 🎉

### 4.3 Ver los Registros DNS

1. En la lista de dominios, busca tu dominio
2. Haz clic en el botón **"Ver Registros DNS"**
3. Copia los valores que aparecen (los necesitarás para el siguiente paso)

### 4.4 Crear un Buzón

1. Ve a **Buzones** en el menú lateral
2. Haz clic en **Nuevo Buzón**
3. Selecciona el dominio que creaste
4. Ingresa el local part (ej: `noreply` para crear `noreply@tudominio.com`)
5. Establece una contraseña
6. Configura la cuota (ej: 1024 MB)
7. Haz clic en **Crear**

---

## 5. Configurar DNS

Ahora debes agregar los registros DNS en tu proveedor (Cloudflare, GoDaddy, etc.):

### SPF (Sender Policy Framework)

```
Tipo: TXT
Nombre: @
Valor: v=spf1 ip4:TU_IP a mx -all
TTL: 3600
```

Reemplaza `TU_IP` con la IP de tu servidor.

### DKIM (DomainKeys Identified Mail)

```
Tipo: TXT
Nombre: default._domainkey
Valor: v=DKIM1; k=rsa; p=MIIBIjANBg... (el valor que copiaste del panel)
TTL: 3600
```

**Importante**: Copia el valor completo de DKIM desde el panel.

### DMARC (Domain-based Message Authentication)

```
Tipo: TXT
Nombre: _dmarc
Valor: v=DMARC1; p=none; rua=mailto:dmarc@tudominio.com
TTL: 3600
```

### MX (Mail Exchange) - Opcional

Si quieres recibir correos:

```
Tipo: MX
Nombre: @
Prioridad: 10
Valor: mail.tudominio.com
TTL: 3600
```

### A Record para mail.tudominio.com

```
Tipo: A
Nombre: mail
Valor: TU_IP
TTL: 3600
```

---

## 6. Enviar Correos de Prueba

### 6.1 Usando el Panel de Administración (Recomendado)

1. Ve a **"Enviar Correo de Prueba"** en el menú lateral 📧
2. Selecciona un buzón de la lista
3. En el campo "Para", puedes:
   - Hacer clic en **"Usar Mail-Tester"** para probar automáticamente
   - O ingresar tu propio correo
4. Escribe el asunto y el mensaje
5. Haz clic en **"Enviar Correo de Prueba"**

### 6.2 Verificar con Mail-Tester

1. Envía un correo a la dirección que te dio Mail-Tester
2. Ve a https://www.mail-tester.com
3. Haz clic en "Then check your score"
4. Deberías obtener una puntuación de 8-10/10

**¿Puntuación baja?** Revisa que:
- Los registros DNS estén correctamente configurados
- Hayan pasado al menos 10-15 minutos desde que agregaste los registros
- El dominio esté marcado como verificado (✅) en el panel

### 6.3 Usando la API

```bash
curl -X POST https://tudominio.com/api/send-mail \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TU_API_TOKEN" \
  -d '{
    "from": "noreply@tudominio.com",
    "to": "test@mail-tester.com",
    "subject": "Prueba de correo",
    "body": "Este es un correo de prueba."
  }'
```

---

## 7. Verificación y Troubleshooting

### Verificar DNS

Después de configurar DNS (espera 10-15 minutos):

1. En el panel, ve a **Dominios**
2. Haz clic en el botón **"Verificar DNS"** de tu dominio
3. Si todo está bien, verás tres checkmarks verdes ✅✅✅

### Problemas Comunes

#### ❌ "DKIM not verified"

**Solución**:
- Verifica que el registro TXT esté correctamente copiado
- Asegúrate de que el nombre sea `default._domainkey`
- Espera más tiempo para la propagación DNS (puede tardar hasta 24 horas)

#### ❌ "SPF not verified"

**Solución**:
- Verifica que la IP del servidor esté correcta
- El registro debe ser tipo TXT, nombre `@`
- Solo puede haber un registro SPF por dominio

#### ❌ Error al enviar correo

**Solución**:
1. Revisa los logs: `tail -f storage/logs/laravel.log`
2. Verifica que Postfix esté corriendo: `systemctl status postfix`
3. Verifica configuración de .env

#### ❌ "Domain not verified"

**Solución**:
- Los registros DNS pueden tardar en propagar
- Usa `dig default._domainkey.tudominio.com TXT` para verificar
- Haz clic en "Verificar DNS" después de esperar

### Comandos Útiles

```bash
# Ver logs de Laravel
tail -f storage/logs/laravel.log

# Ver logs de Postfix
tail -f /var/log/mail.log

# Verificar registro DKIM
dig default._domainkey.tudominio.com TXT

# Verificar registro SPF
dig tudominio.com TXT

# Verificar registro DMARC
dig _dmarc.tudominio.com TXT

# Listar dominios desde CLI
php artisan mailcore:verify-domains

# Ver registros DNS de un dominio
php artisan mailcore:show-dns tudominio.com
```

---

## 🎉 ¡Listo!

Tu servidor de correo está configurado. Ahora puedes:

1. ✅ Crear más dominios y buzones
2. ✅ Enviar correos desde tu aplicación usando la API
3. ✅ Monitorear los envíos desde el panel
4. ✅ Ver estadísticas de correos

## 📚 Recursos Adicionales

- [Documentación de Filament](https://filamentphp.com)
- [Guía de SPF](https://www.spf-record.com)
- [Guía de DKIM](https://www.dkim.org)
- [Guía de DMARC](https://dmarc.org)

---

**¿Necesitas ayuda?** Revisa los logs y la sección de troubleshooting arriba.
