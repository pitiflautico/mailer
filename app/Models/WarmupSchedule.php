<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarmupSchedule extends Model
{
    protected $fillable = [
        'mailbox_id',
        'day',
        'target_day',
        'emails_sent_today',
        'emails_target_today',
        'status',
        'last_send_at',
        'started_at',
        'completed_at',
        'stats',
    ];

    protected $casts = [
        'stats' => 'array',
        'last_send_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }

    /**
     * Get the target number of emails for a given day.
     */
    public static function getTargetForDay(int $day): int
    {
        // Warmup schedule: gradually increase volume
        return match (true) {
            $day <= 3 => 5,
            $day <= 7 => 10,
            $day <= 14 => 20,
            $day <= 21 => 50,
            $day <= 30 => 100,
            default => 150,
        };
    }

    /**
     * Check if we can send more emails today.
     */
    public function canSendToday(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->emails_sent_today < $this->emails_target_today;
    }

    /**
     * Increment the daily counter.
     */
    public function incrementSent(): void
    {
        $this->increment('emails_sent_today');
        $this->last_send_at = now();
        $this->save();

        // Check if we've hit today's target
        if ($this->emails_sent_today >= $this->emails_target_today) {
            $this->advanceDay();
        }
    }

    /**
     * Advance to the next day of warmup.
     */
    public function advanceDay(): void
    {
        $this->day++;
        $this->emails_sent_today = 0;
        $this->emails_target_today = self::getTargetForDay($this->day);

        // Check if warmup is complete
        if ($this->day > $this->target_day) {
            $this->status = 'completed';
            $this->completed_at = now();
        }

        $this->save();
    }

    /**
     * Get progress percentage.
     */
    public function getProgressPercentage(): float
    {
        return ($this->day / $this->target_day) * 100;
    }
}
