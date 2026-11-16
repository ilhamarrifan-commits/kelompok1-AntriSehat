<?php

namespace Tests\Feature;

use App\Models\QueueTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

class QueueNumberingTest extends TestCase
{
    use CreatesClinicData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeDoctor(['name' => 'dr. Umum', 'poly' => 'Umum']);
        $this->makeDoctor(['name' => 'drg. Gigi', 'poly' => 'Gigi', 'specialization' => 'Dokter Gigi']);
    }

    private function take(string $poly, string $patient = 'Pasien'): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/queues', ['poly' => $poly, 'patient_name' => $patient]);
    }

    /** ATURAN KRITIS (a): nomor berurutan per poli. */
    public function test_queue_numbers_are_sequential_per_poly(): void
    {
        $this->freezeClinicTime('2026-11-16 08:00:00');

        $this->take('Umum', 'Ani')->assertCreated()->assertJsonPath('data.queue_number', 1)->assertJsonPath('waiting_ahead', 0);
        $this->take('Umum', 'Budi')->assertCreated()->assertJsonPath('data.queue_number', 2)->assertJsonPath('waiting_ahead', 1);
        $this->take('Umum', 'Citra')->assertCreated()->assertJsonPath('data.queue_number', 3)->assertJsonPath('data.status', 'waiting');
    }

    public function test_each_poly_has_its_own_independent_counter(): void
    {
        $this->freezeClinicTime('2026-11-16 08:00:00');

        $this->take('Umum')->assertJsonPath('data.queue_number', 1);
        $this->take('Umum')->assertJsonPath('data.queue_number', 2);
        $this->take('Gigi')->assertJsonPath('data.queue_number', 1);
        $this->take('Umum')->assertJsonPath('data.queue_number', 3);
        $this->take('Gigi')->assertJsonPath('data.queue_number', 2);
    }

    /** ATURAN KRITIS (a): kembali ke 1 setiap hari (reset harian). */
    public function test_queue_number_resets_to_one_on_a_new_day(): void
    {
        $this->freezeClinicTime('2026-11-16 08:00:00');
        $this->take('Umum');
        $this->take('Umum');
        $this->take('Umum')->assertJsonPath('data.queue_number', 3);

        $this->freezeClinicTime('2026-11-17 08:00:00');
        $this->take('Umum')->assertJsonPath('data.queue_number', 1)->assertJsonPath('data.date', '2026-11-17');
        $this->take('Umum')->assertJsonPath('data.queue_number', 2);

        // Antrean hari sebelumnya tetap utuh (tidak ditimpa).
        $this->assertSame(3, QueueTicket::where('date', '2026-11-16')->where('poly', 'Umum')->count());
    }

    public function test_day_boundary_follows_clinic_timezone_not_utc(): void
    {
        // 23:59 WIB = 16:59 UTC (hari yang sama); 00:01 WIB = 17:01 UTC hari sebelumnya.
        $this->freezeClinicTime('2026-11-16 23:59:00');
        $this->take('Umum')->assertJsonPath('data.queue_number', 1)->assertJsonPath('data.date', '2026-11-16');

        $this->freezeClinicTime('2026-11-17 00:01:00');
        $this->take('Umum')->assertJsonPath('data.queue_number', 1)->assertJsonPath('data.date', '2026-11-17');
    }

    public function test_cancelled_ticket_numbers_are_never_reused(): void
    {
        $this->freezeClinicTime('2026-11-16 08:00:00');

        $first = $this->take('Umum')->json('data.id');
        QueueTicket::whereKey($first)->update(['status' => 'cancelled']);

        $this->take('Umum')->assertJsonPath('data.queue_number', 2);
    }

    public function test_unknown_poly_is_rejected(): void
    {
        $this->take('Poli Antah Berantah')
            ->assertStatus(422)
            ->assertJson(['code' => 'POLY_NOT_FOUND']);
    }

    public function test_poly_with_only_inactive_doctors_is_rejected(): void
    {
        $this->makeDoctor(['name' => 'dr. Cuti', 'poly' => 'Mata', 'is_active' => false]);

        $this->take('Mata')->assertStatus(422)->assertJson(['code' => 'POLY_NOT_FOUND']);
    }

    public function test_validation_requires_poly_and_patient_name(): void
    {
        $this->postJson('/api/queues', [])->assertStatus(422)->assertJsonValidationErrors(['poly', 'patient_name']);
    }

    public function test_unique_index_blocks_duplicate_numbers_at_database_level(): void
    {
        $this->freezeClinicTime('2026-11-16 08:00:00');
        $row = ['patient_name' => 'X', 'poly' => 'Umum', 'queue_number' => 1, 'date' => '2026-11-16', 'status' => 'waiting'];
        QueueTicket::create($row);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        QueueTicket::create($row);
    }
}
