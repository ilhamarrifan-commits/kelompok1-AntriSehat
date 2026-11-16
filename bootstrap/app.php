<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\EnsureStaffToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['staff' => EnsureStaffToken::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // API selalu membalas JSON (termasuk error validasi 422 & 404), tanpa redirect.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'health') || $request->expectsJson()
        );

        // Pelanggaran aturan bisnis adalah error "normal", bukan bug — jangan penuhi log.
        $exceptions->dontReport(BusinessRuleException::class);
    })->create();
