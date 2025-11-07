<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ConsentRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'domain_id',
        'consent_type',
        'granted',
        'consent_method',
        'ip_address',
        'user_agent',
        'consent_text',
        'granted_at',
        'revoked_at',
        'expires_at',
        'verification_token',
        'verified_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'granted' => 'boolean',
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Check if consent is valid.
     */
    public function isValid(): bool
    {
        if (!$this->granted) {
            return false;
        }

        if ($this->revoked_at) {
            return false;
        }

        if ($this->consent_method === 'double_opt_in' && !$this->verified_at) {
            return false;
        }

        if ($this->expires_at && now()->greaterThan($this->expires_at)) {
            return false;
        }

        return true;
    }

    /**
     * Check if email has valid consent.
     */
    public static function hasValidConsent(
        string $email,
        string $consentType,
        ?int $domainId = null
    ): bool {
        $query = static::where('email', strtolower($email))
            ->where('consent_type', $consentType)
            ->where('granted', true);

        if ($domainId) {
            $query->where('domain_id', $domainId);
        }

        $consent = $query->first();

        return $consent && $consent->isValid();
    }

    /**
     * Grant consent.
     */
    public static function grant(
        string $email,
        string $consentType,
        string $consentMethod,
        ?int $domainId = null,
        ?string $consentText = null,
        ?array $metadata = null
    ): self {
        $data = [
            'email' => strtolower($email),
            'domain_id' => $domainId,
            'consent_type' => $consentType,
            'granted' => true,
            'consent_method' => $consentMethod,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'consent_text' => $consentText,
            'granted_at' => now(),
            'metadata' => $metadata,
        ];

        // If double opt-in, generate verification token
        if ($consentMethod === 'double_opt_in') {
            $data['verification_token'] = Str::random(64);
            $data['granted'] = false; // Not granted until verified
        }

        return static::create($data);
    }

    /**
     * Revoke consent.
     */
    public function revoke(?string $reason = null): bool
    {
        return $this->update([
            'granted' => false,
            'revoked_at' => now(),
            'metadata' => array_merge($this->metadata ?? [], [
                'revoke_reason' => $reason,
                'revoked_ip' => request()->ip(),
            ]),
        ]);
    }

    /**
     * Verify double opt-in.
     */
    public function verify(): bool
    {
        if ($this->consent_method !== 'double_opt_in') {
            return false;
        }

        return $this->update([
            'granted' => true,
            'verified_at' => now(),
            'verification_token' => null,
        ]);
    }

    /**
     * Scope for valid consents.
     */
    public function scopeValid($query)
    {
        return $query->where('granted', true)
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function ($q) {
                $q->where('consent_method', '!=', 'double_opt_in')
                    ->orWhereNotNull('verified_at');
            });
    }
}
