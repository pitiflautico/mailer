<?php

namespace App\Console\Commands;

use App\Models\SendLog;
use App\Models\Bounce;
use App\Models\ActivityLog;
use Illuminate\Console\Command;

class CleanupOldLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailcore:cleanup-old-logs {--days=90 : Number of days to keep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up old logs and records';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoffDate = now()->subDays($days);

        $this->info("Cleaning up records older than {$days} days ({$cutoffDate->toDateString()})...");

        // Clean up old send logs
        $deletedSendLogs = SendLog::where('created_at', '<', $cutoffDate)
            ->whereIn('status', ['delivered', 'bounced', 'failed'])
            ->delete();

        $this->info("Deleted {$deletedSendLogs} old send log(s).");

        // Clean up old bounces
        $deletedBounces = Bounce::where('created_at', '<', $cutoffDate)
            ->where('is_suppressed', false)
            ->delete();

        $this->info("Deleted {$deletedBounces} old bounce record(s).");

        // Clean up old activity logs
        $deletedActivityLogs = ActivityLog::where('created_at', '<', $cutoffDate)->delete();

        $this->info("Deleted {$deletedActivityLogs} old activity log(s).");

        $this->info('Cleanup completed successfully.');

        return Command::SUCCESS;
    }
}
