<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduler AntriSehat
|--------------------------------------------------------------------------
| Server hanya perlu SATU entri cron:
|   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
| (atau jalankan `php artisan schedule:work` di container).
*/

// Tiap tengah malam waktu klinik: tutup tiket kemarin yang menggantung.
// Penomoran sendiri tidak bergantung pada job ini (dihitung per tanggal),
// sehingga hari baru tetap mulai dari 1 walaupun scheduler sempat mati.
Schedule::command('antrian:reset-harian')
    ->dailyAt('00:00')
    ->timezone(config('antrisehat.timezone'))
    ->withoutOverlapping(10)
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/scheduler.log'));
