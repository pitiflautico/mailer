<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IpReputation extends Model
{
    use HasFactory;

    protected $table = 'ip_reputation';

    protected $fillable = [
        'ip_address',
        'reputation_score',
        'spam_reports',
        'successful_sends',
        'failed_sends',
        'bounce_rate',
        'is_blacklisted',
        'blacklist_sources',
        'last_checked_at',
        'blacklisted_at',
    ];

    protected function casts(): array
    {
        return [
            'is_blacklisted' => 'boolean',
            'blacklist_sources' => 'array',
            'last_checked_at' => 'datetime',
            'blacklisted_at' => 'datetime',
            'reputation_score' => 'integer',
            'spam_reports' => 'integer',
            'successful_sends' => 'integer',
            'failed_sends' => 'integer',
            'bounce_rate' => 'integer',
        ];
    }

    /**
     * Get or create IP reputation record.
     */
    public static function getOrCreate(string $ipAddress): self
    {
        return static::firstOrCreate(
            ['ip_address' => $ipAddress],
            [
                'reputation_score' => 100,
                'spam_reports' => 0,
                'successful_sends' => 0,
                'failed_sends' => 0,
                'bounce_rate' => 0,
            ]
        );
    }

    /**
     * Update reputation score.
     */
    public function updateScore(): void
    {
        $totalSends = $this->successful_sends + $this->failed_sends;

        if ($totalSends === 0) {
            return;
        }

        $successRate = ($this->successful_sends / $totalSends) * 100;
        $spamRate = $totalSends > 0 ? ($this->spam_reports / $totalSends) * 100 : 0;

        // Calculate score (100 = perfect, 0 = worst)
        $score = $successRate - ($spamRate * 10) - $this->bounce_rate;
        $score = max(0, min(100, $score));

        $this->update(['reputation_score' => (int) $score]);
    }

    /**
     * Record successful send.
     */
    public function recordSuccess(): void
    {
        $this->increment('successful_sends');
        $this->updateScore();
    }

    /**
     * Record failed send.
     */
    public function recordFailure(): void
    {
        $this->increment('failed_sends');
        $this->updateScore();
    }

    /**
     * Record spam report.
     */
    public function recordSpamReport(): void
    {
        $this->increment('spam_reports');
        $this->updateScore();
    }

    /**
     * Check if IP can send.
     */
    public function canSend(): bool
    {
        if ($this->is_blacklisted) {
            return false;
        }

        if ($this->reputation_score < 30) {
            return false;
        }

        return true;
    }

    /**
     * Scope for blacklisted IPs.
     */
    public function scopeBlacklisted($query)
    {
        return $query->where('is_blacklisted', true);
    }

    /**
     * Scope for good reputation.
     */
    public function scopeGoodReputation($query, int $minScore = 70)
    {
        return $query->where('reputation_score', '>=', $minScore)
            ->where('is_blacklisted', false);
    }
}
