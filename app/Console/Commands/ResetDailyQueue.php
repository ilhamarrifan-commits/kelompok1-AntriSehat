<?php

namespace App\Console\Commands;

use App\Services\QueueService;
use App\Support\ClinicClock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetDailyQueue extends Command
{
    protected $signature = 'antrian:reset-harian
                            {--date= : Anggap tanggal ini (Y-m-d) sebagai "hari ini" — untuk simulasi/backfill}
                            {--purge-days= : Hapus tiket antrean yang lebih lama dari N hari}
                            {--dry-run : Tampilkan dampaknya saja tanpa mengubah data}';

    protected $description = 'Reset harian antrean: tutup tiket hari-hari sebelumnya yang menggantung (opsional: hapus data lama).';

    public function handle(QueueService $queues): int
    {
        $today = $this->option('date') ?: ClinicClock::today();
        $dryRun = (bool) $this->option('dry-run');

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $today)) {
            $this->error('Format --date harus Y-m-d.');

            return self::INVALID;
        }

        $result = $queues->closeStaleTickets($today, $dryRun);
        $verb = $dryRun ? 'akan' : 'telah';

        $this->info("[{$today}] {$result['cancelled']} tiket waiting {$verb} dibatalkan, {$result['finished']} tiket called {$verb} diselesaikan.");

        $purgeDays = $this->option('purge-days') ?? config('antrisehat.queue_retention_days');
        $purged = 0;

        if ($purgeDays !== null && $purgeDays !== '') {
            $cutoff = ClinicClock::parseDate($today)->subDays((int) $purgeDays)->toDateString();
            $purged = $queues->purgeOlderThan($cutoff, $dryRun);
            $this->info("{$purged} tiket sebelum {$cutoff} {$verb} dihapus.");
        }

        $this->line('Nomor antrean hari ini dihitung per poli per tanggal, jadi otomatis mulai dari 1.');

        if (! $dryRun) {
            Log::info('antrian:reset-harian selesai', ['date' => $today] + $result + ['purged' => $purged]);
        }

        return self::SUCCESS;
    }
}
