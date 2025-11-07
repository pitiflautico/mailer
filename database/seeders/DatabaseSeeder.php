<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DomainSeeder::class,
            MailboxSeeder::class,
            SendLogSeeder::class,
        ]);

        $this->command->info('✅ Database seeded successfully!');
        $this->command->info('📧 Dominios creados: 2');
        $this->command->info('📬 Buzones creados: 2');
        $this->command->info('📨 Logs de envío: 55');
    }
}
