<?php

namespace App\Filament\Pages;

use App\Models\SendLog;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Process;

class SystemStatus extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static string $view = 'filament.pages.system-status';

    protected static ?string $navigationLabel = 'Estado del Sistema';

    protected static ?string $title = 'Estado del Sistema';

    protected static ?int $navigationSort = 99;

    protected static ?string $navigationGroup = 'Sistema';

    public function getViewData(): array
    {
        return [
            'services' => $this->checkServices(),
            'mailConfig' => $this->getMailConfig(),
            'recentLogs' => $this->getRecentLogs(),
            'systemInfo' => $this->getSystemInfo(),
            'smtpTest' => $this->testSmtpConnection(),
        ];
    }

    protected function checkServices(): array
    {
        $services = [];

        // Check Postfix
        $postfixRunning = $this->isProcessRunning('postfix') || $this->isProcessRunning('master');
        $services[] = [
            'name' => 'Postfix (SMTP Server)',
            'status' => $postfixRunning ? 'running' : 'stopped',
            'description' => $postfixRunning
                ? 'Servidor SMTP funcionando correctamente'
                : '⚠️ Postfix no está corriendo - Los correos NO se enviarán',
            'command' => $postfixRunning ? null : 'sudo systemctl start postfix',
        ];

        // Check Dovecot
        $dovecotRunning = $this->isProcessRunning('dovecot');
        $services[] = [
            'name' => 'Dovecot (IMAP/POP3)',
            'status' => $dovecotRunning ? 'running' : 'stopped',
            'description' => $dovecotRunning
                ? 'Servidor de recepción funcionando'
                : 'Dovecot no está corriendo (solo afecta recepción de correos)',
            'command' => $dovecotRunning ? null : 'sudo systemctl start dovecot',
        ];

        // Check OpenDKIM
        $opendkimRunning = $this->isProcessRunning('opendkim');
        $services[] = [
            'name' => 'OpenDKIM',
            'status' => $opendkimRunning ? 'running' : 'stopped',
            'description' => $opendkimRunning
                ? 'Firma DKIM activa'
                : 'OpenDKIM no está corriendo (correos se enviarán sin firma DKIM)',
            'command' => $opendkimRunning ? null : 'sudo systemctl start opendkim',
        ];

        // Check SMTP Port
        $smtpPort = $this->isPortListening(587) || $this->isPortListening(25);
        $services[] = [
            'name' => 'Puerto SMTP (25/587)',
            'status' => $smtpPort ? 'open' : 'closed',
            'description' => $smtpPort
                ? 'Puerto SMTP abierto y escuchando'
                : '⚠️ Puerto SMTP cerrado - Verifica firewall',
            'command' => null,
        ];

        return $services;
    }

    protected function getMailConfig(): array
    {
        return [
            'Mailer' => config('mail.default'),
            'Host' => config('mail.mailers.smtp.host'),
            'Port' => config('mail.mailers.smtp.port'),
            'Encryption' => config('mail.mailers.smtp.encryption'),
            'From Address' => config('mail.from.address'),
            'From Name' => config('mail.from.name'),
            'Sandbox Mode' => config('mailcore.features.sandbox_mode') ? 'Activado (NO envía correos reales)' : 'Desactivado',
        ];
    }

    protected function getRecentLogs(): array
    {
        return SendLog::latest()
            ->take(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'from' => $log->from_email,
                    'to' => $log->to_email,
                    'subject' => $log->subject,
                    'status' => $log->status,
                    'smtp_code' => $log->smtp_code,
                    'created_at' => $log->created_at->format('d/m/Y H:i:s'),
                ];
            })
            ->toArray();
    }

    protected function getSystemInfo(): array
    {
        return [
            'PHP Version' => PHP_VERSION,
            'Laravel Version' => app()->version(),
            'Environment' => config('app.env'),
            'Debug Mode' => config('app.debug') ? 'Activado' : 'Desactivado',
            'Timezone' => config('app.timezone'),
            'DKIM Path' => config('mailcore.dkim_path'),
            'DKIM Path Exists' => is_dir(config('mailcore.dkim_path')) ? 'Sí' : 'No',
            'DKIM Path Writable' => is_writable(config('mailcore.dkim_path')) ? 'Sí' : 'No',
        ];
    }

    protected function testSmtpConnection(): array
    {
        try {
            $host = config('mail.mailers.smtp.host');
            $port = config('mail.mailers.smtp.port');

            $connection = @fsockopen($host, $port, $errno, $errstr, 5);

            if ($connection) {
                fclose($connection);
                return [
                    'success' => true,
                    'message' => "✅ Conexión exitosa a {$host}:{$port}",
                ];
            }

            return [
                'success' => false,
                'message' => "❌ No se puede conectar a {$host}:{$port} - Error: {$errstr} ({$errno})",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => "❌ Error al probar conexión: " . $e->getMessage(),
            ];
        }
    }

    protected function isProcessRunning(string $process): bool
    {
        $result = Process::run("ps aux | grep -v grep | grep {$process}");
        return $result->successful() && !empty(trim($result->output()));
    }

    protected function isPortListening(int $port): bool
    {
        $result = Process::run("netstat -tlnp 2>/dev/null | grep \":{$port} \" || ss -tlnp 2>/dev/null | grep \":{$port} \"");
        return $result->successful() && !empty(trim($result->output()));
    }
}
