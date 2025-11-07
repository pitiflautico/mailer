<?php

namespace App\Services;

use App\Models\Domain;
use App\Models\Mailbox;
use App\Models\SendLog;
use App\Models\Unsubscribe;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class MailService
{
    public function __construct(
        protected ComplianceService $complianceService,
        protected SpamFilterService $spamFilterService,
        protected IpReputationService $ipReputationService
    ) {}

    /**
     * Send an email through the system.
     */
    public function send(array $data): array
    {
        try {
            // Validate sender
            $from = $data['from'];
            $to = $data['to'];
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

            // 🛡️ COMPLIANCE CHECKS
            $complianceCheck = $this->complianceService->canSendEmail(
                $to,
                $from,
                $data['email_type'] ?? 'transactional'
            );

            if (!$complianceCheck['can_send']) {
                return [
                    'success' => false,
                    'error' => 'Compliance check failed: ' . implode(', ', $complianceCheck['reasons']),
                    'compliance_reasons' => $complianceCheck['reasons'],
                ];
            }

            // 🛡️ SPAM FILTER CHECKS
            $spamCheck = $this->spamFilterService->shouldFilter([
                'from' => $from,
                'to' => $to,
                'subject' => $data['subject'] ?? '',
                'body' => $data['body'] ?? '',
            ]);

            if ($spamCheck['should_filter']) {
                return [
                    'success' => false,
                    'error' => 'Email filtered as potential spam',
                    'spam_score' => $spamCheck['spam_score'],
                    'spam_reasons' => $spamCheck['reasons'],
                ];
            }

            // 🛡️ IP REPUTATION CHECK
            if (!$this->ipReputationService->canSend(request()->ip())) {
                return [
                    'success' => false,
                    'error' => 'IP address blocked due to poor reputation',
                ];
            }

            // 🛡️ CONTENT VALIDATION
            $contentValidation = $this->complianceService->validateEmailContent(
                $data['subject'] ?? '',
                $data['body'] ?? '',
                $from,
                $data['email_type'] ?? 'transactional'
            );

            if (!$contentValidation['compliant']) {
                return [
                    'success' => false,
                    'error' => 'Email content validation failed',
                    'validation_issues' => $contentValidation['issues'],
                ];
            }

            // Check sandbox mode
            if (config('mailcore.features.sandbox_mode')) {
                return $this->sendSandbox($data, $mailbox);
            }

            // Generate message ID
            $messageId = $this->generateMessageId($mailbox->domain);

            // 📧 Add unsubscribe link and headers
            $unsubscribeUrl = Unsubscribe::generateUrl($to, $mailbox->domain_id);
            $unsubscribeOneClick = route('unsubscribe.one-click', ['token' => Str::random(64)]);

            // Auto-append unsubscribe link to body if not present
            $body = $data['body'] ?? '';
            if (!str_contains($body, 'unsubscribe')) {
                $body .= "\n\n---\nTo unsubscribe from future emails, click here: {$unsubscribeUrl}";
            }

            // Create send log
            $sendLog = SendLog::create([
                'domain_id' => $mailbox->domain_id,
                'mailbox_id' => $mailbox->id,
                'message_id' => $messageId,
                'from_email' => $from,
                'to_email' => $to,
                'subject' => $data['subject'] ?? null,
                'body_preview' => Str::limit($body, 200),
                'status' => 'queued',
                'headers' => array_merge($data['headers'] ?? [], [
                    'List-Unsubscribe' => "<{$unsubscribeUrl}>, <{$unsubscribeOneClick}>",
                    'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
                ]),
                'metadata' => $data['metadata'] ?? [],
            ]);

            // Send email with compliance headers
            Mail::raw($body, function ($message) use ($data, $messageId, $unsubscribeUrl, $unsubscribeOneClick, $to) {
                $message->from($data['from'])
                    ->to($to)
                    ->subject($data['subject'] ?? 'No Subject')
                    ->getHeaders()
                    ->addTextHeader('Message-ID', $messageId)
                    ->addTextHeader('List-Unsubscribe', "<{$unsubscribeUrl}>, <{$unsubscribeOneClick}>")
                    ->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click')
                    ->addTextHeader('Precedence', 'bulk');

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
