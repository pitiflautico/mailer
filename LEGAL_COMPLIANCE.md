# 🛡️ MailCore - Guía de Cumplimiento Legal y Anti-Spam

## 📋 Tabla de Contenidos

1. [Cumplimiento de Regulaciones](#cumplimiento-de-regulaciones)
2. [Capas de Seguridad Anti-Spam](#capas-de-seguridad-anti-spam)
3. [Sistema de Supresión](#sistema-de-supresión)
4. [Gestión de Consentimiento (GDPR)](#gestión-de-consentimiento-gdpr)
5. [Unsubscribe Automático](#unsubscribe-automático)
6. [IP Reputation & Blacklist Checking](#ip-reputation--blacklist-checking)
7. [Content Filtering](#content-filtering)
8. [Compliance Audit Logging](#compliance-audit-logging)
9. [Mejores Prácticas](#mejores-prácticas)

---

## 🌍 Cumplimiento de Regulaciones

MailCore cumple con las siguientes regulaciones internacionales:

### GDPR (Reglamento General de Protección de Datos - UE)

✅ **Implementado:**
- Consent management con opt-in y double opt-in
- Derecho al olvido (eliminación/anonimización de datos)
- Derecho de acceso (exportación de datos)
- Audit logging de todas las acciones
- Data minimization
- Privacy by design

**Endpoints GDPR:**
```bash
# Exportar datos de usuario
POST /api/compliance/export
{
  "email": "usuario@ejemplo.com"
}

# Eliminar datos de usuario
POST /api/compliance/delete
{
  "email": "usuario@ejemplo.com",
  "confirmation": true,
  "hard_delete": false  # false = anonymize, true = delete
}
```

### CAN-SPAM Act (USA)

✅ **Implementado:**
- Unsubscribe link obligatorio en todos los emails
- Header "List-Unsubscribe" (RFC 2369)
- One-click unsubscribe (RFC 8058)
- Identificación clara del remitente
- Dirección física en footer (configurable)
- Procesamiento de unsubscribes en 10 días (automático)

### CASL (Canada's Anti-Spam Legislation)

✅ **Implementado:**
- Consent explícito antes de enviar
- Información clara del remitente
- Unsubscribe mechanism
- Record keeping de consents

### PECR (Privacy and Electronic Communications Regulations - UK)

✅ **Implementado:**
- Soft opt-in para clientes existentes
- Clear consent requirements
- Unsubscribe facilitation

---

## 🛡️ Capas de Seguridad Anti-Spam

MailCore implementa **7 capas de protección** anti-spam:

### 1️⃣ Suppression List (Lista de Supresión)

Bloquea automáticamente el envío a emails en la lista.

**Razones de supresión:**
- `hard_bounce` - Rebote permanente
- `spam_complaint` - Queja de spam
- `unsubscribe` - Baja voluntaria
- `manual` - Agregado manualmente
- `invalid_address` - Dirección inválida
- `policy_violation` - Violación de políticas
- `gdpr_request` - Solicitud GDPR

**Uso programático:**
```php
use App\Models\SuppressionList;

// Verificar si está suprimido
$isSuppressed = SuppressionList::isSuppressed('email@ejemplo.com');

// Agregar a suppression list
SuppressionList::suppress(
    'email@ejemplo.com',
    'hard_bounce',
    'bounce_detection',
    $domainId
);
```

### 2️⃣ Consent Management

Gestión completa de consentimientos GDPR-compliant.

**Tipos de consent:**
- `marketing` - Correos de marketing
- `transactional` - Correos transaccionales
- `newsletter` - Boletines
- `promotional` - Promociones
- `data_processing` - Procesamiento de datos

**Métodos de consent:**
- `opt_in` - Opt-in simple
- `double_opt_in` - Doble verificación (recomendado)
- `implicit` - Implícito
- `legitimate_interest` - Interés legítimo

**Ejemplo de uso:**
```php
use App\Models\ConsentRecord;

// Verificar consent válido
$hasConsent = ConsentRecord::hasValidConsent(
    'email@ejemplo.com',
    'marketing',
    $domainId
);

// Conceder consent con double opt-in
$consent = ConsentRecord::grant(
    'email@ejemplo.com',
    'marketing',
    'double_opt_in',
    $domainId,
    'User agreed to receive marketing emails'
);

// Verificar double opt-in
$consent->verify();

// Revocar consent
$consent->revoke('User requested to stop receiving emails');
```

### 3️⃣ Unsubscribe Automático

Sistema completo de unsubscribe con multiple standards.

**Features:**
- ✅ Unsubscribe link automático en cada email
- ✅ Header "List-Unsubscribe" (RFC 2369)
- ✅ One-click unsubscribe (RFC 8058)
- ✅ Página de confirmación personalizable
- ✅ Auto-supresión en unsubscribe
- ✅ Audit logging

**Headers agregados automáticamente:**
```
List-Unsubscribe: <https://mail.ejemplo.com/unsubscribe/TOKEN>, <https://mail.ejemplo.com/unsubscribe/one-click/TOKEN>
List-Unsubscribe-Post: List-Unsubscribe=One-Click
Precedence: bulk
```

**URLs generadas:**
```php
// Generar URL de unsubscribe
$url = Unsubscribe::generateUrl('email@ejemplo.com', $domainId);
// https://mail.ejemplo.com/unsubscribe/TOKEN
```

### 4️⃣ Spam Content Filter

Análisis automático de contenido para detectar spam.

**Checks realizados:**
- Spam trigger words (200+ palabras)
- URL density (ratio de enlaces)
- Phishing patterns
- Excessive HTML markup
- Deceptive subject lines
- Capital letters ratio
- Excessive punctuation

**Score de spam:**
- `0-39`: ALLOW (permitir)
- `40-69`: MARK_AS_SPAM (marcar)
- `70-99`: QUARANTINE (cuarentena)
- `100+`: REJECT (rechazar)

**Ejemplo:**
```php
use App\Services\SpamFilterService;

$filter = app(SpamFilterService::class);

$result = $filter->shouldFilter([
    'from' => 'sender@ejemplo.com',
    'to' => 'recipient@test.com',
    'subject' => 'Limited time offer!!!',
    'body' => 'Click here to win $1000...'
]);

// $result = [
//     'should_filter' => true,
//     'spam_score' => 85,
//     'reasons' => ['Contains spam trigger words', 'High URL density'],
//     'recommendation' => 'QUARANTINE'
// ]
```

### 5️⃣ IP Reputation Tracking

Seguimiento y verificación de reputación de IPs.

**Métricas rastreadas:**
- Reputation score (0-100)
- Spam reports
- Successful sends
- Failed sends
- Bounce rate
- Blacklist status

**Blacklist providers verificados:**
- Spamhaus ZEN
- SpamCop
- Barracuda
- SORBS

**Actualización automática:**
```php
use App\Services\IpReputationService;

$service = app(IpReputationService::class);

// Verificar reputación
$reputation = $service->checkReputation('192.168.1.1');

// Verificar si puede enviar
$canSend = $service->canSend('192.168.1.1');

// Actualizar desde actividad
$service->updateFromActivity('192.168.1.1', 'successful_send');
$service->updateFromActivity('192.168.1.1', 'spam_report');
```

### 6️⃣ Compliance Validation

Validación automática de cumplimiento antes de enviar.

**Checks realizados:**
- ✅ Email no está en suppression list
- ✅ Consent válido (para marketing)
- ✅ No está unsubscribed
- ✅ Sender dentro de límites
- ✅ Dominio verificado (SPF/DKIM/DMARC)
- ✅ IP reputation acceptable
- ✅ Contenido cumple con regulaciones

**Validación de contenido:**
```php
$validation = $complianceService->validateEmailContent(
    $subject,
    $body,
    $fromEmail
);

// $validation = [
//     'compliant' => false,
//     'issues' => [
//         'Missing unsubscribe link (CAN-SPAM Act)',
//         'Missing physical address (CAN-SPAM Act)',
//         'High spam score: 7/10'
//     ],
//     'spam_score' => 7
// ]
```

### 7️⃣ Comprehensive Audit Logging

Logging completo de todas las acciones de cumplimiento.

**Acciones loggeadas:**
- email_send_check
- consent_granted
- consent_verified
- consent_revoked
- unsubscribe
- gdpr_export
- gdpr_deletion
- spam_complaint
- suppression_add

**Ejemplo:**
```php
use App\Models\ComplianceLog;

// Log GDPR action
ComplianceLog::logGdpr(
    'gdpr_export',
    'user@ejemplo.com',
    'User data exported',
    ['records' => 150]
);

// Log compliance check
ComplianceLog::logAction(
    'email_send_check',
    'Compliance check before send',
    'user@ejemplo.com',
    'can_spam',
    true  // compliant
);

// Obtener logs no cumplidos
$nonCompliant = ComplianceLog::nonCompliant()->get();
```

---

## 📊 Sistema de Supresión

### Estructura de la Tabla

```sql
suppression_list:
  - email (unique, indexed)
  - reason (enum)
  - notes
  - source
  - suppressed_at
  - expires_at (nullable)
  - domain_id
  - metadata (json)
```

### Auto-Supresión

El sistema agrega automáticamente a la suppression list cuando:

1. **Hard Bounce** - Después de 1 rebote permanente
2. **Spam Complaint** - Inmediatamente al recibir queja
3. **Unsubscribe** - Cuando el usuario se da de baja
4. **Multiple Soft Bounces** - Después de 3+ soft bounces
5. **GDPR Request** - Al procesar solicitud de eliminación

### Expiración

Puedes configurar supresiones temporales:

```php
SuppressionList::create([
    'email' => 'temp@ejemplo.com',
    'reason' => 'soft_bounce',
    'suppressed_at' => now(),
    'expires_at' => now()->addDays(30),  // Expira en 30 días
]);
```

---

## 🔐 Gestión de Consentimiento (GDPR)

### Double Opt-In Flow

**Flujo recomendado para máximo cumplimiento:**

```
1. Usuario se registra
   ↓
2. Sistema crea ConsentRecord con granted=false
   ↓
3. Envía email de verificación con token
   ↓
4. Usuario hace click en link de verificación
   ↓
5. Sistema marca consent como granted=true y verified_at
   ↓
6. Usuario puede recibir emails
```

**Implementación:**

```php
// Paso 1: Crear consent con double opt-in
$consent = ConsentRecord::grant(
    'nuevo@usuario.com',
    'marketing',
    'double_opt_in',
    $domainId,
    'User subscribed to newsletter'
);

// Paso 2: Enviar email de verificación
Mail::send('emails.verify-consent', [
    'url' => route('consent.verify', ['token' => $consent->verification_token])
], function($message) use ($consent) {
    $message->to($consent->email)
        ->subject('Please verify your subscription');
});

// Paso 3: Usuario verifica (en ConsentController)
$consent = ConsentRecord::where('verification_token', $token)->firstOrFail();
$consent->verify();
```

### Consent Lifecycle

```
CREATED → (double opt-in) → VERIFIED → ACTIVE
                             ↓
                          REVOKED → INACTIVE
                             ↓
                          EXPIRED → INACTIVE
```

---

## 🚫 Unsubscribe Automático

### Implementación Automática

El sistema agrega AUTOMÁTICAMENTE a cada email:

1. **Link en el body** (si no existe)
2. **Headers RFC-compliant**
3. **One-click unsubscribe**

**No necesitas hacer nada - es automático!**

### Personalización

Si quieres personalizar el mensaje de unsubscribe:

```php
$body = "Tu contenido aquí...

---
¿No quieres recibir más emails?
Para darte de baja, haz click aquí: {$unsubscribeUrl}

Empresa S.A.
Calle Ejemplo 123, Ciudad
";
```

### APIs de Unsubscribe

**Routes públicas (no requieren auth):**

```
GET  /unsubscribe/{token}          # Mostrar página de confirmación
POST /unsubscribe/{token}          # Procesar unsubscribe
POST /unsubscribe/one-click/{token} # One-click (RFC 8058)
```

---

## 🌐 IP Reputation & Blacklist Checking

### Monitoreo Automático

El sistema verifica automáticamente:

**Frecuencia:** 1 vez al día por IP

**Blacklists verificadas:**
- `zen.spamhaus.org` - Spamhaus ZEN
- `bl.spamcop.net` - SpamCop
- `b.barracudacentral.org` - Barracuda
- `dnsbl.sorbs.net` - SORBS

### Comandos Artisan

```bash
# Verificar reputación de todas las IPs
php artisan mailcore:check-ip-reputation

# Verificar IP específica
php artisan mailcore:check-ip-reputation 192.168.1.1

# Actualizar blacklist status
php artisan mailcore:update-blacklists
```

### Acciones Automáticas

**Si IP es blacklisted:**
1. Se marca `is_blacklisted = true`
2. Se bloquean envíos desde esa IP
3. Se envía notificación al admin (si configurado)
4. Se logea en ComplianceLog

---

## 🔍 Content Filtering

### Spam Trigger Words

El sistema analiza el contenido buscando más de 200 palabras/frases spam:

**Alto riesgo (30 puntos):**
- viagra, cialis, casino, lottery

**Medio riesgo (20-25 puntos):**
- winner, free money, earn money, weight loss

**Bajo riesgo (15 puntos):**
- congratulations, limited time offer, bitcoin

### Phishing Detection

Patrones detectados automáticamente:
- "verify your account"
- "update payment"
- "suspended account"
- "unusual activity"
- "confirm identity"
- "urgent action required"

### Recomendaciones

**Para evitar filtros de spam:**

❌ **Evitar:**
```
Subject: !!!WINNER!!! Click Here NOW!!!
Body: FREE MONEY! Act now! Limited time! Click here immediately!
Visit http://link1.com http://link2.com http://link3.com...
```

✅ **Hacer:**
```
Subject: Your monthly newsletter - January 2025
Body: Hi [Name],

Here's what's new this month:
- Feature update: New dashboard
- Tips: How to improve your email deliverability

Best regards,
[Your Company]
```

---

## 📋 Compliance Audit Logging

### Qué se Loggea

**Todas las acciones relacionadas con:**
- Envíos de email
- Consentimientos
- Unsubscribes
- Exportaciones GDPR
- Eliminaciones de datos
- Quejas de spam
- Cambios de supresión

### Estructura del Log

```json
{
  "user_id": 1,
  "action_type": "email_send_check",
  "entity_type": "SendLog",
  "entity_id": 12345,
  "email": "user@ejemplo.com",
  "description": "Compliance check before send",
  "compliance_standard": "can_spam",
  "compliant": true,
  "non_compliance_reason": null,
  "data_snapshot": {
    "checks_performed": [...],
    "results": [...]
  },
  "ip_address": "192.168.1.1",
  "user_agent": "MailCore API v1.0",
  "created_at": "2025-01-15 10:30:00"
}
```

### Consultas Útiles

```php
// Obtener todas las acciones no cumplidas
$nonCompliant = ComplianceLog::nonCompliant()->get();

// Obtener logs GDPR
$gdprLogs = ComplianceLog::standard('gdpr')
    ->where('email', 'user@ejemplo.com')
    ->get();

// Generar reporte de cumplimiento
$report = $complianceService->generateComplianceReport($domainId, 30);
```

### Retención de Logs

**Por regulación:**
- GDPR: 6 años mínimo
- CAN-SPAM: 3 años recomendado
- CASL: 3 años mínimo

**Configurar retención:**
```env
COMPLIANCE_LOG_RETENTION_DAYS=2190  # 6 años
```

---

## ✅ Mejores Prácticas

### 1. Warming de IP

**Nunca envíes grandes volúmenes inmediatamente:**

```
Día 1-3:   50-100 emails/día
Día 4-7:   200-500 emails/día
Día 8-14:  1000-2000 emails/día
Día 15+:   Volumen completo
```

### 2. Monitoreo de Métricas

**Vigila constantemente:**
- Bounce rate < 2%
- Complaint rate < 0.1%
- Open rate > 15%
- Click rate > 2%

**Si bounce rate > 5%:** ¡Detén envíos inmediatamente!

### 3. Limpieza de Listas

**Regularmente:**
```bash
# Eliminar hard bounces
php artisan mailcore:cleanup-bounces --type=hard

# Limpiar inactivos (6+ meses sin abrir)
php artisan mailcore:cleanup-inactive --months=6
```

### 4. Autenticación Completa

**Siempre verifica:**
```bash
# Verificar SPF, DKIM, DMARC
php artisan mailcore:verify-domains

# Score debe ser 100%
```

### 5. Contenido de Calidad

**Ratio texto/imágenes:**
- Mínimo 40% texto
- Máximo 60% imágenes

**Links:**
- Máximo 5 links por email
- Siempre HTTPS
- Incluir unsubscribe link

### 6. Testing Antes de Enviar

**Usar mail-tester.com:**
```
1. Envía email de prueba a la dirección que te dan
2. Verifica score (debe ser > 9/10)
3. Corrige problemas identificados
4. Re-testea
```

### 7. Gestión de Quejas

**Al recibir spam complaint:**
1. Auto-supresión inmediata
2. Investigar causa
3. Mejorar contenido/targeting
4. No reenviar NUNCA a quien se quejó

### 8. Documentation

**Mantén documentado:**
- Políticas de envío
- Procedimientos de consent
- Proceso de unsubscribe
- Retención de datos
- Respuesta a quejas

---

## 🚨 Acciones Ante Problemas

### Blacklisted

**Si tu IP es blacklisted:**

1. **Identificar causa:**
   ```bash
   php artisan mailcore:analyze-sends --days=30
   ```

2. **Limpiar listas:**
   ```bash
   php artisan mailcore:cleanup-suppressions
   ```

3. **Solicitar remoción:**
   - Spamhaus: https://www.spamhaus.org/lookup/
   - SpamCop: https://www.spamcop.net/bl.shtml
   - Barracuda: https://barracudacentral.org/rbl/removal-request

4. **Warming de nuevo:**
   - Reduce volumen a 10%
   - Incrementa gradualmente

### Alto Complaint Rate

**Si complaint rate > 0.1%:**

1. Audita contenido
2. Verifica targeting
3. Confirma double opt-in
4. Revisa frecuencia de envío
5. A/B test diferentes enfoques

### Bajo Engagement

**Si open rate < 10%:**

1. Limpia lista (inactivos)
2. Mejora subject lines
3. Optimiza preview text
4. Segmenta audiencia
5. Personaliza contenido

---

## 📞 Recursos Adicionales

### Standards & RFCs

- **RFC 2369** - List-Unsubscribe Header
- **RFC 8058** - One-Click Unsubscribe
- **RFC 6376** - DKIM Signatures
- **RFC 7208** - SPF
- **RFC 7489** - DMARC

### Regulaciones

- **GDPR** - https://gdpr.eu/
- **CAN-SPAM** - https://www.ftc.gov/tips-advice/business-center/guidance/can-spam-act-compliance-guide-business
- **CASL** - https://crtc.gc.ca/eng/com500/faq500.htm

### Tools

- **Mail Tester** - https://www.mail-tester.com/
- **MXToolbox** - https://mxtoolbox.com/
- **Spamhaus Lookup** - https://www.spamhaus.org/lookup/

---

## ✅ Checklist de Cumplimiento

Antes de empezar a enviar correos masivos:

- [ ] Dominio completamente verificado (SPF, DKIM, DMARC)
- [ ] PTR record configurado
- [ ] IP no está en blacklists
- [ ] Double opt-in implementado
- [ ] Unsubscribe link funciona
- [ ] Physical address en footer
- [ ] Consent records guardados
- [ ] Compliance logging activo
- [ ] Suppression list activa
- [ ] Content validation habilitada
- [ ] Rate limits configurados
- [ ] Backup strategy implementada
- [ ] Monitoring alerts configuradas
- [ ] Privacy policy publicada
- [ ] Terms of service actualizados

---

**🛡️ MailCore - Email Compliance Made Easy**

*Sistema 100% legal, seguro y anti-spam compliant*
