<?php

namespace Tests\Feature;

use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

/** ATURAN KRITIS (b): maksimal 30 pasien per sesi dokter. */
class AppointmentQuotaTest extends TestCase
{
    use CreatesClinicData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClinicTime('2026-11-15 10:00:00'); // Minggu; target booking: Senin 16 Nov
    }

    public function test_default_quota_of_schedule_is_30(): void
    {
        $schedule = $this->doctorSchedule();
        $this->assertSame(30, $schedule->fresh()->max_quota);
    }

    public function test_session_accepts_exactly_30_patients_and_rejects_the_31st(): void
    {
        $schedule = $this->doctorSchedule();

        for ($i = 1; $i <= 30; $i++) {
            $this->postJson('/api/appointments', $this->bookingPayload($schedule, "Pasien {$i}"))
                ->assertCreated();
        }

        $this->postJson('/api/appointments', $this->bookingPayload($schedule, 'Pasien 31'))
            ->assertStatus(409)
            ->assertJson(['code' => 'QUOTA_FULL']);

        $this->assertSame(30, Appointment::where('schedule_id', $schedule->id)->count());
    }

    public function test_quota_is_per_session_per_date(): void
    {
        $schedule = $this->doctorSchedule(['max_quota' => 2]);

        $this->postJson('/api/appointments', $this->bookingPayload($schedule, 'Pasien A'))->assertCreated();
        $this->postJson('/api/appointments', $this->bookingPayload($schedule, 'Pasien B'))->assertCreated();
        $this->postJson('/api/appointments', $this->bookingPayload($schedule, 'Pasien C'))->assertStatus(409);

        // Senin berikutnya punya kuota sendiri.
        $this->postJson('/api/appointments', $this->bookingPayload($schedule, 'Pasien C', '2026-11-23'))->assertCreated();
    }

    public function test_quota_is_per_doctor_session_not_shared_between_doctors(): void
    {
        $a = $this->doctorSchedule(['max_quota' => 1]);
        $b = $this->makeSchedule($this->makeDoctor(['name' => 'dr. Lain']), ['max_quota' => 1]);

        $this->postJson('/api/appointments', $this->bookingPayload($a, 'Ani'))->assertCreated();
        $this->postJson('/api/appointments', $this->bookingPayload($a, 'Budi'))->assertStatus(409);

        // Budi (sesi dokter lain, jam bertumpuk) tidak bentrok karena Budi belum punya janji aktif.
        $this->postJson('/api/appointments', $this->bookingPayload($b, 'Budi'))->assertCreated();
    }

    public function test_cancelled_appointment_frees_a_slot(): void
    {
        $schedule = $this->doctorSchedule(['max_quota' => 1]);

        $id = $this->postJson('/api/appointments', $this->bookingPayload($schedule, 'Ani'))->json('data.id');
        $this->postJson('/api/appointments', $this->bookingPayload($schedule, 'Budi'))->assertStatus(409);

        $this->patchJson("/api/appointments/{$id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->postJson('/api/appointments', $this->bookingPayload($schedule, 'Budi'))->assertCreated();
    }

    public function test_availability_endpoint_reports_remaining_quota(): void
    {
        $schedule = $this->doctorSchedule(['max_quota' => 3]);
        $this->postJson('/api/appointments', $this->bookingPayload($schedule, 'Ani'));

        $this->getJson("/api/schedules/{$schedule->id}/availability?date=2026-11-16")
            ->assertOk()
            ->assertJsonPath('data.max_quota', 3)
            ->assertJsonPath('data.booked', 1)
            ->assertJsonPath('data.remaining', 2);

        // Tanggal yang bukan hari praktik ditolak.
        $this->getJson("/api/schedules/{$schedule->id}/availability?date=2026-11-17")
            ->assertStatus(422)->assertJson(['code' => 'SLOT_MISMATCH']);
    }

    private function doctorSchedule(array $overrides = [])
    {
        return $this->makeSchedule($this->makeDoctor(), $overrides);
    }
}
