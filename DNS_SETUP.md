# 🌐 MailCore - Guía de Configuración DNS

Esta guía te explica paso a paso cómo configurar los registros DNS necesarios para que tu servidor de correo funcione correctamente.

## 📋 Tabla de Contenidos

1. [¿Qué es DNS y por qué es necesario?](#qué-es-dns-y-por-qué-es-necesario)
2. [Registros DNS Necesarios](#registros-dns-necesarios)
3. [Configuración Paso a Paso](#configuración-paso-a-paso)
4. [Proveedores de DNS Populares](#proveedores-de-dns-populares)
5. [Verificación](#verificación)
6. [Troubleshooting](#troubleshooting)

---

## 🤔 ¿Qué es DNS y por qué es necesario?

**DNS (Domain Name System)** es como la "guía telefónica" de Internet. Convierte nombres de dominio legibles (como `ejemplo.com`) en direcciones IP que las computadoras entienden.

Para que tu servidor de correo funcione correctamente, necesitas configurar varios registros DNS que:
- Indican dónde está ubicado tu servidor de correo
- Verifican que tú eres el propietario legítimo del dominio
- Autentican que los emails provienen realmente de ti
- Protegen contra phishing y spam

---

## 📝 Registros DNS Necesarios

### 1. **A Record** (Mail Server Location)
Indica la dirección IP de tu servidor de correo.

```
Type:  A
Name:  mail.tudominio.com
Value: 123.456.789.10 (tu IP del servidor)
TTL:   3600
```

### 2. **MX Record** (Mail Exchange)
Indica qué servidor maneja el correo electrónico de tu dominio.

```
Type:     MX
Name:     tudominio.com
Value:    mail.tudominio.com
Priority: 10
TTL:      3600
```

### 3. **SPF Record** (Sender Policy Framework)
Indica qué servidores están autorizados a enviar emails desde tu dominio.

```
Type:  TXT
Name:  tudominio.com
Value: v=spf1 ip4:123.456.789.10 a:mail.tudominio.com -all
TTL:   3600
```

**Explicación del valor:**
- `v=spf1` - Versión de SPF
- `ip4:123.456.789.10` - Tu servidor puede enviar emails
- `a:mail.tudominio.com` - El host mail.tudominio.com puede enviar
- `-all` - Rechazar emails de otros servidores (strict)

### 4. **DKIM Record** (Domain Keys Identified Mail)
Firma criptográfica que verifica que el email no fue modificado.

```
Type:  TXT
Name:  default._domainkey.tudominio.com
Value: v=DKIM1; k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQC... (clave pública)
TTL:   3600
```

**Nota:** La clave DKIM debe generarse primero desde MailCore.

### 5. **DMARC Record** (Domain-based Message Authentication)
Política que indica qué hacer con emails que fallan SPF/DKIM.

```
Type:  TXT
Name:  _dmarc.tudominio.com
Value: v=DMARC1; p=quarantine; rua=mailto:postmaster@tudominio.com; fo=1
TTL:   3600
```

**Explicación del valor:**
- `v=DMARC1` - Versión
- `p=quarantine` - Marcar como spam si falla verificación
- `rua=mailto:...` - Email para recibir reportes agregados
- `fo=1` - Generar reporte si falla SPF o DKIM

### 6. **PTR Record** (Reverse DNS)
**IMPORTANTE:** Este registro NO se configura en tu proveedor de dominio, sino en tu proveedor de hosting.

```
IP:    123.456.789.10
Value: mail.tudominio.com
```

---

## 🚀 Configuración Paso a Paso

### Paso 1: Obtener la IP del Servidor

```bash
# En tu servidor
curl ifconfig.me

# O
hostname -I
```

Guarda esta IP, la necesitarás en todos los registros.

### Paso 2: Generar Claves DKIM

Desde el panel de administración de MailCore:

1. Ve a **Dominios**
2. Selecciona tu dominio
3. Haz clic en **"Generar DKIM"**
4. Haz clic en **"Ver Registros DNS"** para copiar la clave pública

O desde la terminal:

```bash
cd /var/www/mailcore
php artisan mailcore:generate-dkim tudominio.com
php artisan mailcore:show-dns tudominio.com
```

### Paso 3: Acceder a tu Proveedor de DNS

Inicia sesión en el panel de tu proveedor de dominio donde compraste el dominio.

Proveedores comunes:
- GoDaddy
- Namecheap
- Cloudflare
- Google Domains
- AWS Route 53
- DigitalOcean DNS

### Paso 4: Añadir los Registros DNS

**Importante:** Añade TODOS los registros listados arriba.

#### Para cada registro:

1. Busca la sección de **DNS Management** o **DNS Settings**
2. Haz clic en **Add Record** o **Añadir Registro**
3. Selecciona el **Type** (tipo)
4. Ingresa el **Name** (nombre)
5. Ingresa el **Value** (valor)
6. Configura el **TTL** (Time To Live) - usa 3600 si no sabes qué poner
7. Guarda el registro

### Paso 5: Configurar PTR (Reverse DNS)

El PTR se configura de manera diferente:

**Si usas AWS (EC2):**
```bash
# Llenar formulario en:
# https://aws.amazon.com/forms/ec2-email-limit-rdns-request
```

**Si usas DigitalOcean:**
```bash
# En el panel de DigitalOcean:
# Droplet → Settings → Networking → PTR Record
```

**Si usas Hetzner:**
```bash
# En el panel de Hetzner:
# Server → IPs → Click en IP → Reverse DNS
```

**Si usas otro proveedor:**
- Abre un ticket de soporte
- Solicita configurar el PTR record
- Proporciona: IP y hostname (mail.tudominio.com)

### Paso 6: Esperar Propagación DNS

Los cambios DNS no son instantáneos:

- **Mínimo:** 5-10 minutos
- **Usual:** 30 minutos
- **Máximo:** 24-48 horas

**Tip:** Si usas Cloudflare, la propagación es casi instantánea (1-2 minutos).

### Paso 7: Verificar Configuración

#### Desde el Panel de MailCore:

1. Ve a **Dominios**
2. Selecciona tu dominio
3. Haz clic en **"Verificar DNS"**
4. Verifica que todos los registros están ✓ Verificados

#### Desde la Terminal:

```bash
# Verificar un dominio específico
php artisan mailcore:verify-dns tudominio.com

# Verificar todos los dominios
php artisan mailcore:verify-dns
```

#### Manualmente con comandos:

```bash
# Verificar SPF
dig TXT tudominio.com +short | grep spf

# Verificar DKIM
dig TXT default._domainkey.tudominio.com +short | grep DKIM1

# Verificar DMARC
dig TXT _dmarc.tudominio.com +short | grep DMARC1

# Verificar MX
dig MX tudominio.com +short

# Verificar PTR
dig -x 123.456.789.10 +short
```

---

## 🌍 Proveedores de DNS Populares

### GoDaddy

1. Inicia sesión en GoDaddy
2. Ve a **My Products** → **Domains**
3. Haz clic en tu dominio
4. Scroll hasta **Additional Settings** → **Manage DNS**
5. Haz clic en **Add** para cada registro

**Notas:**
- En "Name", GoDaddy añade automáticamente el dominio, así que solo pon el prefijo
- Para SPF/DMARC, usa Type = **TXT**

### Namecheap

1. Inicia sesión en Namecheap
2. Ve a **Domain List**
3. Haz clic en **Manage** junto a tu dominio
4. Ve a **Advanced DNS**
5. Haz clic en **Add New Record**

**Notas:**
- Para registros TXT, Namecheap puede requerir comillas en el valor
- El nombre del dominio se añade automáticamente

### Cloudflare

1. Inicia sesión en Cloudflare
2. Selecciona tu dominio
3. Ve a **DNS**
4. Haz clic en **Add record**

**Notas:**
- ACTIVA el proxy (nube naranja) SOLO para A records de tu sitio web
- DESACTIVA el proxy para el registro A de mail (debe estar en gris)
- Cloudflare propaga DNS muy rápido (1-2 minutos)

### AWS Route 53

1. Inicia sesión en AWS Console
2. Ve a **Route 53** → **Hosted Zones**
3. Selecciona tu dominio
4. Haz clic en **Create record**

**Notas:**
- Usa formato FQDN (Fully Qualified Domain Name) - añade punto al final
- Ejemplo: `mail.tudominio.com.` (con punto al final)

### Google Domains

1. Inicia sesión en Google Domains
2. Selecciona tu dominio
3. Ve a **DNS**
4. Scroll a **Custom resource records**
5. Añade cada registro

**Notas:**
- Google Domains es simple y directo
- Propagación toma 5-10 minutos generalmente

---

## ✅ Verificación

### Tests Online Recomendados

#### 1. MXToolbox
```
https://mxtoolbox.com/SuperTool.aspx
```
- Ingresa tu dominio
- Verifica: MX, SPF, DKIM, DMARC, Blacklist

#### 2. Mail-Tester
```
https://www.mail-tester.com/
```
- Envía un email de prueba
- Obten score de 10/10
- Verifica autenticación completa

#### 3. DKIM Validator
```
https://dkimvalidator.com/
```
- Verifica específicamente DKIM
- Asegura que la firma es válida

#### 4. DMARCian
```
https://dmarcian.com/dmarc-inspector/
```
- Verifica configuración DMARC
- Asegura que la política es correcta

### Checklist Completo

- [ ] A Record configurado (mail.tudominio.com)
- [ ] MX Record configurado
- [ ] SPF Record configurado y verificado
- [ ] DKIM keys generadas
- [ ] DKIM Record configurado y verificado
- [ ] DMARC Record configurado y verificado
- [ ] PTR Record configurado (con hosting provider)
- [ ] DNS propagado (espera 30 minutos mínimo)
- [ ] Verificación desde MailCore panel: todos ✓
- [ ] Test en mail-tester.com: score > 9/10
- [ ] No apareces en blacklists (mxtoolbox.com)

---

## 🐛 Troubleshooting

### Problema 1: "SPF not found"

**Síntomas:** `dig TXT tudominio.com +short` no muestra registro SPF

**Soluciones:**
```bash
# 1. Verifica que añadiste el registro TXT
# 2. Espera propagación DNS (30 min)
# 3. Verifica que el nombre sea exactamente: tudominio.com (no mail.tudominio.com)
# 4. Algunos providers requieren @ en lugar del dominio completo

# Test
dig TXT tudominio.com +short | grep spf
# Debe mostrar: "v=spf1 ..."
```

### Problema 2: "DKIM not found"

**Síntomas:** `dig TXT default._domainkey.tudominio.com +short` no muestra nada

**Soluciones:**
```bash
# 1. Verifica que generaste las claves DKIM
php artisan mailcore:generate-dkim tudominio.com

# 2. Copia la clave pública completa (incluye v=DKIM1; k=rsa; p=...)
php artisan mailcore:show-dns tudominio.com

# 3. Verifica que el nombre sea: default._domainkey.tudominio.com
# 4. El valor debe empezar con: v=DKIM1; k=rsa; p=

# Test
dig TXT default._domainkey.tudominio.com +short
# Debe mostrar: "v=DKIM1; k=rsa; p=MIGfMA0..."
```

### Problema 3: "DMARC not found"

**Síntomas:** `dig TXT _dmarc.tudominio.com +short` no muestra nada

**Soluciones:**
```bash
# 1. Verifica que el nombre empieza con guión bajo: _dmarc
# 2. Nombre completo: _dmarc.tudominio.com
# 3. Valor debe empezar con: v=DMARC1;

# Test
dig TXT _dmarc.tudominio.com +short
# Debe mostrar: "v=DMARC1; p=quarantine..."
```

### Problema 4: "PTR not configured"

**Síntomas:** `dig -x TU_IP +short` no muestra mail.tudominio.com

**Soluciones:**
```bash
# PTR DEBE configurarse con tu proveedor de HOSTING (no DNS)

# AWS EC2:
# https://aws.amazon.com/forms/ec2-email-limit-rdns-request

# DigitalOcean:
# Panel → Droplet → Settings → PTR Record

# Hetzner:
# Panel → Server → IPs → Reverse DNS

# Otros: Abrir ticket de soporte

# Test
dig -x TU_IP_SERVIDOR +short
# Debe mostrar: mail.tudominio.com
```

### Problema 5: "DNS no propaga"

**Síntomas:** Los registros no aparecen después de añadirlos

**Soluciones:**
```bash
# 1. Espera más tiempo (hasta 24h en casos raros)

# 2. Verifica usando diferentes servidores DNS
dig @8.8.8.8 TXT tudominio.com +short     # Google DNS
dig @1.1.1.1 TXT tudominio.com +short     # Cloudflare DNS
dig @208.67.222.222 TXT tudominio.com     # OpenDNS

# 3. Limpia caché DNS local
sudo systemd-resolve --flush-caches  # Linux
dscacheutil -flushcache               # Mac
ipconfig /flushdns                    # Windows

# 4. Usa herramientas online
# https://www.whatsmydns.net/
```

### Problema 6: "Emails van a spam"

**Síntomas:** Los emails se reciben pero van a carpeta de spam

**Soluciones:**
```bash
# 1. Verifica TODOS los registros están configurados
php artisan mailcore:verify-dns tudominio.com

# 2. Test en mail-tester.com - objetivo: 10/10

# 3. Verifica que no estás en blacklists
# https://mxtoolbox.com/blacklists.aspx

# 4. Revisa contenido del email (evita spam words)

# 5. Construye reputación:
#    - Envía bajo volumen al principio
#    - Incrementa gradualmente
#    - Evita spam complaints
#    - Mantén lista limpia (remove bounces)
```

### Problema 7: "Permission denied" al verificar

**Síntomas:** Error al ejecutar comandos de verificación

**Soluciones:**
```bash
# Los comandos de verificación necesitan permisos de lectura de archivos

# Verificar permisos
ls -la /etc/opendkim/keys/tudominio.com/

# Debería mostrar archivos propiedad de opendkim

# Si no existen las claves
sudo ./scripts/manage-dkim.sh generate tudominio.com

# O desde Laravel
php artisan mailcore:generate-dkim tudominio.com
```

---

## 📱 Proveedores de Email (Gmail, Outlook) - Notas Especiales

### Gmail

Gmail es muy estricto con la autenticación:

```bash
# Requisitos MÍNIMOS:
✓ SPF configurado
✓ DKIM configurado y firmado
✓ DMARC configurado
✓ PTR configurado
✓ No estar en blacklists
✓ Reputación de IP limpia
```

**Consejos:**
- Warming up: Envía 20-50 emails/día las primeras semanas
- Evita emails masivos al principio
- Asegura que los destinatarios no marquen como spam

### Microsoft (Outlook, Hotmail)

Similar a Gmail pero a veces más permisivo:

```bash
# Usa SNDS (Smart Network Data Services)
# https://sendersupport.olc.protection.outlook.com/snds/
```

**Consejos:**
- Registra tu IP en SNDS
- Monitorea reputación
- Responde rápido a bounces

---

## 🔧 Comandos Útiles

```bash
# Ver todos los registros DNS de un dominio
dig tudominio.com ANY

# Ver solo MX
dig MX tudominio.com +short

# Ver solo TXT (SPF, DKIM, DMARC)
dig TXT tudominio.com +short

# Ver con servidor DNS específico
dig @8.8.8.8 TXT tudominio.com

# Ver trace completo de resolución
dig tudominio.com +trace

# Ver TTL de un registro
dig tudominio.com +noall +answer

# Test desde MailCore
php artisan mailcore:verify-dns tudominio.com
php artisan mailcore:show-dns tudominio.com
```

---

## 📚 Recursos Adicionales

- **RFC 7208**: SPF Specification
- **RFC 6376**: DKIM Specification
- **RFC 7489**: DMARC Specification
- **MXToolbox**: https://mxtoolbox.com/
- **DMARC Analyzer**: https://dmarcian.com/
- **Mail Tester**: https://www.mail-tester.com/

---

## 🆘 ¿Necesitas Ayuda?

Si después de seguir esta guía sigues teniendo problemas:

1. **Verifica en MailCore:**
   - Panel Admin → Dominios → Ver Registros DNS
   - Copia los valores exactos mostrados

2. **Ejecuta verificación:**
   ```bash
   php artisan mailcore:verify-dns tudominio.com
   ```

3. **Test online:**
   - mail-tester.com (envía email de prueba)
   - mxtoolbox.com (verifica DNS y blacklists)

4. **Revisa logs:**
   ```bash
   tail -f /var/log/mail.log
   ```

---

**Última actualización**: 2024-11-07
**Versión**: 1.0
