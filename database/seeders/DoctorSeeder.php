<?php

namespace Database\Seeders;

use App\Models\Doctor;
use App\Models\DoctorSchedule;
use Illuminate\Database\Seeder;

/** Idempotent: aman dijalankan berulang (updateOrCreate). */
class DoctorSeeder extends Seeder
{
    public function run(): void
    {
        // day_of_week ISO: 1 = Senin ... 7 = Minggu
        $data = [
            ['dr. Andi Wijaya', 'Dokter Umum', 'Umum', '081200000001', [
                [[1, 2, 3, 4, 5], '08:00', '12:00'],
            ]],
            ['dr. Siti Rahmawati', 'Dokter Umum', 'Umum', '081200000002', [
                [[1, 3, 5], '13:00', '16:00'],
            ]],
            ['drg. Budi Santoso', 'Dokter Gigi', 'Gigi', '081200000003', [
                [[1, 2, 3, 4], '09:00', '14:00'],
            ]],
            ['dr. Maya Putri, Sp.A', 'Spesialis Anak', 'Anak', '081200000004', [
                [[2, 4, 6], '08:00', '12:00'],
            ]],
            ['dr. Hendra Gunawan, Sp.PD', 'Spesialis Penyakit Dalam', 'Penyakit Dalam', '081200000005', [
                [[1, 3, 5], '14:00', '18:00'],
            ]],
        ];

        foreach ($data as [$name, $specialization, $poly, $phone, $sessions]) {
            $doctor = Doctor::updateOrCreate(
                ['name' => $name],
                ['specialization' => $specialization, 'poly' => $poly, 'phone' => $phone, 'is_active' => true],
            );

            foreach ($sessions as [$days, $start, $end]) {
                foreach ($days as $day) {
                    DoctorSchedule::updateOrCreate(
                        ['doctor_id' => $doctor->id, 'day_of_week' => $day, 'start_time' => $start],
                        ['end_time' => $end, 'max_quota' => config('antrisehat.default_max_quota', 30)],
                    );
                }
            }
        }
    }
}
