<?php

namespace App\Services;

use App\Models\ComplianceLog;
use App\Models\ConsentRecord;
use App\Models\SuppressionList;
use App\Models\Unsubscribe;
use App\Models\SendLog;
use App\Models\Mailbox;

class ComplianceService
{
    /**
     * Check if email can be sent (comprehensive compliance check).
     */
    public function canSendEmail(string $toEmail, string $fromEmail, string $type = 'transactional'): array
    {
        $checks = [
            'can_send' => true,
            'reasons' => [],
        ];

        // 1. Check suppression list (always check)
        if (SuppressionList::isSuppressed($toEmail)) {
            $checks['can_send'] = false;
            $checks['reasons'][] = 'Email is in suppression list';
        }

        // 2. Check unsubscribe list (only for marketing emails)
        // Transactional emails can be sent even if user unsubscribed from marketing
        if ($type === 'marketing' && Unsubscribe::isUnsubscribed($toEmail)) {
            $checks['can_send'] = false;
            $checks['reasons'][] = 'User has unsubscribed from marketing emails';
        }

        // 3. Check consent (for marketing emails)
        if ($type === 'marketing') {
            if (!ConsentRecord::hasValidConsent($toEmail, 'marketing')) {
                $checks['can_send'] = false;
                $checks['reasons'][] = 'No valid marketing consent (GDPR)';
            }
        }

        // 4. Check sender reputation
        $mailbox = Mailbox::where('email', $fromEmail)->first();
        if ($mailbox && !$mailbox->canSendEmail()) {
            $checks['can_send'] = false;
            $checks['reasons'][] = 'Sender has reached daily limit or is inactive';
        }

        // Log compliance check
        ComplianceLog::logAction(
            'email_send_check',
            sprintf(
                'Compliance check for sending to %s from %s (Type: %s). Result: %s',
                $toEmail,
                $fromEmail,
                $type,
                $checks['can_send'] ? 'ALLOWED' : 'BLOCKED'
            ),
            $toEmail,
            'internal',
            $checks['can_send'],
            'SendLog',
            null,
            $checks
        );

        return $checks;
    }

    /**
     * Process GDPR data export request.
     */
    public function exportUserData(string $email): array
    {
        $data = [
            'email' => $email,
            'export_date' => now()->toIso8601String(),
            'data' => [
                'send_logs' => SendLog::where('to_email', $email)
                    ->orWhere('from_email', $email)
                    ->get()
                    ->toArray(),
                'consent_records' => ConsentRecord::where('email', $email)->get()->toArray(),
                'unsubscribes' => Unsubscribe::where('email', $email)->get()->toArray(),
                'suppressions' => SuppressionList::where('email', $email)->get()->toArray(),
            ],
        ];

        // Log GDPR export
        ComplianceLog::logGdpr(
            'gdpr_export',
            $email,
            'User data exported for GDPR compliance',
            ['record_count' => count($data['data'])]
        );

        return $data;
    }

    /**
     * Process GDPR data deletion request.
     */
    public function deleteUserData(string $email, bool $hardDelete = false): array
    {
        $deletedCounts = [];

        if ($hardDelete) {
            // Hard delete - remove all traces
            $deletedCounts['send_logs'] = SendLog::where('to_email', $email)
                ->orWhere('from_email', $email)
                ->delete();
            $deletedCounts['consent_records'] = ConsentRecord::where('email', $email)->delete();
            $deletedCounts['unsubscribes'] = Unsubscribe::where('email', $email)->delete();
        } else {
            // Soft delete - anonymize
            SendLog::where('to_email', $email)
                ->orWhere('from_email', $email)
                ->update([
                    'to_email' => 'anonymized@deleted.local',
                    'from_email' => 'anonymized@deleted.local',
                    'subject' => '[DELETED]',
                    'body_preview' => '[DELETED]',
                ]);

            $deletedCounts['anonymized'] = true;
        }

        // Always keep suppression to prevent re-sending
        SuppressionList::suppress(
            $email,
            'gdpr_request',
            'gdpr_deletion',
            null,
            ['deletion_date' => now()->toIso8601String()]
        );

        // Log GDPR deletion
        ComplianceLog::logGdpr(
            'gdpr_deletion',
            $email,
            'User data deleted/anonymized for GDPR compliance',
            $deletedCounts
        );

        return $deletedCounts;
    }

