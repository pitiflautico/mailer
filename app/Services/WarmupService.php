<?php

namespace App\Services;

use App\Models\Mailbox;
use App\Models\WarmupSchedule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class WarmupService
{
    /**
     * Start warmup for a new mailbox.
     */
    public function startWarmup(Mailbox $mailbox, int $targetDays = 30): WarmupSchedule
    {
        return WarmupSchedule::create([
            'mailbox_id' => $mailbox->id,
            'day' => 1,
            'target_day' => $targetDays,
            'emails_sent_today' => 0,
            'emails_target_today' => WarmupSchedule::getTargetForDay(1),
            'status' => 'active',
            'started_at' => now(),
        ]);
    }

    /**
     * Send warmup emails for active schedules.
     */
    public function processWarmupEmails(): array
    {
        $results = [
            'processed' => 0,
            'sent' => 0,
            'errors' => 0,
        ];

        $activeSchedules = WarmupSchedule::where('status', 'active')
            ->with('mailbox')
            ->get();

        foreach ($activeSchedules as $schedule) {
            $results['processed']++;

            try {
                // Send warmup emails up to the daily target
                while ($schedule->canSendToday()) {
                    $this->sendWarmupEmail($schedule->mailbox);
                    $schedule->incrementSent();
                    $results['sent']++;

                    // Space out sends (don't send all at once)
                    if ($schedule->emails_sent_today % 5 === 0) {
                        break; // Send in batches of 5
                    }
                }
            } catch (\Exception $e) {
                $results['errors']++;
                Log::error("Warmup failed for mailbox {$schedule->mailbox->email}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Send a single warmup email.
     */
    protected function sendWarmupEmail(Mailbox $mailbox): void
    {
        // Get warmup recipients from config file
        $warmupRecipients = require base_path('config/warmup-recipients.php');

        $recipient = $warmupRecipients[array_rand($warmupRecipients)];

        // Use engaging templates for real Gmail addresses
        $isRealEmail = str_contains($recipient, '@gmail.com');

        if ($isRealEmail) {
            $subject = $this->generateEngagingSubject();
            $body = $this->generateEngagingBody($mailbox);
        } else {
            $subject = $this->generateWarmupSubject();
            $body = $this->generateWarmupBody();
        }

        Mail::html($body, function ($message) use ($mailbox, $recipient, $subject) {
            $message->from($mailbox->email)
                ->to($recipient)
                ->subject($subject);
        });
    }

    /**
     * Generate a natural-looking subject for warmup.
     */
    protected function generateWarmupSubject(): string
    {
        $subjects = [
            'Quick update',
            'Following up',
            'Checking in',
            'Your recent activity',
            'Weekly summary',
            'Account notification',
            'Important information',
            'Status update',
        ];

        return $subjects[array_rand($subjects)];
    }

    /**
     * Generate a natural-looking body for warmup.
     */
    protected function generateWarmupBody(): string
    {
        $templates = [
            '<p>Hi there,</p><p>Just wanted to send a quick update about your account.</p><p>Thanks,<br>The Team</p>',
            '<p>Hello,</p><p>This is a friendly reminder about your recent activity.</p><p>Best regards</p>',
            '<p>Hi,</p><p>We wanted to share some information with you.</p><p>Cheers</p>',
        ];

        return $templates[array_rand($templates)];
    }

    /**
     * Generate engaging subject for real contacts.
     */
    protected function generateEngagingSubject(): string
    {
        $subjects = [
            'Ayúdame con el warmup de GlooPlay 🎮',
            'Favor rápido - Sistema de correo GlooPlay',
            'Test de correo GlooPlay - necesito tu ayuda',
            'Probando nuevo correo para GlooPlay',
            'GlooPlay - Warmup del sistema de correo',
        ];

        return $subjects[array_rand($subjects)];
    }

    /**
     * Generate engaging body asking for interaction.
     */
    protected function generateEngagingBody(Mailbox $mailbox): string
    {
        $templates = [
            '<p>Hola!</p><p>Estoy configurando el sistema de correo de GlooPlay y necesito tu ayuda.</p><p><strong>¿Me ayudas con esto?</strong></p><ol><li>Si llegó a spam → marca como "No es spam"</li><li>Muévelo a inbox</li><li>Responde con "ok" o "👍"</li><li>Agrega ' . $mailbox->email . ' a contactos</li></ol><p>¡Gracias! Esto ayuda a que Gmail confíe en el dominio.</p>',

            '<p>Hey!</p><p>Necesito un favor rápido para el sistema de correo de GlooPlay.</p><p><strong>Solo necesitas:</strong></p><ul><li>📥 Marcar "No es spam" si está en spam</li><li>📨 Moverlo a bandeja principal</li><li>💬 Responder con cualquier cosa</li><li>📇 Agregar ' . $mailbox->email . ' a contactos</li></ul><p>¡Mil gracias! 🙌</p>',

            '<p>Hola!</p><p>Estoy montando el correo de GlooPlay y te necesito.</p><p><strong>Ayúdame así:</strong></p><ol><li>Si está en spam → "No es spam"</li><li>Mueve a inbox</li><li>Responde (aunque sea "vale")</li><li>Agrega ' . $mailbox->email . ' a contactos</li></ol><p>Gracias! Estás ayudando a que GlooPlay tenga un sistema profesional 🚀</p>',
        ];

        return $templates[array_rand($templates)];
    }

    /**
     * Get warmup status for a mailbox.
     */
    public function getWarmupStatus(Mailbox $mailbox): ?array
    {
        $schedule = $mailbox->warmupSchedule;

        if (!$schedule) {
            return null;
        }

        return [
            'status' => $schedule->status,
            'day' => $schedule->day,
            'target_day' => $schedule->target_day,
            'progress' => $schedule->getProgressPercentage(),
            'emails_sent_today' => $schedule->emails_sent_today,
            'emails_target_today' => $schedule->emails_target_today,
            'started_at' => $schedule->started_at,
            'completed_at' => $schedule->completed_at,
        ];
    }
}
