<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SendLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'domain_id',
        'mailbox_id',
        'message_id',
        'from_email',
        'to_email',
        'subject',
        'body_preview',
        'status',
        'smtp_response',
        'smtp_code',
        'error_message',
        'attempts',
        'sent_at',
        'delivered_at',
        'bounced_at',
        'headers',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'bounced_at' => 'datetime',
            'headers' => 'array',
            'metadata' => 'array',
            'smtp_code' => 'integer',
            'attempts' => 'integer',
        ];
    }

    /**
     * Get the domain that owns the send log.
     */
    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Get the mailbox that owns the send log.
     */
    public function mailbox()
    {
        return $this->belongsTo(Mailbox::class);
    }

    /**
     * Get the bounce record for this send log.
     */
    public function bounce()
    {
        return $this->hasOne(Bounce::class);
    }

    /**
     * Scope for successful sends.
     */
    public function scopeSuccessful($query)
    {
        return $query->whereIn('status', ['sent', 'delivered']);
    }

    /**
     * Scope for failed sends.
     */
    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['bounced', 'failed', 'rejected']);
    }

    /**
     * Scope for today's sends.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope for this week's sends.
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    /**
     * Scope for this month's sends.
     */
    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                     ->whereYear('created_at', now()->year);
    }

    /**
     * Get status badge color.
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            'delivered' => 'success',
            'sent' => 'info',
            'bounced', 'failed' => 'danger',
            'rejected' => 'warning',
            'queued', 'deferred' => 'gray',
            default => 'gray',
        };
    }
}
