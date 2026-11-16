<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Services\AppointmentService;
use App\Support\ClinicClock;
use Illuminate\Database\Seeder;

/** Data demo janji temu pada kemunculan berikutnya dari hari praktik jadwal pertama. */
class AppointmentSeeder extends Seeder
{
    public function run(AppointmentService $appointments): void
    {
        if (Appointment::exists()) {
            return;
        }

        $schedule = DoctorSchedule::orderBy('id')->first();
        if (! $schedule) {
            return;
        }

        $date = ClinicClock::now()->startOfDay();
        while ($date->isoWeekday() !== $schedule->day_of_week || $date->isToday() && ClinicClock::now()->format('H:i:s') >= $schedule->end_time) {
            $date->addDay();
        }

        foreach (['Rina Marlina', 'Joko Susilo'] as $patient) {
            $appointments->book([
                'patient_name' => $patient,
                'doctor_id' => $schedule->doctor_id,
                'schedule_id' => $schedule->id,
                'appointment_date' => $date->toDateString(),
            ]);
        }
    }
}
