<?php

namespace App\Console\Commands;

use App\Services\WarmupService;
use Illuminate\Console\Command;

class ProcessWarmup extends Command
{
    protected $signature = 'mailcore:process-warmup';
    protected $description = 'Process automated email warmup for active mailboxes';

    public function handle(WarmupService $warmupService): int
    {
        $this->info('Processing warmup emails...');

        $results = $warmupService->processWarmupEmails();

        $this->info("Processed {$results['processed']} schedules");
        $this->info("Sent {$results['sent']} warmup emails");

        if ($results['errors'] > 0) {
            $this->warn("Errors: {$results['errors']}");
        }

        return Command::SUCCESS;
    }
}
