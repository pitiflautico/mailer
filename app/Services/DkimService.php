<?php

namespace App\Services;

use App\Models\Domain;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class DkimService
{
    /**
     * Generate DKIM keys for a domain.
     */
    public function generateKeys(Domain $domain): bool
    {
        try {
            $dkimPath = config('mailcore.dkim_path');
            $domainPath = "{$dkimPath}/{$domain->name}";
            $selector = $domain->dkim_selector;

            // Create DKIM base directory if it doesn't exist
            if (!is_dir($dkimPath)) {
                if (!mkdir($dkimPath, 0755, true)) {
                    throw new \Exception("No se pudo crear el directorio DKIM: {$dkimPath}. Verifica los permisos.");
                }
            }

            // Create domain directory if it doesn't exist
            if (!is_dir($domainPath)) {
                if (!mkdir($domainPath, 0755, true)) {
                    throw new \Exception("No se pudo crear el directorio del dominio: {$domainPath}. Verifica los permisos.");
                }
            }

            // Generate DKIM keys using openssl
            $privateKeyPath = "{$domainPath}/{$selector}.private";
            $publicKeyPath = "{$domainPath}/{$selector}.txt";

            // Generate private key
            $privateKeyCommand = "openssl genrsa -out {$privateKeyPath} 2048 2>&1";
            $result = Process::run($privateKeyCommand);

            if (!$result->successful()) {
                throw new \Exception("Error al generar la clave privada DKIM: " . $result->errorOutput());
            }

            if (!file_exists($privateKeyPath)) {
                throw new \Exception("La clave privada no se generó correctamente en: {$privateKeyPath}");
            }

            // Generate public key
            $publicKeyCommand = "openssl rsa -in {$privateKeyPath} -pubout -outform PEM -out {$publicKeyPath} 2>&1";
            $result = Process::run($publicKeyCommand);

            if (!$result->successful()) {
                throw new \Exception("Error al generar la clave pública DKIM: " . $result->errorOutput());
            }

            if (!file_exists($publicKeyPath)) {
                throw new \Exception("La clave pública no se generó correctamente en: {$publicKeyPath}");
            }

            // Read keys
            $privateKey = file_get_contents($privateKeyPath);
            $publicKey = file_get_contents($publicKeyPath);

            if (empty($privateKey) || empty($publicKey)) {
                throw new \Exception("Las claves generadas están vacías");
            }

            // Format public key for DNS
            $publicKeyDns = $this->formatPublicKeyForDns($publicKey);

            // Update domain record
            $domain->update([
                'dkim_private_key' => $privateKey,
                'dkim_public_key' => $publicKeyDns,
            ]);

            // Update OpenDKIM configuration files
            $this->updateOpenDkimConfig($domain, $selector, $privateKeyPath);

            return true;
        } catch (\Exception $e) {
            \Log::error('DKIM key generation failed: ' . $e->getMessage(), [
                'domain' => $domain->name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-throw to let the caller handle it
        }
    }

    /**
     * Format public key for DNS TXT record.
     */
    protected function formatPublicKeyForDns(string $publicKey): string
    {
        // Remove header and footer
        $publicKey = str_replace('-----BEGIN PUBLIC KEY-----', '', $publicKey);
        $publicKey = str_replace('-----END PUBLIC KEY-----', '', $publicKey);
        $publicKey = str_replace(["\n", "\r", " "], '', $publicKey);

        return "v=DKIM1; k=rsa; p={$publicKey}";
    }

    /**
     * Verify DNS records for a domain.
     */
    public function verifyDnsRecords(Domain $domain): array
    {
        $results = [
            'spf' => $this->verifySPF($domain),
            'dkim' => $this->verifyDKIM($domain),
            'dmarc' => $this->verifyDMARC($domain),
        ];

        // Update domain verification status
        $domain->update([
            'spf_verified' => $results['spf']['verified'],
            'dkim_verified' => $results['dkim']['verified'],
            'dmarc_verified' => $results['dmarc']['verified'],
            'verification_results' => $results,
            'last_verification_at' => now(),
            'verified_at' => $results['spf']['verified'] &&
                            $results['dkim']['verified'] &&
                            $results['dmarc']['verified']
                            ? now()
                            : null,
        ]);

        return $results;
    }

    /**
     * Verify SPF record.
     */
    protected function verifySPF(Domain $domain): array
    {
        try {
            $records = dns_get_record($domain->name, DNS_TXT);

            foreach ($records as $record) {
                if (isset($record['txt']) && str_contains($record['txt'], 'v=spf1')) {
                    return [
                        'verified' => true,
                        'record' => $record['txt'],
                        'message' => 'SPF record found and valid',
                    ];
                }
            }

            return [
                'verified' => false,
                'record' => null,
                'message' => 'SPF record not found',
            ];
        } catch (\Exception $e) {
            return [
                'verified' => false,
                'record' => null,
                'message' => 'Error checking SPF: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify DKIM record.
     */
    protected function verifyDKIM(Domain $domain): array
    {
        try {
            $selector = $domain->dkim_selector;
            $dkimDomain = "{$selector}._domainkey.{$domain->name}";
            $records = dns_get_record($dkimDomain, DNS_TXT);

            foreach ($records as $record) {
                if (isset($record['txt']) && str_contains($record['txt'], 'v=DKIM1')) {
                    return [
                        'verified' => true,
                        'record' => $record['txt'],
                        'message' => 'DKIM record found and valid',
                    ];
                }
            }

            return [
                'verified' => false,
                'record' => null,
                'message' => 'DKIM record not found at ' . $dkimDomain,
            ];
        } catch (\Exception $e) {
            return [
                'verified' => false,
                'record' => null,
                'message' => 'Error checking DKIM: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verify DMARC record.
     */
    protected function verifyDMARC(Domain $domain): array
    {
        try {
            $dmarcDomain = "_dmarc.{$domain->name}";
            $records = dns_get_record($dmarcDomain, DNS_TXT);

            foreach ($records as $record) {
                if (isset($record['txt']) && str_contains($record['txt'], 'v=DMARC1')) {
                    return [
                        'verified' => true,
                        'record' => $record['txt'],
                        'message' => 'DMARC record found and valid',
                    ];
                }
            }

            return [
                'verified' => false,
                'record' => null,
                'message' => 'DMARC record not found at ' . $dmarcDomain,
            ];
        } catch (\Exception $e) {
            return [
                'verified' => false,
                'record' => null,
                'message' => 'Error checking DMARC: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get DNS configuration instructions for a domain.
     */
    public function getDnsInstructions(Domain $domain): array
    {
        $mailHostname = config('mailcore.hostname');
        $mailIp = config('mailcore.ip');

        return [
            'mx' => [
                'type' => 'MX',
                'name' => '@',
                'value' => $mailHostname,
                'priority' => 10,
            ],
            'a' => [
                'type' => 'A',
                'name' => 'mail',
                'value' => $mailIp,
            ],
            'spf' => [
                'type' => 'TXT',
                'name' => '@',
                'value' => "v=spf1 a mx ip4:{$mailIp} -all",
            ],
            'dkim' => [
                'type' => 'TXT',
                'name' => "{$domain->dkim_selector}._domainkey",
                'value' => $domain->dkim_public_key,
            ],
            'dmarc' => [
                'type' => 'TXT',
                'name' => '_dmarc',
                'value' => "v=DMARC1; p=none; rua=mailto:dmarc@{$domain->name}",
            ],
        ];
    }

    /**
     * Update OpenDKIM configuration files automatically
     */
    protected function updateOpenDkimConfig(Domain $domain, string $selector, string $privateKeyPath): void
    {
        try {
            $domainName = $domain->name;

            // Key identifier for OpenDKIM
            $keyId = "{$selector}._domainkey.{$domainName}";

            // 1. Update KeyTable
            $keyTablePath = '/etc/opendkim/KeyTable';
            $keyTableEntry = "{$keyId} {$domainName}:{$selector}:{$privateKeyPath}\n";

            if (is_writable($keyTablePath) || !file_exists($keyTablePath)) {
                // Read existing content
                $existingContent = file_exists($keyTablePath) ? file_get_contents($keyTablePath) : '';

                // Remove old entry for this domain if exists
                $lines = explode("\n", $existingContent);
                $lines = array_filter($lines, function($line) use ($domainName, $selector) {
                    return !str_contains($line, "{$selector}._domainkey.{$domainName}");
                });

                // Add new entry
                $lines[] = rtrim($keyTableEntry);
                file_put_contents($keyTablePath, implode("\n", $lines) . "\n");

                \Log::info("Updated OpenDKIM KeyTable for {$domainName}");
            }

            // 2. Update SigningTable
            $signingTablePath = '/etc/opendkim/SigningTable';
            $signingTableEntry = "*@{$domainName} {$keyId}\n";

            if (is_writable($signingTablePath) || !file_exists($signingTablePath)) {
                // Read existing content
                $existingContent = file_exists($signingTablePath) ? file_get_contents($signingTablePath) : '';

                // Remove old entry for this domain if exists
                $lines = explode("\n", $existingContent);
                $lines = array_filter($lines, function($line) use ($domainName) {
                    return !str_contains($line, "@{$domainName}");
                });

                // Add new entry
                $lines[] = rtrim($signingTableEntry);
                file_put_contents($signingTablePath, implode("\n", $lines) . "\n");

                \Log::info("Updated OpenDKIM SigningTable for {$domainName}");
            }

            // 3. Update TrustedHosts
            $trustedHostsPath = '/etc/opendkim/TrustedHosts';
            $trustedHostEntry = "{$domainName}\n";

            if (is_writable($trustedHostsPath) || !file_exists($trustedHostsPath)) {
                // Read existing content
                $existingContent = file_exists($trustedHostsPath) ? file_get_contents($trustedHostsPath) : "127.0.0.1\nlocalhost\n";

                // Add domain if not exists
                if (!str_contains($existingContent, $domainName)) {
                    file_put_contents($trustedHostsPath, $existingContent . $trustedHostEntry);
                    \Log::info("Updated OpenDKIM TrustedHosts for {$domainName}");
                }
            }

            // 4. Set proper permissions
            if (file_exists($privateKeyPath)) {
                chmod($privateKeyPath, 0600);
                // Try to set owner to opendkim user
                @chown($privateKeyPath, 'opendkim');
                @chgrp($privateKeyPath, 'opendkim');
            }

            // 5. Reload OpenDKIM service
            $reloadResult = Process::run('systemctl reload opendkim 2>&1 || service opendkim reload 2>&1');

            if ($reloadResult->successful()) {
                \Log::info("OpenDKIM reloaded successfully for {$domainName}");
            } else {
                \Log::warning("Could not reload OpenDKIM automatically. Run: sudo systemctl reload opendkim");
            }

        } catch (\Exception $e) {
            // Don't fail the whole operation if OpenDKIM config update fails
            \Log::warning("Could not update OpenDKIM config automatically: " . $e->getMessage());
            \Log::warning("You may need to run: sudo ./scripts/manage-dkim.sh configure {$domain->name}");
        }
    }
}
