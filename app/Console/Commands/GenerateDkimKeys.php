<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\DkimService;
use Illuminate\Console\Command;

class GenerateDkimKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailcore:generate-dkim {domain : Domain name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate DKIM keys for a domain';

    /**
     * Execute the console command.
     */
    public function handle(DkimService $dkimService): int
    {
        $domainName = $this->argument('domain');

        $domain = Domain::where('name', $domainName)->first();

        if (!$domain) {
            $this->error("Domain '{$domainName}' not found.");
            return Command::FAILURE;
        }

        if ($domain->dkim_public_key && !$this->confirm('Domain already has DKIM keys. Regenerate?')) {
            $this->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        $this->info("Generating DKIM keys for: {$domain->name}");

        if ($dkimService->generateKeys($domain)) {
            $this->info('DKIM keys generated successfully!');

            $this->newLine();
            $this->info('Add this TXT record to your DNS:');
            $this->line("Name: {$domain->dkim_selector}._domainkey");
            $this->line("Value: {$domain->dkim_public_key}");

            return Command::SUCCESS;
        } else {
            $this->error('Failed to generate DKIM keys.');
            return Command::FAILURE;
        }
    }
}
