<?php

namespace App\Services;

use App\Models\Mailbox;
use App\Models\WarmupSchedule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class WarmupService
{
    /**
     * Start warmup for a new mailbox.
     */
    public function startWarmup(Mailbox $mailbox, int $targetDays = 30): WarmupSchedule
    {
        return WarmupSchedule::create([
            'mailbox_id' => $mailbox->id,
            'day' => 1,
            'target_day' => $targetDays,
            'emails_sent_today' => 0,
            'emails_target_today' => WarmupSchedule::getTargetForDay(1),
            'status' => 'active',
            'started_at' => now(),
        ]);
    }

    /**
     * Send warmup emails for active schedules.
     */
    public function processWarmupEmails(): array
    {
        $results = [
            'processed' => 0,
            'sent' => 0,
            'errors' => 0,
        ];

        $activeSchedules = WarmupSchedule::where('status', 'active')
            ->with('mailbox')
            ->get();

        foreach ($activeSchedules as $schedule) {
            $results['processed']++;

            try {
                // Send warmup emails up to the daily target
                while ($schedule->canSendToday()) {
                    $this->sendWarmupEmail($schedule->mailbox);
                    $schedule->incrementSent();
                    $results['sent']++;

                    // Space out sends (don't send all at once)
                    if ($schedule->emails_sent_today % 5 === 0) {
                        break; // Send in batches of 5
                    }
                }
            } catch (\Exception $e) {
                $results['errors']++;
                Log::error("Warmup failed for mailbox {$schedule->mailbox->email}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Send a single warmup email.
     */
    protected function sendWarmupEmail(Mailbox $mailbox): void
    {
        // Internal warmup email destinations (configurable)
        $warmupRecipients = config('mailcore.warmup.recipients', [
            'warmup@mail-tester.com',
            // Add more warmup service emails here
        ]);

        $recipient = $warmupRecipients[array_rand($warmupRecipients)];

        $subject = $this->generateWarmupSubject();
        $body = $this->generateWarmupBody();

        Mail::html($body, function ($message) use ($mailbox, $recipient, $subject) {
            $message->from($mailbox->email)
                ->to($recipient)
                ->subject($subject);
        });
    }

    /**
     * Generate a natural-looking subject for warmup.
     */
    protected function generateWarmupSubject(): string
    {
        $subjects = [
            'Quick update',
            'Following up',
            'Checking in',
            'Your recent activity',
            'Weekly summary',
            'Account notification',
            'Important information',
            'Status update',
        ];

        return $subjects[array_rand($subjects)];
    }

    /**
     * Generate a natural-looking body for warmup.
     */
    protected function generateWarmupBody(): string
    {
        $templates = [
            '<p>Hi there,</p><p>Just wanted to send a quick update about your account.</p><p>Thanks,<br>The Team</p>',
            '<p>Hello,</p><p>This is a friendly reminder about your recent activity.</p><p>Best regards</p>',
            '<p>Hi,</p><p>We wanted to share some information with you.</p><p>Cheers</p>',
        ];

        return $templates[array_rand($templates)];
    }

    /**
     * Get warmup status for a mailbox.
     */
    public function getWarmupStatus(Mailbox $mailbox): ?array
    {
        $schedule = $mailbox->warmupSchedule;

        if (!$schedule) {
            return null;
        }

        return [
            'status' => $schedule->status,
            'day' => $schedule->day,
            'target_day' => $schedule->target_day,
            'progress' => $schedule->getProgressPercentage(),
            'emails_sent_today' => $schedule->emails_sent_today,
            'emails_target_today' => $schedule->emails_target_today,
            'started_at' => $schedule->started_at,
            'completed_at' => $schedule->completed_at,
        ];
    }
}
