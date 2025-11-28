<?php

namespace App\Providers;

use App\Models\Mailbox;
use App\Observers\MailboxObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super_admin') ? true : null;
        });

        // Register Mailbox Observer to auto-create mail directories
        Mailbox::observe(MailboxObserver::class);
    }
}
