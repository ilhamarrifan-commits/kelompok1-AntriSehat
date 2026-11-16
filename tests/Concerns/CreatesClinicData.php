<?php

namespace Tests\Concerns;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Illuminate\Support\Carbon;

trait CreatesClinicData
{
    /**
     * Bekukan waktu pada zona waktu klinik (Asia/Jakarta).
     * Catatan: 2026-11-16 adalah hari SENIN (ISO day 1).
     */
    protected function freezeClinicTime(string $datetime): void
    {
        Carbon::setTestNow(Carbon::parse($datetime, 'Asia/Jakarta'));
    }

    protected function makeDoctor(array $overrides = []): Doctor
    {
        return Doctor::create($overrides + [
            'name' => 'dr. Andi Wijaya',
            'specialization' => 'Dokter Umum',
            'poly' => 'Umum',
            'is_active' => true,
        ]);
    }

    protected function makeSchedule(Doctor $doctor, array $overrides = []): DoctorSchedule
    {
        return $doctor->schedules()->create($overrides + [
            'day_of_week' => 1,          // Senin
            'start_time' => '08:00',
            'end_time' => '12:00',
            'max_quota' => 30,
        ]);
    }

    /** Payload POST /api/appointments. */
    protected function bookingPayload(DoctorSchedule $schedule, string $patient, string $date = '2026-11-16'): array
    {
        return [
            'patient_name' => $patient,
            'doctor_id' => $schedule->doctor_id,
            'schedule_id' => $schedule->id,
            'appointment_date' => $date,
        ];
    }
}
