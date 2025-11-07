<?php

namespace Database\Seeders;

use App\Models\Domain;
use App\Models\Mailbox;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MailboxSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $domain = Domain::where('name', 'ejemplo.com')->first();

        if (!$domain) {
            $this->command->warn('⚠ Dominio "ejemplo.com" no encontrado');
            return;
        }

        // Buzón principal
        Mailbox::create([
            'domain_id' => $domain->id,
            'local_part' => 'noreply',
            'email' => 'noreply@ejemplo.com',
            'password' => Hash::make('password123'),
            'quota_mb' => 1024,
            'used_mb' => 150,
            'is_active' => true,
            'can_send' => true,
            'can_receive' => true,
            'daily_send_limit' => 1000,
            'daily_send_count' => 45,
            'daily_send_reset_at' => today(),
            'notes' => 'Buzón principal para notificaciones automáticas',
        ]);

        // Buzón secundario
        Mailbox::create([
            'domain_id' => $domain->id,
            'local_part' => 'info',
            'email' => 'info@ejemplo.com',
            'password' => Hash::make('password123'),
            'quota_mb' => 2048,
            'used_mb' => 500,
            'is_active' => true,
            'can_send' => true,
            'can_receive' => true,
            'daily_send_limit' => 500,
            'daily_send_count' => 12,
            'daily_send_reset_at' => today(),
            'notes' => 'Buzón de contacto',
        ]);

        $this->command->info('✓ Buzones creados (password: password123)');
    }
}
