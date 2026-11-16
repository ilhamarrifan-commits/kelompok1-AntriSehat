<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tiket antrean (tabel "queues"). Dinamai QueueTicket agar tidak bentrok
 * dengan facade/alias global "Queue" milik Laravel.
 */
class QueueTicket extends Model
{
    public const WAITING = 'waiting';
    public const CALLED = 'called';
    public const FINISHED = 'finished';
    public const CANCELLED = 'cancelled';

    protected $table = 'queues';

    protected $fillable = ['patient_name', 'poly', 'queue_number', 'date', 'status', 'called_at'];

    protected function casts(): array
    {
        return [
            'queue_number' => 'integer',
            'called_at' => 'datetime',
            // Kolom "date" sengaja TIDAK di-cast: dijaga sebagai string Y-m-d
            // agar perbandingan di SQLite/MySQL konsisten.
        ];
    }
}
