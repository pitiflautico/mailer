<?php

namespace App\Console\Commands;

use App\Models\Mailbox;
use App\Services\WarmupService;
use Illuminate\Console\Command;

class ManageWarmup extends Command
{
    protected $signature = 'mailcore:warmup {action} {email?} {--days=30}';
    protected $description = 'Manage email warmup schedules (start, stop, status)';

    public function handle(WarmupService $warmupService): int
    {
        $action = $this->argument('action');
        $email = $this->argument('email');

        match ($action) {
            'start' => $this->startWarmup($warmupService, $email),
            'stop' => $this->stopWarmup($email),
            'status' => $this->showStatus($warmupService, $email),
            'list' => $this->listActive(),
            default => $this->error("Unknown action: {$action}. Use: start, stop, status, list"),
        };

        return Command::SUCCESS;
    }

    protected function startWarmup(WarmupService $warmupService, ?string $email): void
    {
        if (!$email) {
            $this->error('Email is required for start action');
            return;
        }

        $mailbox = Mailbox::where('email', $email)->first();

        if (!$mailbox) {
            $this->error("Mailbox not found: {$email}");
            return;
        }

        if ($mailbox->warmupSchedule()->where('status', 'active')->exists()) {
            $this->warn("Warmup already active for {$email}");
            return;
        }

        $days = (int) $this->option('days');
        $schedule = $warmupService->startWarmup($mailbox, $days);

        $this->info("✓ Warmup started for {$email}");
        $this->info("  Duration: {$days} days");
        $this->info("  Target today: {$schedule->emails_target_today} emails");
    }

    protected function stopWarmup(?string $email): void
    {
        if (!$email) {
            $this->error('Email is required for stop action');
            return;
        }

        $mailbox = Mailbox::where('email', $email)->first();

        if (!$mailbox || !$mailbox->warmupSchedule) {
            $this->error("No active warmup found for {$email}");
            return;
        }

        $mailbox->warmupSchedule->update(['status' => 'paused']);
        $this->info("✓ Warmup paused for {$email}");
    }

    protected function showStatus(WarmupService $warmupService, ?string $email): void
    {
        if (!$email) {
            $this->error('Email is required for status action');
            return;
        }

        $mailbox = Mailbox::where('email', $email)->first();

        if (!$mailbox) {
            $this->error("Mailbox not found: {$email}");
            return;
        }

        $status = $warmupService->getWarmupStatus($mailbox);

        if (!$status) {
            $this->warn("No warmup schedule for {$email}");
            return;
        }

        $this->info("Warmup Status for {$email}:");
        $this->table(
            ['Property', 'Value'],
            [
                ['Status', $status['status']],
                ['Day', "{$status['day']} / {$status['target_day']}"],
                ['Progress', round($status['progress'], 1) . '%'],
                ['Today', "{$status['emails_sent_today']} / {$status['emails_target_today']}"],
                ['Started', $status['started_at']?->format('Y-m-d H:i:s') ?? 'N/A'],
                ['Completed', $status['completed_at']?->format('Y-m-d H:i:s') ?? 'N/A'],
            ]
        );
    }

    protected function listActive(): void
    {
        $schedules = \App\Models\WarmupSchedule::where('status', 'active')
            ->with('mailbox')
            ->get();

        if ($schedules->isEmpty()) {
            $this->warn('No active warmup schedules');
            return;
        }

        $this->table(
            ['Email', 'Day', 'Target', 'Sent Today', 'Progress'],
            $schedules->map(fn($s) => [
                $s->mailbox->email,
                "{$s->day}/{$s->target_day}",
                $s->emails_target_today,
                $s->emails_sent_today,
                round($s->getProgressPercentage(), 1) . '%',
            ])
        );
    }
}
