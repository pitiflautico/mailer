<?php

namespace App\Console\Commands;

use App\Models\Bounce;
use App\Models\SendLog;
use Illuminate\Console\Command;

class CheckBounces extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailcore:check-bounces';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for new bounces and process them';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for bounces...');

        // Find send logs with bounced status but no bounce record
        $bouncedLogs = SendLog::where('status', 'bounced')
            ->doesntHave('bounce')
            ->get();

        if ($bouncedLogs->isEmpty()) {
            $this->info('No new bounces found.');
            return Command::SUCCESS;
        }

        $created = 0;

        foreach ($bouncedLogs as $log) {
            Bounce::create([
                'send_log_id' => $log->id,
                'message_id' => $log->message_id,
                'recipient_email' => $log->to_email,
                'bounce_type' => Bounce::determineBounceTypeFromCode($log->smtp_code ?? 550),
                'bounce_category' => Bounce::determineBounceCategory($log->smtp_response ?? ''),
                'smtp_code' => $log->smtp_code,
                'smtp_response' => $log->smtp_response,
            ]);

            $created++;
        }

        $this->info("Created {$created} bounce record(s).");

        // Auto-suppress emails with multiple hard bounces
        $this->autoSuppressHardBounces();

        return Command::SUCCESS;
    }

    /**
     * Auto-suppress emails with multiple hard bounces.
     */
    protected function autoSuppressHardBounces(): void
    {
        $this->info('Checking for emails to auto-suppress...');

        // Find emails with 3+ hard bounces
        $hardBounces = Bounce::hard()
            ->where('is_suppressed', false)
            ->select('recipient_email', \DB::raw('count(*) as bounce_count'))
            ->groupBy('recipient_email')
            ->having('bounce_count', '>=', 3)
            ->get();

        $suppressed = 0;

        foreach ($hardBounces as $bounce) {
            Bounce::where('recipient_email', $bounce->recipient_email)
                ->update(['is_suppressed' => true]);

            $suppressed++;
        }

        if ($suppressed > 0) {
            $this->warn("Auto-suppressed {$suppressed} email(s) with multiple hard bounces.");
        }
    }
}
