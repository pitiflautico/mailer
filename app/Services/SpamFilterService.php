<?php

namespace App\Services;

use App\Models\SuppressionList;
use App\Models\SpamComplaint;
use App\Models\IpReputation;

class SpamFilterService
{
    /**
     * Check if email should be filtered as spam.
     */
    public function shouldFilter(array $emailData): array
    {
        $reasons = [];
        $score = 0;

        // 1. Check recipient suppression
        if (SuppressionList::isSuppressed($emailData['to'])) {
            $reasons[] = 'Recipient is suppressed';
            $score += 100; // Auto-reject
        }

        // 2. Check sender reputation
        $senderScore = $this->checkSenderReputation($emailData['from']);
        if ($senderScore['score'] > 50) {
            $reasons[] = $senderScore['reason'];
            $score += $senderScore['score'];
        }

        // 3. Check IP reputation
        $ipScore = $this->checkIpReputation();
        if ($ipScore['score'] > 30) {
            $reasons[] = $ipScore['reason'];
            $score += $ipScore['score'];
        }

        // 4. Check content
        $contentScore = $this->analyzeContent(
            $emailData['subject'] ?? '',
            $emailData['body'] ?? ''
        );
        if ($contentScore['score'] > 40) {
            $reasons = array_merge($reasons, $contentScore['reasons']);
            $score += $contentScore['score'];
        }

        // 5. Check rate limits
        $rateLimitScore = $this->checkRateLimits($emailData['from']);
        if ($rateLimitScore['exceeded']) {
            $reasons[] = $rateLimitScore['reason'];
            $score += 50;
        }

        return [
            'should_filter' => $score >= 100,
            'spam_score' => $score,
            'reasons' => $reasons,
            'recommendation' => $this->getRecommendation($score),
        ];
    }

    /**
     * Check sender reputation.
     */
    protected function checkSenderReputation(string $email): array
    {
        $complaintRate = SpamComplaint::getComplaintRate($email);

        if ($complaintRate > 0.1) { // More than 0.1% complaint rate
            return [
                'score' => (int) ($complaintRate * 500),
                'reason' => sprintf('High spam complaint rate: %.2f%%', $complaintRate),
            ];
        }

        return ['score' => 0, 'reason' => ''];
    }

    /**
     * Check IP reputation.
     */
    protected function checkIpReputation(): array
    {
        $ip = request()->ip();
        $reputation = IpReputation::where('ip_address', $ip)->first();

        if (!$reputation) {
            return ['score' => 0, 'reason' => ''];
        }

        if ($reputation->is_blacklisted) {
            return [
                'score' => 100,
                'reason' => 'IP is blacklisted',
            ];
        }

        if ($reputation->reputation_score < 50) {
            return [
                'score' => 100 - $reputation->reputation_score,
                'reason' => sprintf('Low IP reputation score: %d/100', $reputation->reputation_score),
            ];
        }

        return ['score' => 0, 'reason' => ''];
    }

    /**
     * Analyze email content.
     */
    protected function analyzeContent(string $subject, string $body): array
    {
        $reasons = [];
        $score = 0;

        // Check spam trigger words
        $triggerScore = $this->checkSpamTriggers($subject . ' ' . $body);
        if ($triggerScore > 0) {
            $reasons[] = 'Contains spam trigger words';
            $score += $triggerScore;
        }

        // Check URL density
        $urlDensity = $this->calculateUrlDensity($body);
        if ($urlDensity > 0.3) {
            $reasons[] = 'High URL density';
            $score += 20;
        }

        // Check for suspicious patterns
        if ($this->hasPhishingPatterns($body)) {
            $reasons[] = 'Contains phishing patterns';
            $score += 50;
        }

        // Check HTML ratio
        if ($this->hasExcessiveHtml($body)) {
            $reasons[] = 'Excessive HTML markup';
            $score += 15;
        }

        return [
            'score' => $score,
            'reasons' => $reasons,
        ];
    }

    /**
     * Check spam trigger words.
     */
    protected function checkSpamTriggers(string $content): int
    {
        $score = 0;
        $highRiskWords = [
            'viagra' => 30,
            'cialis' => 30,
            'casino' => 25,
            'lottery' => 25,
            'winner' => 20,
            'congratulations' => 15,
            'free money' => 25,
            'earn money' => 20,
            'work from home' => 15,
            'weight loss' => 20,
            'diet pills' => 25,
            'bitcoin' => 15,
            'cryptocurrency' => 15,
            'investment opportunity' => 20,
            'click here now' => 20,
            'limited time offer' => 15,
        ];

        $content = strtolower($content);

        foreach ($highRiskWords as $word => $points) {
            if (str_contains($content, $word)) {
                $score += $points;
            }
        }

        return $score;
    }

    /**
     * Calculate URL density.
     */
    protected function calculateUrlDensity(string $text): float
    {
        if (empty($text)) {
            return 0;
        }

        $urlCount = preg_match_all('/https?:\/\/[^\s]+/', $text);
        $wordCount = str_word_count(strip_tags($text));

        if ($wordCount === 0) {
            return 0;
        }

        return $urlCount / $wordCount;
    }

    /**
     * Check for phishing patterns.
     */
    protected function hasPhishingPatterns(string $text): bool
    {
        $phishingPatterns = [
            '/verify.*account/i',
            '/update.*payment/i',
            '/suspended.*account/i',
            '/unusual.*activity/i',
            '/confirm.*identity/i',
            '/urgent.*action.*required/i',
            '/click.*immediately/i',
        ];

        foreach ($phishingPatterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check for excessive HTML.
     */
    protected function hasExcessiveHtml(string $text): bool
    {
        if (empty($text)) {
            return false;
        }

        $totalLength = strlen($text);
        $textLength = strlen(strip_tags($text));

        if ($totalLength === 0) {
            return false;
        }

        $htmlRatio = ($totalLength - $textLength) / $totalLength;

        return $htmlRatio > 0.7; // More than 70% HTML
    }

    /**
     * Check rate limits.
     */
    protected function checkRateLimits(string $email): array
    {
        // This would integrate with your rate limiting system
        // For now, we'll return a placeholder
        return [
            'exceeded' => false,
            'reason' => '',
        ];
    }

    /**
     * Get recommendation based on score.
     */
    protected function getRecommendation(int $score): string
    {
        return match(true) {
            $score >= 100 => 'REJECT',
            $score >= 70 => 'QUARANTINE',
            $score >= 40 => 'MARK_AS_SPAM',
            default => 'ALLOW',
        };
    }

    /**
     * Train spam filter with user feedback.
     */
    public function trainFilter(string $email, bool $isSpam): void
    {
        if ($isSpam) {
            SpamComplaint::record($email, 'spam', null, null, [
                'reported_by' => 'system_training',
                'feedback_type' => 'abuse',
            ]);
        }

        // In a real implementation, this would update ML models
        // For now, we just log it
    }
}
