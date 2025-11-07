<?php

namespace Database\Seeders;

use App\Models\Domain;
use Illuminate\Database\Seeder;

class DomainSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Dominio verificado
        Domain::create([
            'name' => 'ejemplo.com',
            'dkim_selector' => 'default',
            'dkim_private_key' => '-----BEGIN RSA PRIVATE KEY-----\nMIIEpAIBAAKCAQEA...(simulado)\n-----END RSA PRIVATE KEY-----',
            'dkim_public_key' => 'v=DKIM1; k=rsa; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...',
            'spf_verified' => true,
            'dkim_verified' => true,
            'dmarc_verified' => true,
            'is_active' => true,
            'verified_at' => now(),
            'last_verification_at' => now(),
            'dns_records' => [
                'spf' => 'v=spf1 a mx ip4:192.168.1.1 -all',
                'dkim' => 'v=DKIM1; k=rsa; p=MIIBIjANBgkq...',
                'dmarc' => 'v=DMARC1; p=none; rua=mailto:dmarc@ejemplo.com',
            ],
            'verification_results' => [
                'spf' => ['verified' => true, 'message' => 'SPF record found'],
                'dkim' => ['verified' => true, 'message' => 'DKIM record found'],
                'dmarc' => ['verified' => true, 'message' => 'DMARC record found'],
            ],
        ]);

        // Dominio sin verificar
        Domain::create([
            'name' => 'test.com',
            'dkim_selector' => 'default',
            'is_active' => true,
            'spf_verified' => false,
            'dkim_verified' => false,
            'dmarc_verified' => false,
        ]);

        $this->command->info('✓ Dominios creados');
    }
}
