<?php

namespace Tests\Feature;

use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

class DoctorAndScheduleCrudTest extends TestCase
{
    use CreatesClinicData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClinicTime('2026-11-15 10:00:00');
    }

    public function test_doctor_crud_lifecycle(): void
    {
        $id = $this->postJson('/api/doctors', [
            'name' => 'dr. Maya Putri, Sp.A', 'specialization' => 'Spesialis Anak', 'poly' => 'Anak',
        ])->assertCreated()->assertJsonPath('data.is_active', true)->json('data.id');

        $this->getJson("/api/doctors/{$id}")->assertOk()->assertJsonPath('data.poly', 'Anak');
        $this->getJson('/api/doctors?poly=Anak')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/doctors?poly=Gigi')->assertOk()->assertJsonCount(0, 'data');

        $this->patchJson("/api/doctors/{$id}", ['phone' => '0812', 'is_active' => false])
            ->assertOk()->assertJsonPath('data.phone', '0812')->assertJsonPath('data.is_active', false);
        $this->getJson('/api/doctors?active=1')->assertJsonCount(0, 'data');

        $this->deleteJson("/api/doctors/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('doctors', ['id' => $id]);
    }

    public function test_doctor_validation(): void
    {
        $this->postJson('/api/doctors', [])->assertStatus(422)->assertJsonValidationErrors(['name', 'specialization', 'poly']);
    }

    public function test_doctor_with_appointments_cannot_be_deleted(): void
    {
        $doctor = $this->makeDoctor();
        $schedule = $this->makeSchedule($doctor);
        $this->postJson('/api/appointments', $this->bookingPayload($schedule, 'Ani'))->assertCreated();

        $this->deleteJson("/api/doctors/{$doctor->id}")->assertStatus(409)->assertJson(['code' => 'DOCTOR_HAS_APPOINTMENTS']);
        $this->assertDatabaseHas('doctors', ['id' => $doctor->id]);
    }

    public function test_deleting_a_doctor_removes_their_schedules(): void
    {
        $doctor = $this->makeDoctor();
        $this->makeSchedule($doctor);

        $this->deleteJson("/api/doctors/{$doctor->id}")->assertNoContent();
        $this->assertDatabaseCount('doctor_schedules', 0);
    }

    public function test_schedule_crud_lifecycle_with_default_quota_30(): void
    {
        $doctor = $this->makeDoctor();

        $id = $this->postJson("/api/doctors/{$doctor->id}/schedules", [
            'day_of_week' => 2, 'start_time' => '08:00', 'end_time' => '12:00',
        ])->assertCreated()->assertJsonPath('data.max_quota', 30)->assertJsonPath('data.start_time', '08:00:00')->json('data.id');

        $this->getJson("/api/doctors/{$doctor->id}/schedules")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/schedules/{$id}")->assertOk()->assertJsonPath('data.doctor.id', $doctor->id);

        $this->patchJson("/api/schedules/{$id}", ['max_quota' => 20, 'end_time' => '13:30'])
            ->assertOk()->assertJsonPath('data.max_quota', 20)->assertJsonPath('data.end_time', '13:30:00');

        $this->deleteJson("/api/schedules/{$id}")->assertNoContent();
        $this->assertDatabaseCount('doctor_schedules', 0);
    }

    public function test_schedule_validation_rules(): void
    {
        $doctor = $this->makeDoctor();
        $url = "/api/doctors/{$doctor->id}/schedules";

        $this->postJson($url, ['day_of_week' => 8, 'start_time' => '8am', 'end_time' => 'x'])
            ->assertStatus(422)->assertJsonValidationErrors(['day_of_week', 'start_time', 'end_time']);

        $this->postJson($url, ['day_of_week' => 1, 'start_time' => '12:00', 'end_time' => '08:00'])
            ->assertStatus(422)->assertJson(['code' => 'INVALID_TIME_RANGE']);

        $this->postJson($url, ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '12:00', 'max_quota' => 0])
            ->assertStatus(422)->assertJsonValidationErrors(['max_quota']);
    }

    public function test_overlapping_schedules_for_the_same_doctor_are_rejected(): void
    {
        $doctor = $this->makeDoctor();
        $this->makeSchedule($doctor, ['day_of_week' => 1, 'start_time' => '08:00', 'end_time' => '12:00']);
        $url = "/api/doctors/{$doctor->id}/schedules";

        $this->postJson($url, ['day_of_week' => 1, 'start_time' => '11:00', 'end_time' => '14:00'])
            ->assertStatus(422)->assertJson(['code' => 'SCHEDULE_OVERLAP']);

        // Hari lain / jam menempel boleh.
        $this->postJson($url, ['day_of_week' => 2, 'start_time' => '08:00', 'end_time' => '12:00'])->assertCreated();
        $this->postJson($url, ['day_of_week' => 1, 'start_time' => '12:00', 'end_time' => '14:00'])->assertCreated();
    }

    public function test_cannot_shrink_quota_below_existing_bookings_or_move_a_booked_schedule(): void
    {
        $schedule = $this->makeSchedule($this->makeDoctor());
        foreach (['Pasien A', 'Pasien B', 'Pasien C'] as $name) {
            $this->postJson('/api/appointments', $this->bookingPayload($schedule, $name))->assertCreated();
        }

        $this->patchJson("/api/schedules/{$schedule->id}", ['max_quota' => 2])
            ->assertStatus(422)->assertJson(['code' => 'QUOTA_BELOW_BOOKED']);
        $this->patchJson("/api/schedules/{$schedule->id}", ['max_quota' => 3])->assertOk();

        $this->patchJson("/api/schedules/{$schedule->id}", ['start_time' => '09:00'])
            ->assertStatus(409)->assertJson(['code' => 'SCHEDULE_HAS_BOOKINGS']);

        $this->deleteJson("/api/schedules/{$schedule->id}")
            ->assertStatus(409)->assertJson(['code' => 'SCHEDULE_HAS_APPOINTMENTS']);
    }

    public function test_unknown_resources_return_json_404(): void
    {
        $this->getJson('/api/doctors/9999')->assertNotFound();
        $this->getJson('/api/schedules/9999')->assertNotFound();
        $this->get('/api/doctors/9999')->assertNotFound()->assertHeader('Content-Type', 'application/json');
        $this->assertSame(0, Doctor::count());
    }
}
