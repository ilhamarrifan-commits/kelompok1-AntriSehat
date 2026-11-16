<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Doctor;
use App\Models\QueueTicket;
use App\Support\ClinicClock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class QueueService
{
    private const MAX_ATTEMPTS = 8;

    /**
     * Ambil nomor antrean baru untuk sebuah poli.
     *
     * Nomor = (nomor terbesar poli tsb pada HARI INI) + 1, sehingga otomatis
     * kembali ke 1 setiap pergantian hari dan terpisah per poli. Jika dua
     * pasien mengambil nomor bersamaan, baris poli dikunci agar bergantian; unique
     * index (poly,date,queue_number) + retry menjadi pengaman terakhir — nomor
     * tidak pernah kembar.
     */
    public function take(string $poly, string $patientName): QueueTicket
    {
        $poly = $this->resolvePoly($poly);
        $date = ClinicClock::today();

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($poly, $patientName, $date) {
                    // Serialkan pengambil nomor pada poli yang sama: kunci satu baris
                    // dokter milik poli tsb (SELECT ... FOR UPDATE) SEBELUM membaca nomor
                    // terakhir. Tanpa ini, puluhan request serentak saling bertabrakan di
                    // unique index dan sebagian bisa kehabisan jatah retry.
                    Doctor::where('poly', $poly)->orderBy('id')->lockForUpdate()->value('id');

                    $next = ((int) QueueTicket::where('poly', $poly)
                        ->where('date', $date)
                        ->max('queue_number')) + 1;

                    return QueueTicket::create([
                        'patient_name' => $patientName,
                        'poly' => $poly,
                        'queue_number' => $next,
                        'date' => $date,
                        'status' => QueueTicket::WAITING,
                    ]);
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::MAX_ATTEMPTS) {
                    throw $e;
                }
                usleep(random_int(5_000, 25_000));
            }
        }
    }

    /** Jumlah pasien yang masih menunggu di depan tiket ini. */
    public function waitingAhead(QueueTicket $ticket): int
    {
        return QueueTicket::where('poly', $ticket->poly)
            ->where('date', $ticket->date)
            ->where('status', QueueTicket::WAITING)
            ->where('queue_number', '<', $ticket->queue_number)
            ->count();
    }

    /**
     * Staf memanggil antrean berikutnya (waiting -> called).
     * Tiket yang sebelumnya berstatus "called" di poli yang sama otomatis
     * dianggap selesai, sehingga hanya ada satu "sedang dilayani" per poli.
     */
    public function callNext(string $poly): QueueTicket
    {
        $date = ClinicClock::today();

        return DB::transaction(function () use ($poly, $date) {
            $next = QueueTicket::where('poly', $poly)
                ->where('date', $date)
                ->where('status', QueueTicket::WAITING)
                ->orderBy('queue_number')
                ->lockForUpdate()
                ->first();

            if (! $next) {
                throw new BusinessRuleException(
                    "Tidak ada antrean yang menunggu di poli {$poly} hari ini.",
                    'NO_WAITING_QUEUE',
                    404,
                );
            }

            QueueTicket::where('poly', $next->poly)
                ->where('date', $date)
                ->where('status', QueueTicket::CALLED)
                ->update(['status' => QueueTicket::FINISHED]);

            $next->update(['status' => QueueTicket::CALLED, 'called_at' => now()]);

            return $next->refresh();
        });
    }

    public function finish(QueueTicket $ticket): QueueTicket
    {
        return $this->transition($ticket, [QueueTicket::CALLED], QueueTicket::FINISHED);
    }

    public function cancel(QueueTicket $ticket): QueueTicket
    {
        return $this->transition($ticket, [QueueTicket::WAITING, QueueTicket::CALLED], QueueTicket::CANCELLED);
    }

    /**
     * Data layar antrean (live display) untuk hari ini, per poli.
     *
     * @return array{date: string, polies: array<int, array<string, mixed>>}
     */
    public function liveDisplay(?string $poly = null): array
    {
        $date = ClinicClock::today();

        $tickets = QueueTicket::where('date', $date)
            ->when($poly, fn ($q) => $q->where('poly', $poly))
            ->orderBy('queue_number')
            ->get()
            ->groupBy('poly');

        $polies = Doctor::active()
            ->when($poly, fn ($q) => $q->where('poly', $poly))
            ->distinct()->pluck('poly')
            ->merge($tickets->keys())
            ->unique()->sort()->values();

        $rows = $polies->map(function (string $name) use ($tickets) {
            $list = $tickets->get($name, collect());
            $serving = $list->firstWhere('status', QueueTicket::CALLED);
            $next = $list->firstWhere('status', QueueTicket::WAITING);

            return [
                'poly' => $name,
                'now_serving' => $serving ? $this->summary($serving) : null,
                'next_waiting' => $next ? $this->summary($next) : null,
                'waiting_count' => $list->where('status', QueueTicket::WAITING)->count(),
                'total_issued' => (int) $list->max('queue_number'),
            ];
        })->all();

        return ['date' => $date, 'polies' => $rows];
    }

    /**
     * Housekeeping harian: tiket hari-hari sebelumnya yang masih menggantung
     * ditutup (waiting -> cancelled, called -> finished). Idempotent.
     *
     * @return array{cancelled: int, finished: int}
     */
    public function closeStaleTickets(string $today, bool $dryRun = false): array
    {
        $waiting = QueueTicket::where('date', '<', $today)->where('status', QueueTicket::WAITING);
        $called = QueueTicket::where('date', '<', $today)->where('status', QueueTicket::CALLED);

        if ($dryRun) {
            return ['cancelled' => $waiting->count(), 'finished' => $called->count()];
        }

        return DB::transaction(fn () => [
            'cancelled' => $waiting->update(['status' => QueueTicket::CANCELLED]),
            'finished' => $called->update(['status' => QueueTicket::FINISHED]),
        ]);
    }

    /** Hapus tiket yang lebih lama dari $cutoff (Y-m-d). */
    public function purgeOlderThan(string $cutoff, bool $dryRun = false): int
    {
        $query = QueueTicket::where('date', '<', $cutoff);

        return $dryRun ? $query->count() : $query->delete();
    }

    private function resolvePoly(string $poly): string
    {
        $canonical = Doctor::active()->where('poly', $poly)->value('poly');

        if ($canonical === null) {
            throw new BusinessRuleException(
                "Poli '{$poly}' tidak ditemukan atau belum memiliki dokter aktif.",
                'POLY_NOT_FOUND',
                422,
            );
        }

        return $canonical;
    }

    /** @param array<int, string> $from */
    private function transition(QueueTicket $ticket, array $from, string $to): QueueTicket
    {
        return DB::transaction(function () use ($ticket, $from, $to) {
            $locked = QueueTicket::lockForUpdate()->findOrFail($ticket->id);

            if (! in_array($locked->status, $from, true)) {
                throw new BusinessRuleException(
                    "Antrean berstatus '{$locked->status}' tidak dapat diubah menjadi '{$to}'.",
                    'INVALID_STATUS_TRANSITION',
                    409,
                );
            }

            $locked->update(['status' => $to]);

            return $locked;
        });
    }

    /** @return array<string, mixed> */
    private function summary(QueueTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'queue_number' => $ticket->queue_number,
            'patient_name' => $ticket->patient_name,
            'called_at' => $ticket->called_at?->toIso8601String(),
        ];
    }
}
