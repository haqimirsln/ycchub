<?php

use App\Http\Middleware\EnsureAccountApproved;
use App\Http\Middleware\HoldDuringDatabaseUpdate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'approved' => EnsureAccountApproved::class,
            'advisor.readonly' => \App\Http\Middleware\AdvisorReadOnly::class,
        ]);
        $middleware->web(append: [HoldDuringDatabaseUpdate::class]);
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('home.redirect'));
        // cPanel/Cloudflare sit in front of PHP; trust their forwarded headers so HTTPS URLs are generated correctly.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
