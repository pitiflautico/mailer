<?php

namespace App\Observers;

use App\Models\Mailbox;
use Illuminate\Support\Facades\Log;

class MailboxObserver
{
    /**
     * Handle the Mailbox "created" event.
     */
    public function created(Mailbox $mailbox): void
    {
        $this->createMailDirectory($mailbox);
        $this->startWarmup($mailbox);
    }

    /**
     * Handle the Mailbox "updated" event.
     */
    public function updated(Mailbox $mailbox): void
    {
        // Si el buzón se reactiva, asegurarse de que el directorio existe
        if ($mailbox->is_active && $mailbox->wasChanged('is_active')) {
            $this->createMailDirectory($mailbox);
        }
    }

    /**
     * Handle the Mailbox "deleted" event.
     */
    public function deleted(Mailbox $mailbox): void
    {
        // Opcionalmente, podrías archivar o eliminar el directorio de correo
        // Por seguridad, mejor no eliminar automáticamente
        Log::info("Mailbox deleted: {$mailbox->email}. Mail directory preserved.");
    }

    /**
     * Create the mail directory for the mailbox.
     */
    protected function createMailDirectory(Mailbox $mailbox): void
    {
        try {
            // Obtener dominio y usuario del email
            [$user, $domain] = explode('@', $mailbox->email);

            // Ruta del directorio de correo
            $mailPath = "/var/mail/vhosts/{$domain}/{$user}";

            // Crear el directorio si no existe
            if (!file_exists($mailPath)) {
                // Crear directorio con permisos 770
                mkdir($mailPath, 0770, true);

                // Cambiar propietario a vmail:vmail (UID/GID 5000)
                chown($mailPath, 'vmail');
                chgrp($mailPath, 'vmail');

                // Asegurar permisos correctos en el directorio del dominio también
                $domainPath = "/var/mail/vhosts/{$domain}";
                if (file_exists($domainPath)) {
                    chown($domainPath, 'vmail');
                    chgrp($domainPath, 'vmail');
                    chmod($domainPath, 0770);
                }

                Log::info("Mail directory created for: {$mailbox->email} at {$mailPath}");
            }

            // Crear subdirectorios Maildir estándar
            $subdirs = ['cur', 'new', 'tmp'];
            foreach ($subdirs as $subdir) {
                $subdirPath = "{$mailPath}/{$subdir}";
                if (!file_exists($subdirPath)) {
                    mkdir($subdirPath, 0770, true);
                    chown($subdirPath, 'vmail');
                    chgrp($subdirPath, 'vmail');
                }
            }

        } catch (\Exception $e) {
            Log::error("Failed to create mail directory for {$mailbox->email}: " . $e->getMessage());
        }
    }

    /**
     * Start automated warmup for a new mailbox.
     */
    protected function startWarmup(Mailbox $mailbox): void
    {
        try {
            // Only start warmup if enabled in config and mailbox can send
            if (!config('mailcore.features.auto_warmup', true) || !$mailbox->can_send) {
                return;
            }

            $warmupService = app(\App\Services\WarmupService::class);
            $warmupService->startWarmup($mailbox, 30);

            Log::info("Warmup started automatically for: {$mailbox->email}");
        } catch (\Exception $e) {
            Log::error("Failed to start warmup for {$mailbox->email}: " . $e->getMessage());
        }
    }
}
