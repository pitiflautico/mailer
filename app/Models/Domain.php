<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Domain extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'dkim_selector',
        'dkim_private_key',
        'dkim_public_key',
        'spf_verified',
        'dkim_verified',
        'dmarc_verified',
        'is_active',
        'verified_at',
        'dns_records',
        'verification_results',
        'last_verification_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spf_verified' => 'boolean',
            'dkim_verified' => 'boolean',
            'dmarc_verified' => 'boolean',
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
            'last_verification_at' => 'datetime',
            'dns_records' => 'array',
            'verification_results' => 'array',
        ];
    }

    /**
     * Get all mailboxes for this domain.
     */
    public function mailboxes()
    {
        return $this->hasMany(Mailbox::class);
    }

    /**
     * Get all send logs for this domain.
     */
    public function sendLogs()
    {
        return $this->hasMany(SendLog::class);
    }

    /**
     * Check if domain is fully verified.
     */
    public function isFullyVerified(): bool
    {
        return $this->spf_verified &&
               $this->dkim_verified &&
               $this->dmarc_verified;
    }

    /**
     * Get verification status as percentage.
     */
    public function getVerificationPercentage(): int
    {
        $verified = 0;
        if ($this->spf_verified) $verified++;
        if ($this->dkim_verified) $verified++;
        if ($this->dmarc_verified) $verified++;

        return (int) (($verified / 3) * 100);
    }

    /**
     * Get active mailboxes count.
     */
    public function getActiveMailboxesCountAttribute(): int
    {
        return $this->mailboxes()->where('is_active', true)->count();
    }

    /**
     * Get total sends count.
     */
    public function getTotalSendsAttribute(): int
    {
        return $this->sendLogs()->count();
    }
}
