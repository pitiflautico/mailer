<?php

namespace App\Services;

use App\Models\SendLog;
use App\Models\Bounce;
use Illuminate\Support\Facades\DB;

class PostfixLogParser
{
    protected string $logFile;
    protected int $lastPosition = 0;

    public function __construct()
    {
        $this->logFile = config('mailcore.postfix_log', '/var/log/mail.log');
    }

    /**
     * Parse Postfix logs and update database.
     */
    public function parse(): array
    {
        $stats = [
            'processed' => 0,
            'sent' => 0,
            'bounced' => 0,
            'deferred' => 0,
        ];

        if (!file_exists($this->logFile)) {
            \Log::warning("Postfix log file not found: {$this->logFile}");
            return $stats;
        }

        try {
            $handle = fopen($this->logFile, 'r');
            if (!$handle) {
                throw new \Exception("Could not open log file");
            }

            // Seek to last known position
            if ($this->lastPosition > 0) {
                fseek($handle, $this->lastPosition);
            }

            while (($line = fgets($handle)) !== false) {
                $this->parseLine($line, $stats);
                $stats['processed']++;
            }

            // Save current position
            $this->lastPosition = ftell($handle);
            fclose($handle);

            return $stats;
        } catch (\Exception $e) {
            \Log::error('Error parsing Postfix logs: ' . $e->getMessage());
            return $stats;
        }
    }

    /**
     * Parse a single log line.
     */
    protected function parseLine(string $line, array &$stats): void
    {
        // Extract message ID
        if (preg_match('/postfix\/\w+\[\d+\]: ([A-F0-9]+):/', $line, $matches)) {
            $queueId = $matches[1];

            // Check for sent status
            if (str_contains($line, 'status=sent')) {
                $this->handleSentStatus($queueId, $line);
                $stats['sent']++;
            }

            // Check for bounced status
            if (str_contains($line, 'status=bounced')) {
                $this->handleBouncedStatus($queueId, $line);
                $stats['bounced']++;
            }

            // Check for deferred status
            if (str_contains($line, 'status=deferred')) {
                $this->handleDeferredStatus($queueId, $line);
                $stats['deferred']++;
            }

            // Extract email addresses and subject
            if (preg_match('/from=<([^>]+)>/', $line, $matches)) {
                $this->handleFromAddress($queueId, $matches[1]);
            }

            if (preg_match('/to=<([^>]+)>/', $line, $matches)) {
                $this->handleToAddress($queueId, $matches[1]);
            }
        }
    }

    /**
     * Handle sent status.
     */
    protected function handleSentStatus(string $queueId, string $line): void
    {
        // Extract SMTP response
        preg_match('/\((.+?)\)/', $line, $matches);
        $smtpResponse = $matches[1] ?? null;

        // Extract SMTP code
        preg_match('/status=sent \((\d{3})/', $line, $matches);
        $smtpCode = $matches[1] ?? 250;

        SendLog::where('message_id', $queueId)
            ->update([
                'status' => 'delivered',
                'smtp_response' => $smtpResponse,
                'smtp_code' => $smtpCode,
                'delivered_at' => now(),
            ]);
    }

    /**
     * Handle bounced status.
     */
    protected function handleBouncedStatus(string $queueId, string $line): void
    {
        // Extract SMTP response
        preg_match('/\((.+?)\)/', $line, $matches);
        $smtpResponse = $matches[1] ?? 'Unknown bounce reason';

        // Extract SMTP code
        preg_match('/status=bounced \((\d{3})/', $line, $matches);
        $smtpCode = $matches[1] ?? 550;

        $sendLog = SendLog::where('message_id', $queueId)->first();

        if ($sendLog) {
            $sendLog->update([
                'status' => 'bounced',
                'smtp_response' => $smtpResponse,
                'smtp_code' => $smtpCode,
                'bounced_at' => now(),
            ]);

            // Create bounce record
            Bounce::create([
                'send_log_id' => $sendLog->id,
                'message_id' => $queueId,
                'recipient_email' => $sendLog->to_email,
                'bounce_type' => Bounce::determineBounceTypeFromCode((int)$smtpCode),
                'bounce_category' => Bounce::determineBounceCategory($smtpResponse),
                'smtp_code' => $smtpCode,
                'smtp_response' => $smtpResponse,
                'raw_message' => $line,
            ]);
        }
    }

    /**
     * Handle deferred status.
     */
    protected function handleDeferredStatus(string $queueId, string $line): void
    {
        preg_match('/\((.+?)\)/', $line, $matches);
        $smtpResponse = $matches[1] ?? null;

        SendLog::where('message_id', $queueId)
            ->update([
                'status' => 'deferred',
                'smtp_response' => $smtpResponse,
                'attempts' => DB::raw('attempts + 1'),
            ]);
    }

    /**
     * Handle from address.
     */
    protected function handleFromAddress(string $queueId, string $email): void
    {
        SendLog::updateOrCreate(
            ['message_id' => $queueId],
            ['from_email' => $email]
        );
    }

    /**
     * Handle to address.
     */
    protected function handleToAddress(string $queueId, string $email): void
    {
        SendLog::where('message_id', $queueId)
            ->update(['to_email' => $email]);
    }

    /**
     * Get last parsed position.
     */
    public function getLastPosition(): int
    {
        return $this->lastPosition;
    }

    /**
     * Set last parsed position.
     */
    public function setLastPosition(int $position): void
    {
        $this->lastPosition = $position;
    }
}
