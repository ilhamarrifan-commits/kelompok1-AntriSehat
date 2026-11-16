<?php

namespace Tests\Feature;

use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

/** ATURAN KRITIS (c): anti-bentrok, plus validasi slot sesuai jadwal dokter. */
class AppointmentRulesTest extends TestCase
{
    use CreatesClinicData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClinicTime('2026-11-15 10:00:00'); // Minggu
    }

    private function book(array $payload)
    {
        return $this->postJson('/api/appointments', $payload);
    }

    // ---------------------------------------------------------------- anti-bentrok

    public function test_same_patient_cannot_book_the_same_session_twice(): void
    {
        $schedule = $this->makeSchedule($this->makeDoctor());

        $this->book($this->bookingPayload($schedule, 'Budi Santoso'))->assertCreated();
        $this->book($this->bookingPayload($schedule, 'Budi Santoso'))
            ->assertStatus(409)->assertJson(['code' => 'APPOINTMENT_CONFLICT']);
    }

    public function test_same_patient_cannot_book_overlapping_sessions_with_different_doctors(): void
    {
        $morning = $this->makeSchedule($this->makeDoctor(), ['start_time' => '08:00', 'end_time' => '12:00']);
        $overlap = $this->makeSchedule($this->makeDoctor(['name' => 'drg. Lain', 'poly' => 'Gigi']), ['start_time' => '10:00', 'end_time' => '14:00']);

        $this->book($this->bookingPayload($morning, 'Budi'))->assertCreated();
        $this->book($this->bookingPayload($overlap, 'Budi'))
            ->assertStatus(409)->assertJson(['code' => 'APPOINTMENT_CONFLICT']);
    }

    public function test_same_patient_can_book_back_to_back_and_separate_sessions(): void
    {
        $doctor = $this->makeDoctor();
        $morning = $this->makeSchedule($doctor, ['start_time' => '08:00', 'end_time' => '12:00']);
        $adjacent = $this->makeSchedule($doctor, ['start_time' => '12:00', 'end_time' => '15:00']); // menempel, tidak bertumpuk
        $evening = $this->makeSchedule($doctor, ['start_time' => '16:00', 'end_time' => '18:00']);

        $this->book($this->bookingPayload($morning, 'Budi'))->assertCreated();
        $this->book($this->bookingPayload($adjacent, 'Budi'))->assertCreated();
        $this->book($this->bookingPayload($evening, 'Budi'))->assertCreated();
    }

    public function test_same_patient_can_book_the_same_session_on_a_different_date(): void
    {
        $schedule = $this->makeSchedule($this->makeDoctor());

        $this->book($this->bookingPayload($schedule, 'Budi', '2026-11-16'))->assertCreated();
        $this->book($this->bookingPayload($schedule, 'Budi', '2026-11-23'))->assertCreated();
    }

    public function test_different_patients_can_share_a_session(): void
    {
        $schedule = $this->makeSchedule($this->makeDoctor());

        $this->book($this->bookingPayload($schedule, 'Ani'))->assertCreated();
        $this->book($this->bookingPayload($schedule, 'Budi'))->assertCreated();
    }

    public function test_conflict_detection_ignores_case_and_extra_whitespace_in_names(): void
    {
        $schedule = $this->makeSchedule($this->makeDoctor());

        $this->book($this->bookingPayload($schedule, 'Budi Santoso'))->assertCreated();
        $this->book($this->bookingPayload($schedule, '  budi    SANTOSO '))->assertStatus(409);
    }

    public function test_cancelled_appointment_does_not_block_rebooking(): void
    {
        $schedule = $this->makeSchedule($this->makeDoctor());

        $id = $this->book($this->bookingPayload($schedule, 'Budi'))->json('data.id');
        $this->patchJson("/api/appointments/{$id}/cancel")->assertOk();

        $this->book($this->bookingPayload($schedule, 'Budi'))->assertCreated();
    }

    // ---------------------------------------------------------------- slot sesuai jadwal

    public function test_date_must_fall_on_the_schedule_weekday(): void
    {
        $schedule = $this->makeSchedule($this->makeDoctor(), ['day_of_week' => 1]); // Senin

        $this->book($this->bookingPayload($schedule, 'Ani', '2026-11-17')) // Selasa
            ->assertStatus(422)->assertJson(['code' => 'SLOT_MISMATCH']);

        $this->assertSame(0, Appointment::count());
    }

    public function test_schedule_must_belong_to_the_given_doctor(): void
    {
        $schedule = $this->makeSchedule($this->makeDoctor());
        $other = $this->makeDoctor(['name' => 'dr. Lain']);

        $payload = $this->bookingPayload($schedule, 'Ani');
        $payload['doctor_id'] = $other->id;

        $this->book($payload)->assertStatus(422)->assertJson(['code' => 'SCHEDULE_DOCTOR_MISMATCH']);
    }

    public function test_inactive_doctor_cannot_be_booked(): void
    {
        $schedule = $this->makeSchedule($this->makeDoctor(['is_active' => false]));

        $this->book($this->bookingPayload($schedule, 'Ani'))->assertStatus(422)->assertJson(['code' => 'DOCTOR_INACTIVE']);
    }

    public function test_past_dates_are_rejected(): void
    {
        $schedule = $this->makeSchedule($this->makeDoctor());

        $this->book($this->bookingPayload($schedule, 'Ani', '2026-11-09')) // Senin lalu
            ->assertStatus(422)->assertJson(['code' => 'PAST_DATE']);
    }

    public function test_todays_session_is_rejected_after_it_ended_but_accepted_before(): void
    {
        $schedule = $this->makeSchedule($this->makeDoctor()); // Senin 08:00-12:00

        $this->freezeClinicTime('2026-11-16 13:00:00');
        $this->book($this->bookingPayload($schedule, 'Ani'))->assertStatus(422)->assertJson(['code' => 'SESSION_ENDED']);

        $this->freezeClinicTime('2026-11-16 09:00:00');
        $this->book($this->bookingPayload($schedule, 'Ani'))->assertCreated();
    }

    public function test_payload_validation_errors(): void
    {
        $this->book([])->assertStatus(422)->assertJsonValidationErrors(['patient_name', 'doctor_id', 'schedule_id', 'appointment_date']);

        $schedule = $this->makeSchedule($this->makeDoctor());
        $payload = $this->bookingPayload($schedule, 'Ani', '16-11-2026');
        $this->book($payload)->assertStatus(422)->assertJsonValidationErrors(['appointment_date']);

        $payload = $this->bookingPayload($schedule, 'Ani');
        $payload['doctor_id'] = 9999;
        $this->book($payload)->assertStatus(422)->assertJsonValidationErrors(['doctor_id']);
    }

    public function test_successful_booking_returns_expected_resource(): void
    {
        $schedule = $this->makeSchedule($this->makeDoctor());

        $this->book($this->bookingPayload($schedule, '  Ani   Lestari '))
            ->assertCreated()
            ->assertJsonPath('data.patient_name', 'Ani Lestari')
            ->assertJsonPath('data.appointment_date', '2026-11-16')
            ->assertJsonPath('data.status', 'booked')
            ->assertJsonPath('data.doctor.name', 'dr. Andi Wijaya');
    }
}
