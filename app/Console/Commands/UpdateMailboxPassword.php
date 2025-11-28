<?php

namespace App\Console\Commands;

use App\Models\Mailbox;
use Illuminate\Console\Command;

class UpdateMailboxPassword extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mailcore:update-password {email} {password}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update a mailbox password';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $mailbox = Mailbox::where('email', $email)->first();

        if (!$mailbox) {
            $this->error("Mailbox not found: {$email}");
            return Command::FAILURE;
        }

        $mailbox->password = bcrypt($password);
        $mailbox->save();

        $this->info("Password updated successfully for: {$email}");

        return Command::SUCCESS;
    }
}
