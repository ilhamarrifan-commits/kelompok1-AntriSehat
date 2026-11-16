<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appointment extends Model
{
    public const BOOKED = 'booked';
    public const COMPLETED = 'completed';
    public const CANCELLED = 'cancelled';

    protected $fillable = ['patient_name', 'doctor_id', 'schedule_id', 'appointment_date', 'status'];

    // "appointment_date" dijaga sebagai string Y-m-d (tanpa cast).

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DoctorSchedule::class, 'schedule_id');
    }

    /** Janji temu yang masih memakai slot (belum dibatalkan). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', self::CANCELLED);
    }
}
