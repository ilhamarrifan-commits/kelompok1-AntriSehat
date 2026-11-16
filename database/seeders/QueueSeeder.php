<?php

namespace Database\Seeders;

use App\Models\QueueTicket;
use App\Services\QueueService;
use App\Support\ClinicClock;
use Illuminate\Database\Seeder;

/** Data demo antrean hari ini (dilewati bila hari ini sudah ada antrean). */
class QueueSeeder extends Seeder
{
    public function run(QueueService $queues): void
    {
        if (QueueTicket::where('date', ClinicClock::today())->exists()) {
            return;
        }

        foreach (['Rina Marlina', 'Joko Susilo', 'Dewi Lestari'] as $name) {
            $queues->take('Umum', $name);
        }
        $queues->take('Gigi', 'Agus Pratama');
        $queues->callNext('Umum');
    }
}
