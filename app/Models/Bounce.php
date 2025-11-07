<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bounce extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'send_log_id',
        'message_id',
        'recipient_email',
        'bounce_type',
        'bounce_category',
        'smtp_code',
        'smtp_response',
        'diagnostic_code',
        'raw_message',
        'is_suppressed',
        'suppressed_until',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_suppressed' => 'boolean',
            'suppressed_until' => 'datetime',
            'smtp_code' => 'integer',
        ];
    }

    /**
     * Get the send log that owns the bounce.
     */
    public function sendLog()
    {
        return $this->belongsTo(SendLog::class);
    }

    /**
     * Scope for hard bounces.
     */
    public function scopeHard($query)
    {
        return $query->whereIn('bounce_type', ['hard', 'permanent']);
    }

    /**
     * Scope for soft bounces.
     */
    public function scopeSoft($query)
    {
        return $query->whereIn('bounce_type', ['soft', 'transient']);
    }

    /**
     * Scope for suppressed emails.
     */
    public function scopeSuppressed($query)
    {
        return $query->where('is_suppressed', true)
                     ->where(function($q) {
                         $q->whereNull('suppressed_until')
                           ->orWhere('suppressed_until', '>', now());
                     });
    }

    /**
     * Determine bounce type from SMTP code.
     */
    public static function determineBounceTypeFromCode(int $code): string
    {
        return match(true) {
            $code >= 500 && $code < 600 => 'hard',
            $code >= 400 && $code < 500 => 'soft',
            default => 'unknown',
        };
    }

    /**
     * Determine bounce category from SMTP response.
     */
    public static function determineBounceCategory(string $response): string
    {
        $response = strtolower($response);

        if (str_contains($response, 'user unknown') ||
            str_contains($response, 'no such user') ||
            str_contains($response, 'invalid recipient')) {
            return 'invalid_address';
        }

        if (str_contains($response, 'mailbox full') ||
            str_contains($response, 'quota exceeded')) {
            return 'mailbox_full';
        }

        if (str_contains($response, 'spam') ||
            str_contains($response, 'blocked') ||
            str_contains($response, 'blacklist')) {
            return 'spam_related';
        }

        if (str_contains($response, 'dns') ||
            str_contains($response, 'domain not found')) {
            return 'dns_error';
        }

        if (str_contains($response, 'connection') ||
            str_contains($response, 'timeout')) {
            return 'connection_error';
        }

        if (str_contains($response, 'policy') ||
            str_contains($response, 'relay denied')) {
            return 'policy_related';
        }

        if (str_contains($response, 'content') ||
            str_contains($response, 'message rejected')) {
            return 'content_rejected';
        }

        return 'other';
    }

    /**
     * Get type badge color.
     */
    public function getTypeColorAttribute(): string
    {
        return match($this->bounce_type) {
            'hard', 'permanent' => 'danger',
            'soft', 'transient' => 'warning',
            default => 'gray',
        };
    }
}
