<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function __construct()
    {
        //
    }

    public function handle(\Illuminate\Auth\Events\Login $event): void
    {
        if ($event->guard === 'applicant') {
            return; // applicants aren't rows in `users`
        }

        ActivityLog::create([
            'user_id' => $event->user->id,
            'event' => 'login_success',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}