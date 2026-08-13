<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: [
            __DIR__ . '/../routes/api.php',
            __DIR__ . '/../routes/auth.php',
            __DIR__ . '/../routes/superadmin.php',
            __DIR__ . '/../routes/admin.php',
            __DIR__ . '/../routes/staff.php',
        ],
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => Spatie\Permission\Middleware\PermissionMiddleware::class
        ]);
    })
    ->withBroadcasting(
        __DIR__ . '/../routes/channels.php',
        ['middleware' => ['api', 'auth:sanctum']]
    )
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
