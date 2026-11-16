<?php

use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'service' => config('app.name'),
    'message' => 'AntriSehat API — lihat /health dan /api/*',
]));

// Health check untuk smoke test Jenkins. Sengaja di luar grup middleware "web"
// (session/cookie/CSRF) agar tidak ikut gagal/menulis session saat DB bermasalah.
Route::get('/health', HealthController::class)
    ->withoutMiddleware('web')
    ->name('health');
