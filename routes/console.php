<?php

use App\Models\WarmupSchedule;
use Illuminate\Support\Facades\Schedule;

Schedule::command('mailcore:parse-logs')->everyFiveMinutes();
Schedule::command('mailcore:check-bounces')->everyTenMinutes();
Schedule::command('mailcore:verify-domains')->daily();
Schedule::command('mailcore:cleanup-old-logs')->daily();

// Warmup: send the day's quota in small batches spread through the day.
// process-warmup sends up to 5 per mailbox per run and stops at the daily
// target, so running it through the day trickles the emails out naturally.
Schedule::command('mailcore:process-warmup')
    ->everyFifteenMinutes()
    ->between('8:00', '21:00')
    ->withoutOverlapping();

// Warmup: advance each active schedule one warmup-day per CALENDAR day. This
// resets the daily counter and raises the target along the ramp (5/10/20/50/100).
Schedule::call(function () {
    WarmupSchedule::where('status', 'active')->get()->each->advanceDay();
})->dailyAt('00:05')->name('warmup-advance-day')->withoutOverlapping();
