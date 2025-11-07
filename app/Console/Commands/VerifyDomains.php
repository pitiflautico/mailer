<?php

namespace App\Console\Commands;

use App\Models\Domain;
use App\Services\DkimService;
use Illuminate\Console\Command;

class VerifyDomains extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailcore:verify-domains {domain? : Specific domain to verify}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify DNS records (SPF, DKIM, DMARC) for all or specific domain';

    /**
     * Execute the console command.
     */
    public function handle(DkimService $dkimService): int
    {
        $domainName = $this->argument('domain');

        if ($domainName) {
            $domains = Domain::where('name', $domainName)->get();

            if ($domains->isEmpty()) {
                $this->error("Domain '{$domainName}' not found.");
                return Command::FAILURE;
            }
        } else {
            $domains = Domain::where('is_active', true)->get();
        }

        if ($domains->isEmpty()) {
            $this->warn('No domains to verify.');
            return Command::SUCCESS;
        }

        $this->info("Verifying {$domains->count()} domain(s)...\n");

        $results = [];

        foreach ($domains as $domain) {
            $this->info("Verifying: {$domain->name}");

            $verification = $dkimService->verifyDnsRecords($domain);

            $results[] = [
                $domain->name,
                $verification['spf']['verified'] ? '✓' : '✗',
                $verification['dkim']['verified'] ? '✓' : '✗',
                $verification['dmarc']['verified'] ? '✓' : '✗',
                $domain->isFullyVerified() ? 'Yes' : 'No',
            ];
        }

        $this->table(
            ['Domain', 'SPF', 'DKIM', 'DMARC', 'Fully Verified'],
            $results
        );

        return Command::SUCCESS;
    }
}
