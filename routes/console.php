<?php

use Illuminate\Support\Facades\Schedule;

// Run `php artisan schedule:work` locally, or one cron entry in production:
// * * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1

Schedule::command('db:backup --keep=7')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('model:prune')->dailyAt('03:00');
Schedule::command('invoices:mark-overdue')->dailyAt('00:10');
Schedule::command('auth:clear-resets')->weekly();
