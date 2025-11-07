<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SuppressionList extends Model
{
    use HasFactory;

    protected $table = 'suppression_list';

    protected $fillable = [
        'email',
        'reason',
        'notes',
        'source',
        'suppressed_at',
        'expires_at',
        'domain_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'suppressed_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Check if suppression is still active.
     */
    public function isActive(): bool
    {
        if ($this->expires_at) {
            return now()->lessThan($this->expires_at);
        }
        return true;
    }

    /**
     * Check if email is suppressed.
     */
    public static function isSuppressed(string $email): bool
    {
        return static::where('email', strtolower($email))
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Add email to suppression list.
     */
    public static function suppress(
        string $email,
        string $reason,
        ?string $source = null,
        ?int $domainId = null,
        ?array $metadata = null
    ): self {
        return static::create([
            'email' => strtolower($email),
            'reason' => $reason,
            'source' => $source,
            'domain_id' => $domainId,
            'suppressed_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Scope for active suppressions.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope for specific reason.
     */
    public function scopeReason($query, string $reason)
    {
        return $query->where('reason', $reason);
    }
}
