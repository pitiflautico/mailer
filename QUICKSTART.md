# ⚡ MailCore - QuickStart Local

## 🚀 Inicio Rápido (5 minutos)

### Opción 1: Script Automático (Recomendado)

```bash
# Ejecutar script de setup
bash setup-local.sh
```

El script te guiará paso a paso y configurará todo automáticamente.

---

### Opción 2: Manual Rápido

```bash
# 1. Instalar dependencias
composer install

# 2. Configurar
cp .env.example .env

# 3. Base de datos SQLite
touch database/database.sqlite

# Editar .env:
# DB_CONNECTION=sqlite
# DB_DATABASE=/ruta/completa/a/database/database.sqlite
# MAILCORE_SANDBOX_MODE=true

# 4. Generar key
php artisan key:generate

# 5. Migrar
php artisan migrate

# 6. Datos de prueba (opcional)
php artisan db:seed

# 7. Usuario admin
php artisan make:filament-user

# 8. Iniciar servidor
php artisan serve
```

Accede a: http://localhost:8000/admin

---

### Opción 3: Docker (Todo incluido)

```bash
# Levantar servicios
docker-compose up -d

# Esperar que inicialice (30 seg)
docker-compose logs -f app

# Crear usuario admin
docker-compose exec app php artisan make:filament-user
```

**Accesos:**
- Panel: http://localhost:8000/admin
- Mailpit (ver correos): http://localhost:8025
- phpMyAdmin: http://localhost:8080

---

## 🎯 ¿Qué probar?

### 1. Panel de Administración

**Dashboard:**
- Estadísticas generales
- Gráficos de envíos
- Últimos envíos

**Dominios:**
- Ver dominios de prueba
- Intentar crear uno nuevo
- Ver instrucciones DNS

**Buzones:**
- Ver buzones existentes
- Crear nuevo buzón
- Ver uso de cuotas

**Envíos:**
- Ver logs de envíos
- Filtrar por estado
- Ver detalles de envío

**Rebotes:**
- Ver correos rebotados
- Categorías de bounces

### 2. API REST

```bash
# Health check
curl http://localhost:8000/api/health

# Primero crea un token API en el panel (Settings)

# Enviar email de prueba (modo sandbox)
curl -X POST http://localhost:8000/api/send \
  -H "Authorization: Bearer tu-token" \
  -H "Content-Type: application/json" \
  -d '{
    "from": "noreply@ejemplo.com",
    "to": "test@test.com",
    "subject": "Test desde local",
    "body": "Este es un correo de prueba"
  }'
```

### 3. Comandos Artisan

```bash
# Ver dominios
php artisan mailcore:verify-domains

# Parsear logs (simulado en local)
php artisan mailcore:parse-logs

# Ver ayuda de comandos
php artisan mailcore:check-bounces --help
```

---

## 📋 Datos de Prueba

Si ejecutaste `php artisan db:seed`:

**Dominios:**
- `ejemplo.com` - Verificado completamente
- `test.com` - Sin verificar

**Buzones:**
- `noreply@ejemplo.com` - Password: `password123`
- `info@ejemplo.com` - Password: `password123`

**Envíos:**
- 50 correos entregados exitosamente
- 5 correos rebotados

---

## 🐛 Solución Rápida de Problemas

### Error: "No application encryption key"
```bash
php artisan key:generate
```

### Error: "Class 'Filament...' not found"
```bash
composer install
```

### Error: "database locked" (SQLite)
```bash
# Cambiar a MySQL o reiniciar servidor
php artisan serve
```

### Panel sin estilos
```bash
npm install
npm run dev
```

### Ver logs de errores
```bash
tail -f storage/logs/laravel.log
```

---

## 💡 Tips

1. **Modo Sandbox**: En local siempre usa `MAILCORE_SANDBOX_MODE=true` - los correos se guardan pero no se envían

2. **Datos Frescos**: Para resetear todo:
   ```bash
   php artisan migrate:fresh --seed
   ```

3. **Ver Correos**: Si usas Docker, todos los correos van a Mailpit en http://localhost:8025

4. **Testing API**: Usa Postman o Insomnia para probar endpoints

5. **Desarrollo**: Si modificas código, el servidor de Laravel recarga automáticamente

---

## 📚 Más Información

- **Guía Completa**: `TESTING.md`
- **Documentación API**: `API.md`
- **Instalación Producción**: `INSTALLATION.md`
- **README Principal**: `README.md`

---

## ✅ Checklist de Verificación

- [ ] Panel de admin carga correctamente
- [ ] Dashboard muestra estadísticas
- [ ] Puedes navegar por los módulos
- [ ] API `/health` responde
- [ ] Puedes enviar correos (modo sandbox)
- [ ] Los datos de prueba se muestran

Si todo funciona, ¡estás listo para desarrollar! 🎉

---

## 🆘 ¿Necesitas Ayuda?

1. Revisa `TESTING.md` para guía detallada
2. Verifica `storage/logs/laravel.log` para errores
3. Asegúrate de tener PHP 8.2+ y Composer instalados
