<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;

class Mailbox extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_id',
        'local_part',
        'email',
        'password',
        'quota_mb',
        'used_mb',
        'is_active',
        'can_send',
        'can_receive',
        'daily_send_limit',
        'daily_send_count',
        'daily_send_reset_at',
        'notes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'can_send' => 'boolean',
            'can_receive' => 'boolean',
            'daily_send_reset_at' => 'date',
            'quota_mb' => 'integer',
            'used_mb' => 'integer',
            'daily_send_limit' => 'integer',
            'daily_send_count' => 'integer',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($mailbox) {
            if ($mailbox->password) {
                $mailbox->password = Hash::make($mailbox->password);
            }
        });
    }

    /**
     * Get the domain that owns the mailbox.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get all send logs for this mailbox.
     */
    public function sendLogs()
    {
        return $this->hasMany(SendLog::class);
    }

    /**
     * Get the active warmup schedule for this mailbox.
     */
    public function warmupSchedule()
    {
        return $this->hasOne(WarmupSchedule::class)->where('status', 'active');
    }

    /**
     * Get all warmup schedules for this mailbox.
     */
    public function warmupSchedules()
    {
        return $this->hasMany(WarmupSchedule::class);
    }

    /**
     * Check if mailbox can send emails.
     */
    public function canSendEmail(): bool
    {
        if (!$this->is_active || !$this->can_send) {
            return false;
        }

        // Reset daily counter if needed
        if ($this->daily_send_reset_at && $this->daily_send_reset_at->isToday() === false) {
            $this->daily_send_count = 0;
            $this->daily_send_reset_at = today();
            $this->save();
        }

        return $this->daily_send_count < $this->daily_send_limit;
    }

    /**
     * Increment daily send count.
     */
    public function incrementSendCount(): void
    {
        $this->increment('daily_send_count');

        if (!$this->daily_send_reset_at) {
            $this->daily_send_reset_at = today();
            $this->save();
        }
    }

    /**
     * Get quota usage percentage.
     */
    public function getQuotaUsagePercentage(): int
    {
        if ($this->quota_mb === 0) {
            return 0;
        }

        return (int) (($this->used_mb / $this->quota_mb) * 100);
    }

    /**
     * Check if quota is exceeded.
     */
    public function isQuotaExceeded(): bool
    {
        return $this->used_mb >= $this->quota_mb;
    }
}
