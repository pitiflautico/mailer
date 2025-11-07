<?php

namespace App\Services;

use App\Models\IpReputation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class IpReputationService
{
    /**
     * Blacklist providers.
     */
    protected array $blacklistProviders = [
        'zen.spamhaus.org',
        'bl.spamcop.net',
        'b.barracudacentral.org',
        'dnsbl.sorbs.net',
    ];

    /**
     * Check IP reputation.
     */
    public function checkReputation(string $ipAddress): array
    {
        $reputation = IpReputation::getOrCreate($ipAddress);

        // Only check once per day
        if ($reputation->last_checked_at && $reputation->last_checked_at->isToday()) {
            return [
                'ip' => $ipAddress,
                'score' => $reputation->reputation_score,
                'blacklisted' => $reputation->is_blacklisted,
                'blacklist_sources' => $reputation->blacklist_sources ?? [],
            ];
        }

        // Check blacklists
        $blacklistResults = $this->checkBlacklists($ipAddress);

        $reputation->update([
            'is_blacklisted' => $blacklistResults['is_blacklisted'],
            'blacklist_sources' => $blacklistResults['sources'],
            'last_checked_at' => now(),
            'blacklisted_at' => $blacklistResults['is_blacklisted'] ? now() : null,
        ]);

        return [
            'ip' => $ipAddress,
            'score' => $reputation->reputation_score,
            'blacklisted' => $reputation->is_blacklisted,
            'blacklist_sources' => $reputation->blacklist_sources ?? [],
        ];
    }

    /**
     * Check multiple blacklists.
     */
    protected function checkBlacklists(string $ipAddress): array
    {
        $sources = [];
        $reversedIp = $this->reverseIp($ipAddress);

        foreach ($this->blacklistProviders as $provider) {
            $cacheKey = "blacklist_check_{$ipAddress}_{$provider}";

            $isListed = Cache::remember($cacheKey, 3600, function () use ($reversedIp, $provider) {
                return $this->queryDnsbl($reversedIp, $provider);
            });

            if ($isListed) {
                $sources[] = $provider;
            }
        }

        return [
            'is_blacklisted' => !empty($sources),
            'sources' => $sources,
        ];
    }

    /**
     * Query DNSBL.
     */
    protected function queryDnsbl(string $reversedIp, string $provider): bool
    {
        $query = "{$reversedIp}.{$provider}";

        try {
            $result = gethostbyname($query);
            // If result is not the query itself, it means it's listed
            return $result !== $query;
        } catch (\Exception $e) {
            \Log::warning("DNSBL query failed: {$query}", ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Reverse IP address for DNSBL query.
     */
    protected function reverseIp(string $ipAddress): string
    {
        $parts = explode('.', $ipAddress);
        return implode('.', array_reverse($parts));
    }

    /**
     * Update reputation based on email activity.
     */
    public function updateFromActivity(string $ipAddress, string $activity, array $metadata = []): void
    {
        $reputation = IpReputation::getOrCreate($ipAddress);

        switch ($activity) {
            case 'successful_send':
                $reputation->recordSuccess();
                break;
            case 'failed_send':
                $reputation->recordFailure();
                break;
            case 'spam_report':
                $reputation->recordSpamReport();
                break;
            case 'bounce':
                $bounceRate = $metadata['bounce_rate'] ?? 0;
                $reputation->update(['bounce_rate' => $bounceRate]);
                $reputation->updateScore();
                break;
        }
    }

    /**
     * Get reputation status.
     */
    public function getStatus(string $ipAddress): string
    {
        $reputation = IpReputation::where('ip_address', $ipAddress)->first();

        if (!$reputation) {
            return 'unknown';
        }

        if ($reputation->is_blacklisted) {
            return 'blacklisted';
        }

        return match(true) {
            $reputation->reputation_score >= 80 => 'excellent',
            $reputation->reputation_score >= 60 => 'good',
            $reputation->reputation_score >= 40 => 'fair',
            $reputation->reputation_score >= 20 => 'poor',
            default => 'very_poor',
        };
    }

    /**
     * Check if IP can send email.
     */
    public function canSend(string $ipAddress): bool
    {
        $reputation = IpReputation::where('ip_address', $ipAddress)->first();

        if (!$reputation) {
            return true; // Allow new IPs
        }

        return $reputation->canSend();
    }
}
