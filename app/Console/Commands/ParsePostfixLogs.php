<?php

namespace App\Console\Commands;

use App\Services\PostfixLogParser;
use Illuminate\Console\Command;

class ParsePostfixLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailcore:parse-logs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parse Postfix logs and update send status';

    /**
     * Execute the console command.
     */
    public function handle(PostfixLogParser $parser): int
    {
        $this->info('Parsing Postfix logs...');

        $stats = $parser->parse();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Lines Processed', $stats['processed']],
                ['Sent', $stats['sent']],
                ['Bounced', $stats['bounced']],
                ['Deferred', $stats['deferred']],
            ]
        );

        $this->info('Log parsing completed successfully.');

        return Command::SUCCESS;
    }
}
