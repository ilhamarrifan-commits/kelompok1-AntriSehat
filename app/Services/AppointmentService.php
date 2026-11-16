<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Appointment;
use App\Models\DoctorSchedule;
use App\Support\ClinicClock;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    /**
     * Buat janji temu.
     *
     * Aturan: (1) jadwal harus milik dokter tsb & dokter aktif, (2) tanggal harus
     * jatuh pada hari praktik jadwal dan belum lewat, (3) pasien tidak boleh punya
     * janji aktif lain yang jamnya bertumpuk pada tanggal yang sama, (4) kuota
     * sesi (max_quota, default 30) tidak boleh terlampaui.
     *
     * Baris jadwal dikunci (SELECT ... FOR UPDATE) selama transaksi, sehingga dua
     * permintaan bersamaan untuk sesi yang sama dieksekusi bergantian dan kuota
     * tidak bisa jebol (race condition).
     *
     * @param array{patient_name: string, doctor_id: int, schedule_id: int, appointment_date: string} $data
     */
    public function book(array $data): Appointment
    {
        $patient = $this->normalizeName($data['patient_name']);
        $date = ClinicClock::parseDate($data['appointment_date']);
        $dateStr = $date->toDateString();

        return DB::transaction(function () use ($data, $patient, $date, $dateStr) {
            /** @var DoctorSchedule|null $schedule */
            $schedule = DoctorSchedule::with('doctor')->lockForUpdate()->find($data['schedule_id']);

            if (! $schedule || $schedule->doctor_id !== (int) $data['doctor_id']) {
                throw new BusinessRuleException('Jadwal tidak sesuai dengan dokter yang dipilih.', 'SCHEDULE_DOCTOR_MISMATCH', 422);
            }

            if (! $schedule->doctor->is_active) {
                throw new BusinessRuleException('Dokter sedang tidak aktif.', 'DOCTOR_INACTIVE', 422);
            }

            $this->assertSlotMatchesSchedule($schedule, $date);

            $conflict = Appointment::active()
                ->where('appointment_date', $dateStr)
                ->whereRaw('LOWER(patient_name) = ?', [mb_strtolower($patient)])
                ->whereHas('schedule', fn ($q) => $q
                    ->where('start_time', '<', $schedule->end_time)
                    ->where('end_time', '>', $schedule->start_time))
                ->exists();

            if ($conflict) {
                throw new BusinessRuleException(
                    'Pasien sudah memiliki janji temu yang jamnya bertumpuk pada tanggal tersebut.',
                    'APPOINTMENT_CONFLICT',
                    409,
                );
            }

            $booked = $this->bookedCount($schedule->id, $dateStr);

            if ($booked >= $schedule->max_quota) {
                throw new BusinessRuleException(
                    "Kuota sesi sudah penuh ({$schedule->max_quota} pasien).",
                    'QUOTA_FULL',
                    409,
                );
            }

            return Appointment::create([
                'patient_name' => $patient,
                'doctor_id' => $schedule->doctor_id,
                'schedule_id' => $schedule->id,
                'appointment_date' => $dateStr,
                'status' => Appointment::BOOKED,
            ])->load(['doctor', 'schedule']);
        });
    }

    public function cancel(Appointment $appointment): Appointment
    {
        return DB::transaction(function () use ($appointment) {
            $locked = Appointment::lockForUpdate()->findOrFail($appointment->id);

            if ($locked->status !== Appointment::BOOKED) {
                throw new BusinessRuleException(
                    "Janji temu berstatus '{$locked->status}' tidak dapat dibatalkan.",
                    'INVALID_STATUS_TRANSITION',
                    409,
                );
            }

            $locked->update(['status' => Appointment::CANCELLED]);

            return $locked->load(['doctor', 'schedule']);
        });
    }

    /** @return array<string, mixed> */
    public function availability(DoctorSchedule $schedule, string $date): array
    {
        $day = ClinicClock::parseDate($date);
        $this->assertSlotMatchesSchedule($schedule, $day, checkPast: false);

        $booked = $this->bookedCount($schedule->id, $day->toDateString());

        return [
            'schedule_id' => $schedule->id,
            'date' => $day->toDateString(),
            'max_quota' => $schedule->max_quota,
            'booked' => $booked,
            'remaining' => max(0, $schedule->max_quota - $booked),
        ];
    }

    private function bookedCount(int $scheduleId, string $date): int
    {
        return Appointment::active()
            ->where('schedule_id', $scheduleId)
            ->where('appointment_date', $date)
            ->count();
    }

    private function assertSlotMatchesSchedule(DoctorSchedule $schedule, \Illuminate\Support\Carbon $date, bool $checkPast = true): void
    {
        if ($date->isoWeekday() !== $schedule->day_of_week) {
            throw new BusinessRuleException(
                'Tanggal yang dipilih tidak sesuai dengan hari praktik pada jadwal dokter.',
                'SLOT_MISMATCH',
                422,
            );
        }

        if (! $checkPast) {
            return;
        }

        $now = ClinicClock::now();

        if ($date->toDateString() < $now->toDateString()) {
            throw new BusinessRuleException('Tanggal janji temu sudah lewat.', 'PAST_DATE', 422);
        }

        if ($date->toDateString() === $now->toDateString() && $now->format('H:i:s') >= $schedule->end_time) {
            throw new BusinessRuleException('Sesi praktik hari ini sudah berakhir.', 'SESSION_ENDED', 422);
        }
    }

    private function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name));
    }
}
