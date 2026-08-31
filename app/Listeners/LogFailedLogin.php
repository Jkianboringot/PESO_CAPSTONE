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

    public function handle(Failed $event): void
    {
        ActivityLog::create([
            'user_id'    => optional($event->user)->id,
            'event'      => 'login_failed',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'meta'       => ['attempted_login' => $event->credentials['email'] ?? null],
        ]);
    }
}