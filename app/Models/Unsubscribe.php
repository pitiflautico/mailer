<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Unsubscribe extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'domain_id',
        'mailbox_id',
        'list_type',
        'unsubscribe_token',
        'ip_address',
        'user_agent',
        'reason',
        'unsubscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'unsubscribed_at' => 'datetime',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($unsubscribe) {
            if (!$unsubscribe->unsubscribe_token) {
                $unsubscribe->unsubscribe_token = Str::random(64);
            }
            if (!$unsubscribe->unsubscribed_at) {
                $unsubscribe->unsubscribed_at = now();
            }
        });
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    public function mailbox()
    {
        return $this->belongsTo(Mailbox::class);
    }

    /**
     * Check if email is unsubscribed.
     */
    public static function isUnsubscribed(string $email, ?int $domainId = null, string $listType = 'all'): bool
    {
        $query = static::where('email', strtolower($email));

        if ($domainId) {
            $query->where('domain_id', $domainId);
        }

        if ($listType !== 'all') {
            $query->where(function ($q) use ($listType) {
                $q->where('list_type', $listType)
                    ->orWhere('list_type', 'all');
            });
        }

        return $query->exists();
    }

    /**
     * Process unsubscribe request.
     */
    public static function process(
        string $email,
        ?int $domainId = null,
        ?int $mailboxId = null,
        string $listType = 'all',
        ?string $reason = null
    ): self {
        // Add to suppression list
        SuppressionList::suppress(
            $email,
            'unsubscribe',
            'unsubscribe_link',
            $domainId,
            ['list_type' => $listType]
        );

        return static::create([
            'email' => strtolower($email),
            'domain_id' => $domainId,
            'mailbox_id' => $mailboxId,
            'list_type' => $listType,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'reason' => $reason,
        ]);
    }

    /**
     * Generate unsubscribe URL.
     */
    public static function generateUrl(string $email, ?int $domainId = null): string
    {
        $unsubscribe = static::firstOrCreate(
            [
                'email' => strtolower($email),
                'domain_id' => $domainId,
                'list_type' => 'all',
            ],
            [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]
        );

        return route('unsubscribe.confirm', ['token' => $unsubscribe->unsubscribe_token]);
    }
}
