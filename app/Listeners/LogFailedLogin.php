<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Failed;

class LogFailedLogin
{
    public function __construct()
    {
        //
    }

    // app/Listeners/LogSuccessfulLogin.php// app/Listeners/LogFailedLogin.php
    public function handle(Failed $event): void
    {
        if ($event->guard === 'applicant') {
            return;
        }

        ActivityLog::create([
            'user_id' => $event->user?->id,
            'event' => 'login_failed',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}