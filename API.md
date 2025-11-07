# MailCore - Documentación de API

## 🔐 Autenticación

La API de MailCore utiliza Laravel Sanctum para autenticación mediante tokens.

### Generar Token de API

1. Accede al panel de administración
2. Ve a tu perfil de usuario
3. Genera un nuevo token de API
4. Copia el token generado (solo se muestra una vez)

### Usar el Token

Incluye el token en el header de cada petición:

```
Authorization: Bearer {tu-token}
Content-Type: application/json
```

## 📧 Endpoints

### Base URL

```
https://mail.tudominio.com/api
```

---

## 1. Enviar Email Simple

### `POST /send`

Envía un correo electrónico simple.

#### Request

```json
{
  "from": "noreply@tudominio.com",
  "to": "destinatario@ejemplo.com",
  "subject": "Asunto del correo",
  "body": "Contenido del correo en texto plano o HTML",
  "cc": "copia@ejemplo.com",
  "bcc": "copia_oculta@ejemplo.com",
  "reply_to": "respuestas@tudominio.com",
  "headers": {
    "X-Custom-Header": "valor"
  },
  "metadata": {
    "campaign_id": "123",
    "user_id": "456"
  }
}
```

#### Response (Éxito)

```json
{
  "success": true,
  "message": "Email sent successfully",
  "data": {
    "message_id": "abc123def456@tudominio.com",
    "send_log_id": 789
  }
}
```

#### Response (Error)

```json
{
  "success": false,
  "error": "Invalid sender mailbox"
}
```

---

## 2. Enviar Emails en Lote

### `POST /send/bulk`

Envía múltiples correos en una sola petición (máximo 100).

#### Request

```json
{
  "emails": [
    {
      "from": "noreply@tudominio.com",
      "to": "usuario1@ejemplo.com",
      "subject": "Email 1",
      "body": "Contenido del email 1"
    },
    {
      "from": "noreply@tudominio.com",
      "to": "usuario2@ejemplo.com",
      "subject": "Email 2",
      "body": "Contenido del email 2"
    }
  ]
}
```

#### Response

```json
{
  "success": true,
  "message": "Bulk send completed",
  "data": {
    "success_count": 2,
    "failed_count": 0,
    "errors": []
  }
}
```

---

## 3. Health Check

### `GET /health`

Verifica el estado del sistema (no requiere autenticación).

#### Response

```json
{
  "status": "ok",
  "timestamp": "2025-01-15T10:30:00.000000Z"
}
```

---

## 📝 Ejemplos de Uso

### cURL

```bash
curl -X POST https://mail.tudominio.com/api/send \
  -H "Authorization: Bearer tu-token-aqui" \
  -H "Content-Type: application/json" \
  -d '{
    "from": "noreply@tudominio.com",
    "to": "destinatario@ejemplo.com",
    "subject": "Prueba de API",
    "body": "Este es un correo de prueba desde la API"
  }'
```

### PHP

```php
<?php

$ch = curl_init('https://mail.tudominio.com/api/send');

$data = [
    'from' => 'noreply@tudominio.com',
    'to' => 'destinatario@ejemplo.com',
    'subject' => 'Prueba de API',
    'body' => 'Este es un correo de prueba desde la API'
];

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer tu-token-aqui',
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($data)
]);

$response = curl_exec($ch);
$result = json_decode($response, true);

if ($result['success']) {
    echo "Email enviado: " . $result['data']['message_id'];
} else {
    echo "Error: " . $result['error'];
}
```

### Python

```python
import requests

url = 'https://mail.tudominio.com/api/send'
headers = {
    'Authorization': 'Bearer tu-token-aqui',
    'Content-Type': 'application/json'
}
data = {
    'from': 'noreply@tudominio.com',
    'to': 'destinatario@ejemplo.com',
    'subject': 'Prueba de API',
    'body': 'Este es un correo de prueba desde la API'
}

response = requests.post(url, json=data, headers=headers)
result = response.json()

if result['success']:
    print(f"Email enviado: {result['data']['message_id']}")
else:
    print(f"Error: {result['error']}")
```

### JavaScript (Node.js)

```javascript
const axios = require('axios');

const sendEmail = async () => {
  try {
    const response = await axios.post('https://mail.tudominio.com/api/send', {
      from: 'noreply@tudominio.com',
      to: 'destinatario@ejemplo.com',
      subject: 'Prueba de API',
      body: 'Este es un correo de prueba desde la API'
    }, {
      headers: {
        'Authorization': 'Bearer tu-token-aqui',
        'Content-Type': 'application/json'
      }
    });

    console.log('Email enviado:', response.data.data.message_id);
  } catch (error) {
    console.error('Error:', error.response.data.error);
  }
};

sendEmail();
```

---

## ⚠️ Códigos de Error

| Código HTTP | Descripción |
|-------------|-------------|
| 200 | Éxito |
| 400 | Bad Request - Error en los datos enviados |
| 401 | Unauthorized - Token inválido o no proporcionado |
| 422 | Unprocessable Entity - Errores de validación |
| 429 | Too Many Requests - Límite de tasa excedido |
| 500 | Internal Server Error - Error del servidor |

---

## 🔒 Límites de Tasa

- **Por buzón**: Configurable en el panel (default: 1000 emails/día)
- **Por IP**: 60 peticiones/minuto
- **Envío en lote**: Máximo 100 emails por petición

---

## 💡 Mejores Prácticas

1. **Manejo de Errores**: Siempre implementa retry logic con backoff exponencial
2. **Validación**: Valida emails antes de enviar
3. **Logs**: Guarda los `message_id` para seguimiento
4. **Límites**: Respeta los límites de tasa
5. **Seguridad**: Nunca expongas tu token de API en código público
6. **Monitoreo**: Revisa el panel para ver estadísticas de envío

---

## 📊 Modo Sandbox

Para pruebas, puedes activar el modo sandbox en `.env`:

```env
MAILCORE_SANDBOX_MODE=true
```

En este modo:
- Los emails se registran en la base de datos
- No se envían realmente
- Útil para desarrollo y pruebas

---

## 🔍 Seguimiento de Emails

Puedes consultar el estado de un email usando el `send_log_id` o `message_id` directamente en el panel de administración:

1. Ve a **Envíos**
2. Busca por `message_id` o destinatario
3. Ve detalles completos: estado, rebotes, respuestas SMTP

---

## 📞 Soporte

Si encuentras problemas con la API:

1. Verifica que el dominio esté completamente verificado (SPF, DKIM, DMARC)
2. Revisa que el buzón tenga permisos de envío
3. Consulta los logs en el panel
4. Revisa `/var/log/mail.log` en el servidor
