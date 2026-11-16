<?php

namespace Tests\Unit;

use App\Support\ClinicClock;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClinicClockTest extends TestCase
{
    public function test_today_uses_clinic_timezone_even_when_server_is_utc(): void
    {
        // 17:30 UTC tanggal 15 = 00:30 WIB tanggal 16.
        Carbon::setTestNow(Carbon::parse('2026-11-15 17:30:00', 'UTC'));

        $this->assertSame('2026-11-16', ClinicClock::today());
        $this->assertSame('Asia/Jakarta', ClinicClock::now()->timezoneName);
    }

    public function test_parse_date_returns_start_of_day_in_clinic_timezone(): void
    {
        $date = ClinicClock::parseDate('2026-11-16');

        $this->assertSame('2026-11-16 00:00:00', $date->format('Y-m-d H:i:s'));
        $this->assertSame(1, $date->isoWeekday()); // Senin
    }
}
