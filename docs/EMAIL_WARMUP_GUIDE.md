# Guía de Email Warmup - GlooPlay

## Objetivo
Construir reputación del dominio `glooplay.com` y la IP `134.209.204.133` para mejorar la entrega a inbox.

## Servicios de Warmup Recomendados

### Opción 1: Mailwarm (Recomendado para empezar)
- **URL**: https://www.mailwarm.com/
- **Precio**: Plan gratuito disponible
- **Setup**:
  1. Crear cuenta en Mailwarm
  2. Conectar mailbox hi@glooplay.com
  3. Configurar volumen inicial: 5-10 emails/día
  4. Aumentar gradualmente cada semana

### Opción 2: Warmup Inbox
- **URL**: https://www.warmupinbox.com/
- **Precio**: ~$15/mes
- **Ventaja**: Red grande de cuentas reales

### Opción 3: MailReach
- **URL**: https://www.mailreach.co/
- **Precio**: ~$25/mes
- **Ventaja**: Incluye spam score testing

## Cronograma Manual de Warmup (30 días)

### Semana 1: Establecer base
- **Día 1-3**: 5 correos/día a amigos/conocidos
- **Día 4-7**: 10 correos/día
- **Objetivo**: 100% tasa de apertura

### Semana 2: Aumentar gradualmente
- **Día 8-10**: 20 correos/día
- **Día 11-14**: 30 correos/día
- **Objetivo**: >50% tasa de apertura

### Semana 3: Escalar
- **Día 15-17**: 50 correos/día
- **Día 18-21**: 75 correos/día
- **Objetivo**: >30% tasa de apertura

### Semana 4: Volumen normal
- **Día 22-30**: 100-200 correos/día
- **Objetivo**: Entrega consistente a inbox

## Mejores Prácticas

### ✅ Hacer
1. Personalizar subject y contenido
2. Enviar a diferentes proveedores (Gmail, Outlook, etc.)
3. Mantener engagement alto al inicio
4. Responder rápido a replies
5. Monitorear bounces y removerlos inmediatamente
6. Usar unsubscribe links (ya configurado)
7. Mantener ratio queja/spam < 0.1%

### ❌ Evitar
1. Enviar a listas compradas
2. Enviar bursts grandes de golpe
3. Usar palabras spam en subject
4. Muchos links o imágenes pesadas
5. Archivos adjuntos al inicio
6. Todo en mayúsculas
7. Cambiar "From" name frecuentemente

## Monitoring

### Métricas clave a seguir:
```bash
# Ver últimos envíos
php artisan mailcore:recent-sends --limit=50

# Verificar DNS
php artisan mailcore:verify-domains glooplay.com

# Ver bounces
# (comando a implementar)
```

### Herramientas de testing:
- **Mail-Tester**: https://www.mail-tester.com/ (test spam score)
- **MXToolbox**: https://mxtoolbox.com/deliverability (check blacklists)
- **Google Postmaster**: https://postmaster.google.com/ (Gmail reputation)

## Feedback Loops (FBL)

Registrarse para recibir notificaciones de quejas:

1. **Gmail**: https://postmaster.google.com/
2. **Outlook**: https://sendersupport.olc.protection.outlook.com/snds/
3. **Yahoo**: No disponible para dominios pequeños
4. **AOL**: https://postmaster.aol.com/

## Checklist Rápido

- [ ] SPF configurado ✅
- [ ] DKIM configurado ✅
- [ ] DMARC configurado ✅
- [ ] PTR record (opcional pero recomendado)
- [ ] Registrado en Google Postmaster Tools
- [ ] Registrado en Microsoft SNDS
- [ ] Warmup service activado o cronograma manual iniciado
- [ ] Lista de 10-20 contactos conocidos para warmup
- [ ] Monitoreo diario de métricas

## Cronograma de Mejora Esperado

| Semana | Tasa de Inbox Esperada | Acciones |
|--------|------------------------|----------|
| 1      | 20-30% (mucho spam)    | Warmup intensivo |
| 2      | 40-50%                 | Continuar warmup |
| 3      | 60-70%                 | Aumentar volumen |
| 4      | 70-85%                 | Volumen normal |
| 8+     | 85-95%                 | Reputación establecida |

## Recursos

- [Return Path Email Deliverability Guide](https://returnpath.com/)
- [Mailgun Email Best Practices](https://www.mailgun.com/blog/deliverability/)
- [SendGrid Deliverability Guide](https://docs.sendgrid.com/ui/sending-email/deliverability)

## Contactos de Soporte

- **Admin Panel**: https://mail.nebulio.es/admin
- **API Docs**: Ver `/docs/API.md`
