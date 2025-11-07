<?php

namespace App\Console\Commands;

use App\Models\Domain;
use Illuminate\Console\Command;

class ShowDNSRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailcore:show-dns {domain}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show DNS records that need to be configured for a domain';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $domainName = $this->argument('domain');
        $domain = Domain::where('name', $domainName)->first();

        if (!$domain) {
            $this->error("Domain '$domainName' not found.");
            $this->line("\nAvailable domains:");
            Domain::all()->each(fn($d) => $this->line("  - {$d->name}"));
            return 1;
        }

        $this->showDNSRecords($domain);
        return 0;
    }

    /**
     * Show DNS records for domain
     */
    private function showDNSRecords(Domain $domain)
    {
        $serverIp = $this->getServerIP();
        $mailHostname = config('mailcore.mail_hostname', "mail.{$domain->name}");

        $this->info("╔════════════════════════════════════════════════════════════════════╗");
        $this->info("║         DNS RECORDS FOR: {$domain->name}                    ");
        $this->info("╚════════════════════════════════════════════════════════════════════╝");

        $this->newLine();

        // A Record
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📍 A RECORD (Mail Server)");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("Type:  A");
        $this->line("Name:  mail.{$domain->name}");
        $this->line("Value: {$serverIp}");
        $this->line("TTL:   3600");

        $this->newLine();

        // MX Record
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📬 MX RECORD (Mail Exchange)");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("Type:     MX");
        $this->line("Name:     {$domain->name}");
        $this->line("Value:    mail.{$domain->name}");
        $this->line("Priority: 10");
        $this->line("TTL:      3600");

        $this->newLine();

        // SPF Record
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("📧 SPF RECORD (Sender Policy Framework)");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("Type:  TXT");
        $this->line("Name:  {$domain->name}");
        $this->line("Value: v=spf1 ip4:{$serverIp} a:mail.{$domain->name} -all");
        $this->line("TTL:   3600");

        $this->newLine();

        // DKIM Record
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🔑 DKIM RECORD (Domain Keys Identified Mail)");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");

        $dkimKey = $this->getDKIMPublicKey($domain->name);

        if ($dkimKey) {
            $this->line("Type:  TXT");
            $this->line("Name:  default._domainkey.{$domain->name}");
            $this->line("Value: {$dkimKey}");
            $this->line("TTL:   3600");
        } else {
            $this->warn("⚠ DKIM key not generated yet!");
            $this->line("\nGenerate DKIM key first:");
            $this->info("  sudo ./scripts/manage-dkim.sh generate {$domain->name}");
            $this->line("\nOr from MailCore directory:");
            $this->info("  php artisan mailcore:generate-dkim {$domain->name}");
        }

        $this->newLine();

        // DMARC Record
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🛡️  DMARC RECORD (Domain-based Message Authentication)");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("Type:  TXT");
        $this->line("Name:  _dmarc.{$domain->name}");
        $this->line("Value: v=DMARC1; p=quarantine; rua=mailto:postmaster@{$domain->name}; ruf=mailto:postmaster@{$domain->name}; fo=1");
        $this->line("TTL:   3600");

        $this->newLine();

        // PTR Record (Reverse DNS)
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info("🔄 PTR RECORD (Reverse DNS) - Configure with your hosting provider");
        $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->line("IP:    {$serverIp}");
        $this->line("Value: mail.{$domain->name}");
        $this->warn("\n⚠ PTR records must be configured by your hosting provider!");

        $this->newLine();
        $this->line("════════════════════════════════════════════════════════════════════");

        // Instructions
        $this->newLine();
        $this->info("📝 NEXT STEPS:");
        $this->line("1. Add all the above DNS records to your domain provider");
        $this->line("2. Wait 5-30 minutes for DNS propagation");
        $this->line("3. Verify records with: php artisan mailcore:verify-dns {$domain->name}");
        $this->line("4. Check verification in admin panel: /admin/domains");

        $this->newLine();
        $this->info("🔧 HELPFUL COMMANDS:");
        $this->line("• Test DNS: dig TXT {$domain->name} +short");
        $this->line("• Test DKIM: dig TXT default._domainkey.{$domain->name} +short");
        $this->line("• Test DMARC: dig TXT _dmarc.{$domain->name} +short");
        $this->line("• Test PTR: dig -x {$serverIp} +short");

        $this->newLine();
    }

    /**
     * Get server IP
     */
    private function getServerIP()
    {
        // Try to get from config
        $ip = config('mailcore.server_ip');

        if (!$ip) {
            // Try to detect
            $ip = gethostbyname(gethostname());

            // If local, try to get public IP
            if (strpos($ip, '127.') === 0 || strpos($ip, '192.168.') === 0) {
                try {
                    $ip = trim(file_get_contents('https://api.ipify.org'));
                } catch (\Exception $e) {
                    $ip = 'YOUR_SERVER_IP';
                }
            }
        }

        return $ip;
    }

    /**
     * Get DKIM public key
     */
    private function getDKIMPublicKey($domain)
    {
        $selector = config('mailcore.dkim_selector', 'default');
        $dkimPath = config('mailcore.dkim_path', '/etc/opendkim/keys');
        $dnsFile = "{$dkimPath}/{$domain}/{$selector}.dns";

        if (file_exists($dnsFile)) {
            $content = file_get_contents($dnsFile);

            // Extract the public key value
            preg_match('/\((.*?)\)/s', $content, $matches);

            if (isset($matches[1])) {
                // Clean up the key
                $key = str_replace(['"', ' ', "\t", "\n", "\r"], '', $matches[1]);
                return "v=DKIM1; k=rsa; p={$key}";
            }
        }

        return null;
    }
}
