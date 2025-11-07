<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplianceLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action_type',
        'entity_type',
        'entity_id',
        'email',
        'description',
        'compliance_standard',
        'compliant',
        'non_compliance_reason',
        'data_snapshot',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'compliant' => 'boolean',
            'data_snapshot' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log compliance action.
     */
    public static function logAction(
        string $actionType,
        string $description,
        ?string $email = null,
        ?string $complianceStandard = null,
        bool $compliant = true,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $dataSnapshot = null
    ): self {
        return static::create([
            'user_id' => auth()->id(),
            'action_type' => $actionType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'email' => $email ? strtolower($email) : null,
            'description' => $description,
            'compliance_standard' => $complianceStandard,
            'compliant' => $compliant,
            'data_snapshot' => $dataSnapshot,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Log GDPR action.
     */
    public static function logGdpr(
        string $actionType,
        string $email,
        string $description,
        ?array $dataSnapshot = null
    ): self {
        return static::logAction(
            $actionType,
            $description,
            $email,
            'gdpr',
            true,
            null,
            null,
            $dataSnapshot
        );
    }

    /**
     * Scope for specific standard.
     */
    public function scopeStandard($query, string $standard)
    {
        return $query->where('compliance_standard', $standard);
    }

    /**
     * Scope for non-compliant.
     */
    public function scopeNonCompliant($query)
    {
        return $query->where('compliant', false);
    }
}
