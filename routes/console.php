<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('mailcore:parse-logs')->everyFiveMinutes();
Schedule::command('mailcore:check-bounces')->everyTenMinutes();
Schedule::command('mailcore:verify-domains')->daily();
Schedule::command('mailcore:cleanup-old-logs')->daily();
