<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');
        //     $middleware->alias([ use spatie for now
        //     'role' => \App\Http\Middleware\CheckRole::class,
        // ]);
    
       $middleware->redirectGuestsTo(fn ($request) =>
           $request->is('applicant/*') ? route('applicant.login') : route('login'));
           
        $middleware->redirectUsersTo(fn(Request $request) =>
            $request->routeIs('applicant.*') ? route('applicant.profile') : route('dashboard'));

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'log.jobportal' => \App\Http\Middleware\LogJobPortalVisit::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
