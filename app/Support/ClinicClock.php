<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Satu-satunya sumber "sekarang" dan "hari ini" untuk logika bisnis klinik,
 * selalu dalam zona waktu klinik (default Asia/Jakarta).
 */
final class ClinicClock
{
    public static function timezone(): string
    {
        return config('antrisehat.timezone', 'Asia/Jakarta');
    }

    public static function now(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    /** Tanggal hari ini (Y-m-d) di zona waktu klinik. */
    public static function today(): string
    {
        return self::now()->toDateString();
    }

    /** Parse "Y-m-d" menjadi awal hari di zona waktu klinik. */
    public static function parseDate(string $date): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $date, self::timezone())->startOfDay();
    }
}
