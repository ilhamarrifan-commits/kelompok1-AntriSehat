<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Data master: selalu dijalankan (idempotent).
        $this->call(DoctorSeeder::class);

        // Data demo: hanya di luar production agar tidak mengotori data asli.
        if (! app()->isProduction()) {
            $this->call([QueueSeeder::class, AppointmentSeeder::class]);
        }
    }
}
