<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use CreatesClinicData;
    use RefreshDatabase;

    private array $doctor = ['name' => 'dr. Baru', 'specialization' => 'Umum', 'poly' => 'Umum'];

    public function test_staff_endpoints_require_the_token_when_configured(): void
    {
        config(['antrisehat.staff_token' => 'rahasia-123']);

        $this->postJson('/api/doctors', $this->doctor)->assertStatus(401)->assertJson(['code' => 'UNAUTHORIZED']);
        $this->postJson('/api/doctors', $this->doctor, ['X-Staff-Token' => 'salah'])->assertStatus(401);
        $this->postJson('/api/queues/call-next', ['poly' => 'Umum'])->assertStatus(401);
        $this->getJson('/api/appointments')->assertStatus(401);

        $this->postJson('/api/doctors', $this->doctor, ['X-Staff-Token' => 'rahasia-123'])->assertCreated();
        $this->postJson('/api/doctors', $this->doctor + ['name' => 'dr. Dua'], ['Authorization' => 'Bearer rahasia-123'])->assertCreated();
    }

    public function test_patient_facing_endpoints_stay_public_even_when_token_is_set(): void
    {
        config(['antrisehat.staff_token' => 'rahasia-123']);
        $doctor = $this->makeDoctor();
        $schedule = $this->makeSchedule($doctor);
        $this->freezeClinicTime('2026-11-15 10:00:00');

        $this->getJson('/api/doctors')->assertOk();
        $this->getJson('/api/display')->assertOk();
        $this->postJson('/api/queues', ['poly' => 'Umum', 'patient_name' => 'Ani'])->assertCreated();
        $this->postJson('/api/appointments', $this->bookingPayload($schedule, 'Ani'))->assertCreated();
        $this->getJson('/health')->assertOk();
    }

    public function test_staff_endpoints_are_closed_in_production_when_no_token_is_configured(): void
    {
        $this->app['env'] = 'production';
        config(['antrisehat.staff_token' => null]);

        $this->postJson('/api/doctors', $this->doctor)->assertStatus(401);
    }

    public function test_staff_endpoints_are_open_in_non_production_without_token(): void
    {
        config(['antrisehat.staff_token' => null]);

        $this->postJson('/api/doctors', $this->doctor)->assertCreated();
    }
}
