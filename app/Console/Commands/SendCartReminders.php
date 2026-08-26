<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\CronJobController;
use Illuminate\Console\Command;

class SendCartReminders extends Command
{
    protected $signature = 'cart:send-reminders';
    protected $description = 'Send cart reminder notifications to users';

    public function handle()
    {
        // Call your controller method
        app(CronJobController::class)->sendCartReminders();
        $this->info('Cart reminders sent successfully.');
    }
}
