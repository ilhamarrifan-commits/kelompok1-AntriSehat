<?php

namespace Tests\Feature;

use App\Models\QueueTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

/** Tantangan DevOps K05: scheduler reset/pengelolaan antrean harian. */
class DailyResetCommandTest extends TestCase
{
    use CreatesClinicData;
    use RefreshDatabase;

    private function ticket(string $date, int $number, string $status, string $poly = 'Umum'): QueueTicket
    {
        return QueueTicket::create([
            'patient_name' => "P{$number}", 'poly' => $poly, 'queue_number' => $number, 'date' => $date, 'status' => $status,
        ]);
    }

    public function test_command_is_registered_in_the_scheduler_to_run_daily_at_midnight_clinic_time(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command, 'antrian:reset-harian'));

        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertSame('Asia/Jakarta', (string) $event->timezone);
        $this->assertTrue($event->onOneServer);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_command_closes_stale_tickets_from_previous_days_only(): void
    {
        $this->freezeClinicTime('2026-11-17 00:00:05');

        $staleWaiting = $this->ticket('2026-11-16', 1, 'waiting');
        $staleCalled = $this->ticket('2026-11-16', 2, 'called');
        $staleFinished = $this->ticket('2026-11-16', 3, 'finished');
        $todayWaiting = $this->ticket('2026-11-17', 1, 'waiting');

        $this->artisan('antrian:reset-harian')->assertSuccessful();

        $this->assertSame('cancelled', $staleWaiting->fresh()->status);
        $this->assertSame('finished', $staleCalled->fresh()->status);
        $this->assertSame('finished', $staleFinished->fresh()->status);
        $this->assertSame('waiting', $todayWaiting->fresh()->status, 'Tiket hari ini tidak boleh tersentuh');
    }

    public function test_command_is_idempotent(): void
    {
        $this->freezeClinicTime('2026-11-17 00:00:05');
        $this->ticket('2026-11-16', 1, 'waiting');

        $this->artisan('antrian:reset-harian')->assertSuccessful();
        $this->artisan('antrian:reset-harian')->expectsOutputToContain('0 tiket waiting telah dibatalkan')->assertSuccessful();
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->freezeClinicTime('2026-11-17 00:00:05');
        $stale = $this->ticket('2026-11-16', 1, 'waiting');

        $this->artisan('antrian:reset-harian --dry-run --purge-days=0')
            ->expectsOutputToContain('1 tiket waiting akan dibatalkan')
            ->assertSuccessful();

        $this->assertSame('waiting', $stale->fresh()->status);
        $this->assertDatabaseCount('queues', 1);
    }

    public function test_purge_days_deletes_only_tickets_older_than_the_window(): void
    {
        $this->freezeClinicTime('2026-11-17 00:00:05');
        $this->ticket('2026-11-01', 1, 'finished');
        $this->ticket('2026-11-16', 1, 'finished');

        $this->artisan('antrian:reset-harian --purge-days=7')->assertSuccessful();

        $this->assertSame(['2026-11-16'], QueueTicket::pluck('date')->all());
    }

    public function test_numbering_restarts_at_one_even_if_the_scheduler_never_ran(): void
    {
        $this->makeDoctor(['poly' => 'Umum']);

        $this->freezeClinicTime('2026-11-16 08:00:00');
        $this->postJson('/api/queues', ['poly' => 'Umum', 'patient_name' => 'Ani']);
        $this->postJson('/api/queues', ['poly' => 'Umum', 'patient_name' => 'Budi']);

        // Hari berikutnya, command TIDAK dijalankan sama sekali.
        $this->freezeClinicTime('2026-11-17 07:30:00');
        $this->postJson('/api/queues', ['poly' => 'Umum', 'patient_name' => 'Citra'])->assertJsonPath('data.queue_number', 1);
    }

    public function test_invalid_date_option_is_rejected(): void
    {
        $this->artisan('antrian:reset-harian --date=besok')->assertFailed();
    }
}
