<?php

namespace Database\Seeders;

use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\SendLog;
use App\Models\Bounce;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SendLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $domain = Domain::first();
        $mailbox = Mailbox::first();

        if (!$domain || !$mailbox) {
            $this->command->warn('⚠ No hay dominios o buzones');
            return;
        }

        // Logs exitosos (últimos 30 días)
        for ($i = 0; $i < 50; $i++) {
            $daysAgo = rand(0, 30);
            $createdAt = now()->subDays($daysAgo)->subHours(rand(0, 23));

            SendLog::create([
                'domain_id' => $domain->id,
                'mailbox_id' => $mailbox->id,
                'message_id' => Str::random(32) . '@ejemplo.com',
                'from_email' => $mailbox->email,
                'to_email' => 'usuario' . $i . '@test.com',
                'subject' => $this->getRandomSubject(),
                'body_preview' => 'Este es un correo de prueba generado automáticamente para testing...',
                'status' => 'delivered',
                'smtp_code' => 250,
                'smtp_response' => '250 2.0.0 OK',
                'sent_at' => $createdAt,
                'delivered_at' => $createdAt->copy()->addSeconds(rand(1, 30)),
                'created_at' => $createdAt,
                'headers' => [
                    'X-Mailer' => 'MailCore v1.0',
                    'X-Priority' => '3',
                ],
                'metadata' => [
                    'campaign' => 'test_campaign',
                    'user_id' => rand(1, 100),
                ],
            ]);
        }

        // Algunos rebotes
        for ($i = 0; $i < 5; $i++) {
            $daysAgo = rand(0, 10);
            $createdAt = now()->subDays($daysAgo);

            $sendLog = SendLog::create([
                'domain_id' => $domain->id,
                'mailbox_id' => $mailbox->id,
                'message_id' => Str::random(32) . '@ejemplo.com',
                'from_email' => $mailbox->email,
                'to_email' => 'invalid' . $i . '@noexiste.com',
                'subject' => 'Email que rebotó ' . $i,
                'body_preview' => 'Este correo fue rechazado por el servidor destinatario...',
                'status' => 'bounced',
                'smtp_code' => 550,
                'smtp_response' => '550 5.1.1 User not found',
                'bounced_at' => $createdAt,
                'created_at' => $createdAt,
                'attempts' => rand(1, 3),
            ]);

            // Crear registro de bounce
            Bounce::create([
                'send_log_id' => $sendLog->id,
                'message_id' => $sendLog->message_id,
                'recipient_email' => $sendLog->to_email,
                'bounce_type' => 'hard',
                'bounce_category' => 'invalid_address',
                'smtp_code' => 550,
                'smtp_response' => '550 5.1.1 User not found',
                'diagnostic_code' => 'smtp; 550 5.1.1 <' . $sendLog->to_email . '>: Recipient address rejected: User unknown',
                'raw_message' => 'Full bounce message would be here...',
                'is_suppressed' => $i >= 3, // Los últimos 2 están suprimidos
            ]);
        }

        $this->command->info('✓ Logs de envío creados (50 exitosos, 5 rebotados)');
    }

    /**
     * Get random email subject.
     */
    protected function getRandomSubject(): string
    {
        $subjects = [
            'Confirmación de registro',
            'Recuperación de contraseña',
            'Nueva notificación',
            'Actualización de cuenta',
            'Boletín mensual',
            'Recordatorio importante',
            'Confirmación de pedido',
            'Estado de envío',
            'Factura disponible',
            'Bienvenido a MailCore',
        ];

        return $subjects[array_rand($subjects)];
    }
}
