<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Middleware\EnsureRole;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'service' => config('app.name'),
    'message' => 'AntriSehat API — lihat /health dan /api/*',
]));

Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', EnsureRole::class.':admin'])
    ->name('dashboard');

Route::view('/pasien', 'pasien.home')
    ->middleware(['auth', EnsureRole::class.':pasien'])
    ->name('pasien.home');

// Health check untuk smoke test Jenkins. Sengaja di luar grup middleware "web"
// (session/cookie/CSRF) agar tidak ikut gagal/menulis session saat DB bermasalah.
Route::get('/health', HealthController::class)
    ->withoutMiddleware('web')
    ->name('health');
