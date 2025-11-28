# Sistema de Warmup Automático

## ¿Qué es?

El sistema de warmup automático construye gradualmente la reputación de nuevos buzones de correo enviando correos de prueba en volúmenes crecientes durante 30 días.

## Características

### ✅ Automático
- Se activa automáticamente al crear un nuevo buzón
- No requiere intervención manual
- Progresa automáticamente día a día

### 📊 Gradual
```
Día 1-3:   5 emails/día
Día 4-7:   10 emails/día
Día 8-14:  20 emails/día
Día 15-21: 50 emails/día
Día 22-30: 100 emails/día
Día 30+:   Warmup completado
```

### 🎯 Inteligente
- Genera subjects y contenidos naturales
- Espaciado entre envíos
- Tracking de progreso

## Instalación

### 1. Ejecutar migración
```bash
php artisan migrate
```

### 2. Configurar cron job
Agregar al crontab para ejecutar cada 2 horas:
```bash
0 */2 * * * cd /var/www/mail && php artisan mailcore:process-warmup >> /dev/null 2>&1
```

O agregarlo a `app/Console/Kernel.php`:
```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('mailcore:process-warmup')
        ->everyTwoHours()
        ->withoutOverlapping();
}
```

## Uso

### Inicio automático
Cuando creas un nuevo buzón, el warmup se inicia automáticamente:
```php
$mailbox = Mailbox::create([
    'email' => 'nuevo@dominio.com',
    'password' => 'password123',
    // ... otros campos
]);
// Warmup se inicia automáticamente
```

### Comandos manuales

#### Iniciar warmup
```bash
php artisan mailcore:warmup start hi@glooplay.com
php artisan mailcore:warmup start hi@glooplay.com --days=60  # Custom duration
```

#### Ver estado
```bash
php artisan mailcore:warmup status hi@glooplay.com
```

Salida:
```
Warmup Status for hi@glooplay.com:
+----------+-------------------------+
| Property | Value                   |
+----------+-------------------------+
| Status   | active                  |
| Day      | 5 / 30                  |
| Progress | 16.7%                   |
| Today    | 3 / 10                  |
| Started  | 2025-11-28 10:00:00     |
+----------+-------------------------+
```

#### Listar warmups activos
```bash
php artisan mailcore:warmup list
```

#### Pausar warmup
```bash
php artisan mailcore:warmup stop hi@glooplay.com
```

#### Procesar warmups (ejecutado por cron)
```bash
php artisan mailcore:process-warmup
```

## Configuración

### Activar/desactivar warmup automático
En `config/mailcore.php`:
```php
'features' => [
    'auto_warmup' => true,  // false para desactivar
],

'warmup' => [
    'recipients' => [
        'warmup@mail-tester.com',
        // Agregar más destinos de warmup aquí
    ],
],
```

### Personalizar cronograma
Editar en `app/Models/WarmupSchedule.php`:
```php
public static function getTargetForDay(int $day): int
{
    return match (true) {
        $day <= 3 => 5,      // Modificar valores aquí
        $day <= 7 => 10,
        $day <= 14 => 20,
        $day <= 21 => 50,
        $day <= 30 => 100,
        default => 150,
    };
}
```

## API

### Obtener estado de warmup
```php
use App\Services\WarmupService;

$warmupService = app(WarmupService::class);
$status = $warmupService->getWarmupStatus($mailbox);

if ($status) {
    echo "Progress: {$status['progress']}%";
    echo "Day: {$status['day']}/{$status['target_day']}";
}
```

### Iniciar warmup programáticamente
```php
$warmupService = app(WarmupService::class);
$schedule = $warmupService->startWarmup($mailbox, 30);
```

## Monitoreo

### Base de datos
```sql
-- Ver todos los warmups activos
SELECT m.email, ws.day, ws.emails_sent_today, ws.emails_target_today, ws.status
FROM warmup_schedules ws
JOIN mailboxes m ON m.id = ws.mailbox_id
WHERE ws.status = 'active';

-- Ver progreso de warmup específico
SELECT *
FROM warmup_schedules
WHERE mailbox_id = (SELECT id FROM mailboxes WHERE email = 'hi@glooplay.com');
```

### Logs
```bash
tail -f storage/logs/laravel.log | grep -i warmup
```

## Troubleshooting

### El warmup no avanza
```bash
# Verificar que el cron está ejecutándose
php artisan mailcore:process-warmup

# Ver logs
tail storage/logs/laravel.log
```

### Reiniciar warmup
```bash
# Pausar el actual
php artisan mailcore:warmup stop hi@glooplay.com

# Iniciar uno nuevo
php artisan mailcore:warmup start hi@glooplay.com
```

### Desactivar para buzón específico
```sql
UPDATE warmup_schedules
SET status = 'paused'
WHERE mailbox_id = (SELECT id FROM mailboxes WHERE email = 'hi@glooplay.com');
```

## Mejores Prácticas

1. **Dejar que complete** - No pausar antes de 30 días
2. **No enviar volumen extra** - Respetar límites de warmup
3. **Monitorear spam rate** - Usar Google Postmaster Tools
4. **Combinar con warmup manual** - Pedir a conocidos que interactúen

## Próximos Pasos

Después del warmup automático (30 días):
1. Continuar con warmup manual con usuarios reales
2. Aumentar volumen gradualmente
3. Monitorear métricas de entrega
4. Ajustar basado en feedback

## Recursos

- Google Postmaster Tools: https://postmaster.google.com/
- Mail-Tester: https://www.mail-tester.com/
- Warmup Guide: `docs/EMAIL_WARMUP_GUIDE.md`
