<?php

use App\Providers\AppServiceProvider;
use App\Services\AuditLogService;
use App\Services\DuplicateDetectionService;

return [
    AppServiceProvider::class,
    AuditLogService::class,
    DuplicateDetectionService::class,
];
