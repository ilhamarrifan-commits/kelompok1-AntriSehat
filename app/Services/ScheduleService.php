<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Support\ClinicClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduleService
{
    /** @param array<string, mixed> $data */
    public function create(Doctor $doctor, array $data): DoctorSchedule
    {
        $data['max_quota'] ??= config('antrisehat.default_max_quota', 30);

        return DB::transaction(function () use ($doctor, $data) {
            $this->assertTimeRange($data['start_time'], $data['end_time']);
            $this->assertNoOverlap($doctor->id, $data['day_of_week'], $data['start_time'], $data['end_time']);

            return $doctor->schedules()->create($data);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(DoctorSchedule $schedule, array $data): DoctorSchedule
    {
        return DB::transaction(function () use ($schedule, $data) {
            // Kunci baris yang sama dengan proses booking agar tidak balapan.
            $schedule = DoctorSchedule::lockForUpdate()->findOrFail($schedule->id);

            $day = $data['day_of_week'] ?? $schedule->day_of_week;
            $start = $this->hhmmss($data['start_time'] ?? $schedule->start_time);
            $end = $this->hhmmss($data['end_time'] ?? $schedule->end_time);

            $this->assertTimeRange($start, $end);
            $this->assertNoOverlap($schedule->doctor_id, $day, $start, $end, $schedule->id);

            $timeChanged = $day !== $schedule->day_of_week
                || $start !== $this->hhmmss($schedule->start_time)
                || $end !== $this->hhmmss($schedule->end_time);

            $future = Appointment::active()
                ->where('schedule_id', $schedule->id)
                ->where('appointment_date', '>=', ClinicClock::today());

            if ($timeChanged && (clone $future)->exists()) {
                throw new BusinessRuleException(
                    'Hari/jam tidak dapat diubah karena sudah ada janji temu mendatang pada jadwal ini.',
                    'SCHEDULE_HAS_BOOKINGS',
                    409,
                );
            }

            if (isset($data['max_quota'])) {
                $peak = (int) (clone $future)
                    ->select('appointment_date', DB::raw('COUNT(*) as total'))
                    ->groupBy('appointment_date')
                    ->orderByDesc('total')
                    ->value('total');

                if ($data['max_quota'] < $peak) {
                    throw new BusinessRuleException(
                        "Kuota tidak boleh di bawah jumlah janji temu yang sudah ada ({$peak}).",
                        'QUOTA_BELOW_BOOKED',
                        422,
                    );
                }
            }

            $schedule->update(array_merge($data, [
                'day_of_week' => $day,
                'start_time' => $start,
                'end_time' => $end,
            ]));

            return $schedule->refresh();
        });
    }

    public function delete(DoctorSchedule $schedule): void
    {
        if ($schedule->appointments()->exists()) {
            throw new BusinessRuleException(
                'Jadwal tidak dapat dihapus karena sudah memiliki janji temu.',
                'SCHEDULE_HAS_APPOINTMENTS',
                409,
            );
        }

        $schedule->delete();
    }

    private function assertTimeRange(string $start, string $end): void
    {
        if ($this->hhmmss($end) <= $this->hhmmss($start)) {
            throw new BusinessRuleException('Jam selesai harus setelah jam mulai.', 'INVALID_TIME_RANGE', 422);
        }
    }

    private function assertNoOverlap(int $doctorId, int $day, string $start, string $end, ?int $ignoreId = null): void
    {
        $overlap = DoctorSchedule::where('doctor_id', $doctorId)
            ->where('day_of_week', $day)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('start_time', '<', $this->hhmmss($end))
            ->where('end_time', '>', $this->hhmmss($start))
            ->exists();

        if ($overlap) {
            throw new BusinessRuleException(
                'Jadwal bertumpuk dengan jadwal lain milik dokter yang sama pada hari tersebut.',
                'SCHEDULE_OVERLAP',
                422,
            );
        }
    }

    private function hhmmss(string $time): string
    {
        return Carbon::parse($time)->format('H:i:s');
    }
}
