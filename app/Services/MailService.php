<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\SendLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MailService
{
    /**
     * Send an email through the system.
     */
    public function send(array $data): array
    {
        try {
            // Validate sender
            $from = $data['from'];
            $mailbox = Mailbox::where('email', $from)->first();

            if (!$mailbox) {
                return [
                    'success' => false,
                    'error' => 'Invalid sender mailbox',
                ];
            }

            if (!$mailbox->canSendEmail()) {
                return [
                    'success' => false,
                    'error' => 'Mailbox cannot send emails (inactive or limit reached)',
                ];
            }

            // Check if domain is verified
            if (!$mailbox->domain->isFullyVerified()) {
                return [
                    'success' => false,
                    'error' => 'Domain is not fully verified (SPF, DKIM, DMARC)',
                ];
            }

            // Check sandbox mode
            if (config('mailcore.features.sandbox_mode')) {
                return $this->sendSandbox($data, $mailbox);
            }

            // Generate message ID
            $messageId = $this->generateMessageId($mailbox->domain);

            // Create send log
            $sendLog = SendLog::create([
                'domain_id' => $mailbox->domain_id,
                'mailbox_id' => $mailbox->id,
                'message_id' => $messageId,
                'from_email' => $from,
                'to_email' => $data['to'],
                'subject' => $data['subject'] ?? null,
                'body_preview' => Str::limit($data['body'] ?? '', 200),
                'status' => 'queued',
                'headers' => $data['headers'] ?? [],
                'metadata' => $data['metadata'] ?? [],
            ]);

            // Send email
            Mail::raw($data['body'] ?? '', function ($message) use ($data, $messageId) {
                $message->from($data['from'])
                    ->to($data['to'])
                    ->subject($data['subject'] ?? 'No Subject')
                    ->getHeaders()
                    ->addTextHeader('Message-ID', $messageId);

                if (isset($data['cc'])) {
                    $message->cc($data['cc']);
                }

                if (isset($data['bcc'])) {
                    $message->bcc($data['bcc']);
                }

                if (isset($data['reply_to'])) {
                    $message->replyTo($data['reply_to']);
                }
            });

            // Update send log
            $sendLog->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Increment mailbox send count
            $mailbox->incrementSendCount();

            return [
                'success' => true,
                'message_id' => $messageId,
                'send_log_id' => $sendLog->id,
            ];

        } catch (\Exception $e) {
            \Log::error('Mail sending failed: ' . $e->getMessage());

            if (isset($sendLog)) {
                $sendLog->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send email in sandbox mode (doesn't actually send).
     */
    protected function sendSandbox(array $data, Mailbox $mailbox): array
    {
        $messageId = $this->generateMessageId($mailbox->domain);

        SendLog::create([
            'domain_id' => $mailbox->domain_id,
            'mailbox_id' => $mailbox->id,
            'message_id' => $messageId,
            'from_email' => $data['from'],
            'to_email' => $data['to'],
            'subject' => $data['subject'] ?? null,
            'body_preview' => Str::limit($data['body'] ?? '', 200),
            'status' => 'delivered',
            'smtp_response' => 'Sandbox mode - email not actually sent',
            'smtp_code' => 250,
            'sent_at' => now(),
            'delivered_at' => now(),
            'metadata' => array_merge($data['metadata'] ?? [], ['sandbox' => true]),
        ]);

        return [
            'success' => true,
            'message_id' => $messageId,
            'sandbox' => true,
        ];
    }

    /**
     * Generate a unique message ID.
     */
    protected function generateMessageId(Domain $domain): string
    {
        return Str::random(32) . '@' . $domain->name;
    }

    /**
     * Send bulk emails.
     */
    public function sendBulk(array $emails): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($emails as $email) {
            $result = $this->send($email);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'email' => $email,
                    'error' => $result['error'],
                ];
            }
        }

        return $results;
    }
}
