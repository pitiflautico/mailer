<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpamComplaint extends Model
{
    use HasFactory;

    protected $fillable = [
        'send_log_id',
        'complainant_email',
        'message_id',
        'complaint_type',
        'complaint_details',
        'feedback_type',
        'reported_by',
        'ip_address',
        'user_agent',
        'original_headers',
        'auto_processed',
        'suppressed',
    ];

    protected function casts(): array
    {
        return [
            'auto_processed' => 'boolean',
            'suppressed' => 'boolean',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($complaint) {
            // Auto-suppress on spam complaint
            if ($complaint->complaint_type === 'spam' && !$complaint->suppressed) {
                SuppressionList::suppress(
                    $complaint->complainant_email,
                    'spam_complaint',
                    'spam_complaint',
                    null,
                    [
                        'complaint_id' => $complaint->id,
                        'feedback_type' => $complaint->feedback_type,
                    ]
                );

                $complaint->update(['suppressed' => true]);
            }
        });
    }

    public function sendLog()
    {
        return $this->belongsTo(SendLog::class);
    }

    /**
     * Record spam complaint.
     */
    public static function record(
        string $email,
        string $complaintType = 'spam',
        ?int $sendLogId = null,
        ?string $messageId = null,
        ?array $details = []
    ): self {
        return static::create([
            'complainant_email' => strtolower($email),
            'send_log_id' => $sendLogId,
            'message_id' => $messageId,
            'complaint_type' => $complaintType,
            'complaint_details' => $details['details'] ?? null,
            'feedback_type' => $details['feedback_type'] ?? null,
            'reported_by' => $details['reported_by'] ?? 'user',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'original_headers' => $details['headers'] ?? null,
        ]);
    }

    /**
     * Get complaint rate for email.
     */
    public static function getComplaintRate(string $email): float
    {
        $totalSent = SendLog::where('from_email', $email)->count();
        $complaints = static::where('complainant_email', $email)->count();

        if ($totalSent === 0) {
            return 0;
        }

        return ($complaints / $totalSent) * 100;
    }
}
