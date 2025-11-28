<?php

namespace App\Console\Commands;

use App\Models\SendLog;
use Illuminate\Console\Command;

class ShowRecentSends extends Command
{
    protected $signature = 'mailcore:recent-sends {--limit=10}';
    protected $description = 'Show recent email sends';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $logs = SendLog::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get(['id', 'recipient', 'subject', 'status', 'created_at']);

        if ($logs->isEmpty()) {
            $this->info('No sends found.');
            return Command::SUCCESS;
        }

        $this->table(
            ['ID', 'Recipient', 'Subject', 'Status', 'Created At'],
            $logs->map(fn($log) => [
                $log->id,
                $log->recipient,
                $log->subject ?? '(no subject)',
                $log->status,
                $log->created_at->format('Y-m-d H:i:s'),
            ])
        );

        return Command::SUCCESS;
    }
}
