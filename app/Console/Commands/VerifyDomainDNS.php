<?php

namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Command;

class VerifyDomainDNS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailcore:verify-dns {domain?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify SPF, DKIM, and DMARC DNS records for domains';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domainName = $this->argument('domain');

        if ($domainName) {
            // Verify specific domain
            $domain = Domain::where('name', $domainName)->first();

            if (!$domain) {
                $this->error("Domain '$domainName' not found.");
                return 1;
            }

            $this->verifyDomain($domain);
        } else {
            // Verify all active domains
            $domains = Domain::where('is_active', true)->get();

            if ($domains->isEmpty()) {
                $this->warn('No active domains found.');
                return 0;
            }

            $this->info("Verifying {$domains->count()} domain(s)...\n");

            foreach ($domains as $domain) {
                $this->verifyDomain($domain);
                $this->newLine();
            }
        }

        return 0;
    }

    /**
     * Verify a single domain
     */
    private function verifyDomain(Domain $domain)
    {
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("Domain: {$domain->name}");
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        // Verify SPF
        $this->line("\n📧 Checking SPF Record...");
        $spfResult = $this->checkSPF($domain->name);
        $domain->spf_verified = $spfResult['verified'];

        if ($spfResult['verified']) {
            $this->info("   ✓ SPF: VERIFIED");
            $this->line("   Record: {$spfResult['record']}");
        } else {
            $this->error("   ✗ SPF: NOT FOUND");
            $this->warn("   Expected: v=spf1 ip4:YOUR_SERVER_IP a:mail.{$domain->name} -all");
        }

        // Verify DKIM
        $this->line("\n🔑 Checking DKIM Record...");
        $dkimResult = $this->checkDKIM($domain->name);
        $domain->dkim_verified = $dkimResult['verified'];

        if ($dkimResult['verified']) {
            $this->info("   ✓ DKIM: VERIFIED");
            $this->line("   Record found: default._domainkey.{$domain->name}");
        } else {
            $this->error("   ✗ DKIM: NOT FOUND");
            $this->warn("   Generate key with: ./scripts/manage-dkim.sh generate {$domain->name}");
        }

        // Verify DMARC
        $this->line("\n🛡️  Checking DMARC Record...");
        $dmarcResult = $this->checkDMARC($domain->name);
        $domain->dmarc_verified = $dmarcResult['verified'];

        if ($dmarcResult['verified']) {
            $this->info("   ✓ DMARC: VERIFIED");
            $this->line("   Record: {$dmarcResult['record']}");
        } else {
            $this->error("   ✗ DMARC: NOT FOUND");
            $this->warn("   Expected: v=DMARC1; p=quarantine; rua=mailto:postmaster@{$domain->name}");
        }

        // Update last verification time
        $domain->last_verified_at = now();
        $domain->save();

        // Summary
        $this->line("\n" . str_repeat("─", 50));
        $allVerified = $spfResult['verified'] && $dkimResult['verified'] && $dmarcResult['verified'];

        if ($allVerified) {
            $this->info("✓ All DNS records verified successfully!");
        } else {
            $this->warn("⚠ Some DNS records are missing or incorrect.");
            $this->line("Run: php artisan mailcore:show-dns {$domain->name}");
        }
    }

    /**
     * Check SPF record
     */
    private function checkSPF($domain)
    {
        try {
            $records = dns_get_record($domain, DNS_TXT);

            foreach ($records as $record) {
                if (isset($record['txt']) && strpos($record['txt'], 'v=spf1') === 0) {
                    return [
                        'verified' => true,
                        'record' => $record['txt']
                    ];
                }
            }
        } catch (\Exception $e) {
            // DNS lookup failed
        }

        return ['verified' => false, 'record' => null];
    }

    /**
     * Check DKIM record
     */
    private function checkDKIM($domain)
    {
        $selector = config('mailcore.dkim_selector', 'default');
        $dkimDomain = "{$selector}._domainkey.{$domain}";

        try {
            $records = dns_get_record($dkimDomain, DNS_TXT);

            foreach ($records as $record) {
                if (isset($record['txt']) && strpos($record['txt'], 'v=DKIM1') !== false) {
                    return [
                        'verified' => true,
                        'record' => $record['txt']
                    ];
                }
            }
        } catch (\Exception $e) {
            // DNS lookup failed
        }

        return ['verified' => false, 'record' => null];
    }

    /**
     * Check DMARC record
     */
    private function checkDMARC($domain)
    {
        $dmarcDomain = "_dmarc.{$domain}";

        try {
            $records = dns_get_record($dmarcDomain, DNS_TXT);

            foreach ($records as $record) {
                if (isset($record['txt']) && strpos($record['txt'], 'v=DMARC1') === 0) {
                    return [
                        'verified' => true,
                        'record' => $record['txt']
                    ];
                }
            }
        } catch (\Exception $e) {
            // DNS lookup failed
        }

        return ['verified' => false, 'record' => null];
    }
}