    /**
     * Validate email content for compliance.
     */
    public function validateEmailContent(string $subject, string $body, string $fromEmail, string $type = 'transactional'): array
    {
        $issues = [];

        // CAN-SPAM Act requirements - stricter for marketing emails
        if ($type === 'marketing') {
            if (!$this->hasUnsubscribeLink($body)) {
                $issues[] = 'Missing unsubscribe link (CAN-SPAM Act)';
            }

            if (!$this->hasPhysicalAddress($body)) {
                $issues[] = 'Missing physical address (CAN-SPAM Act)';
            }
        }

        if ($this->hasDeceptiveSubject($subject, $body)) {
            $issues[] = 'Subject line may be deceptive (CAN-SPAM Act)';
        }

        // Spam trigger words - only warn, don't block
        $spamScore = $this->calculateSpamScore($subject, $body);
        if ($spamScore > 8) { // Increased threshold from 5 to 8
            $issues[] = sprintf('High spam score: %d/10 (contains spam trigger words)', $spamScore);
        }

        return [
            'compliant' => empty($issues),
            'issues' => $issues,
            'spam_score' => $spamScore,
        ];
    }

    /**
     * Check if email has unsubscribe link.
     */
    protected function hasUnsubscribeLink(string $body): bool
    {
        return preg_match('/unsubscribe|opt-out|remove me/i', $body);
    }

    /**
     * Check if email has physical address.
     */
    protected function hasPhysicalAddress(string $body): bool
    {
        // Basic check for address pattern
        return preg_match('/\d+.*\b(street|st|avenue|ave|road|rd|boulevard|blvd)\b/i', $body);
    }

    /**
     * Check for deceptive subject lines.
     */
    protected function hasDeceptiveSubject(string $subject, string $body): bool
    {
        $deceptivePatterns = [
            'RE:',
            'FW:',
            'Re:',
            'Fwd:',
        ];

        foreach ($deceptivePatterns as $pattern) {
            if (str_starts_with($subject, $pattern) && !str_contains($body, 'previous message')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate spam score based on content.
     */
    protected function calculateSpamScore(string $subject, string $body): int
    {
        $score = 0;
        $content = $subject . ' ' . $body;

        $spamTriggers = [
            'free' => 1,
            'win' => 1,
            'winner' => 1,
            'cash' => 1,
            'prize' => 1,
            'click here' => 2,
            'buy now' => 2,
            'order now' => 2,
            'limited time' => 1,
            'act now' => 2,
            'urgent' => 1,
            'congratulations' => 1,
            '100%' => 1,
            'guarantee' => 1,
            'risk-free' => 1,
            'no obligation' => 1,
            'viagra' => 3,
            'cialis' => 3,
            'weight loss' => 2,
            'make money' => 2,
            '$$$' => 2,
        ];

        foreach ($spamTriggers as $trigger => $points) {
            if (stripos($content, $trigger) !== false) {
                $score += $points;
            }
        }

        // Check for excessive caps
        $capsRatio = $this->calculateCapsRatio($content);
        if ($capsRatio > 0.3) {
            $score += 2;
        }

        // Check for excessive exclamation marks
        $exclamationCount = substr_count($content, '!');
        if ($exclamationCount > 3) {
            $score += 1;
        }

        return min($score, 10);
    }

    /**
     * Calculate ratio of capital letters.
     */
    protected function calculateCapsRatio(string $text): float
    {
        $letters = preg_replace('/[^a-zA-Z]/', '', $text);
        if (strlen($letters) === 0) {
            return 0;
        }

        $caps = preg_replace('/[^A-Z]/', '', $text);
        return strlen($caps) / strlen($letters);
    }

    /**
     * Generate compliance report for domain.
     */
    public function generateComplianceReport(int $domainId, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        return [
            'domain_id' => $domainId,
            'period' => [
                'from' => $startDate->toDateString(),
                'to' => now()->toDateString(),
            ],
            'metrics' => [
                'total_sent' => SendLog::where('domain_id', $domainId)
                    ->where('created_at', '>=', $startDate)
                    ->count(),
                'bounces' => SendLog::where('domain_id', $domainId)
                    ->where('created_at', '>=', $startDate)
                    ->where('status', 'bounced')
                    ->count(),
                'spam_complaints' => SpamComplaint::whereHas('sendLog', function ($query) use ($domainId, $startDate) {
                    $query->where('domain_id', $domainId)
                        ->where('created_at', '>=', $startDate);
                })->count(),
                'unsubscribes' => Unsubscribe::where('domain_id', $domainId)
                    ->where('created_at', '>=', $startDate)
                    ->count(),
            ],
            'compliance_checks' => ComplianceLog::where('created_at', '>=', $startDate)
                ->where('entity_type', 'Domain')
                ->where('entity_id', $domainId)
                ->count(),
            'non_compliant_actions' => ComplianceLog::where('created_at', '>=', $startDate)
                ->where('entity_type', 'Domain')
                ->where('entity_id', $domainId)
                ->where('compliant', false)
                ->get(),
        ];
    }
}
