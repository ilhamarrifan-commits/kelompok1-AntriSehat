<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class DoctorSchedule extends Model
{
    protected $fillable = ['doctor_id', 'day_of_week', 'start_time', 'end_time', 'max_quota'];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'max_quota' => 'integer',
        ];
    }

    // Jam selalu disimpan "HH:MM:SS" agar perbandingan string/TIME konsisten.
    protected function startTime(): Attribute
    {
        return Attribute::make(set: fn ($value) => Carbon::parse($value)->format('H:i:s'));
    }

    protected function endTime(): Attribute
    {
        return Attribute::make(set: fn ($value) => Carbon::parse($value)->format('H:i:s'));
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'schedule_id');
    }
}
