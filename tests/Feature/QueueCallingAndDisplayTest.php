<?php

namespace Tests\Feature;

use App\Models\QueueTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesClinicData;
use Tests\TestCase;

class QueueCallingAndDisplayTest extends TestCase
{
    use CreatesClinicData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freezeClinicTime('2026-11-16 08:00:00');
        $this->makeDoctor(['poly' => 'Umum']);
        $this->makeDoctor(['name' => 'drg. Gigi', 'poly' => 'Gigi']);
    }

    private function take(string $poly, string $patient): void
    {
        $this->postJson('/api/queues', ['poly' => $poly, 'patient_name' => $patient])->assertCreated();
    }

    public function test_call_next_moves_lowest_waiting_ticket_to_called(): void
    {
        $this->take('Umum', 'Ani');
        $this->take('Umum', 'Budi');

        $this->postJson('/api/queues/call-next', ['poly' => 'Umum'])
            ->assertOk()
            ->assertJsonPath('data.queue_number', 1)
            ->assertJsonPath('data.status', 'called')
            ->assertJsonPath('data.patient_name', 'Ani');

        $this->assertNotNull(QueueTicket::where('queue_number', 1)->first()->called_at);
        $this->assertSame('waiting', QueueTicket::where('queue_number', 2)->first()->status);
    }

    public function test_calling_next_finishes_previously_called_ticket_in_same_poly(): void
    {
        $this->take('Umum', 'Ani');
        $this->take('Umum', 'Budi');

        $this->postJson('/api/queues/call-next', ['poly' => 'Umum']);
        $this->postJson('/api/queues/call-next', ['poly' => 'Umum'])->assertJsonPath('data.queue_number', 2);

        $this->assertSame('finished', QueueTicket::where('queue_number', 1)->first()->status);
        $this->assertSame(1, QueueTicket::where('poly', 'Umum')->where('status', 'called')->count());
    }

    public function test_call_next_only_affects_requested_poly(): void
    {
        $this->take('Umum', 'Ani');
        $this->take('Gigi', 'Gita');

        $this->postJson('/api/queues/call-next', ['poly' => 'Gigi'])->assertJsonPath('data.patient_name', 'Gita');

        $this->assertSame('waiting', QueueTicket::where('poly', 'Umum')->first()->status);
    }

    public function test_call_next_returns_404_when_nobody_is_waiting(): void
    {
        $this->postJson('/api/queues/call-next', ['poly' => 'Umum'])
            ->assertNotFound()
            ->assertJson(['code' => 'NO_WAITING_QUEUE']);
    }

    public function test_call_next_ignores_tickets_from_previous_days(): void
    {
        QueueTicket::create(['patient_name' => 'Kemarin', 'poly' => 'Umum', 'queue_number' => 1, 'date' => '2026-11-15', 'status' => 'waiting']);

        $this->postJson('/api/queues/call-next', ['poly' => 'Umum'])->assertNotFound();
    }

    public function test_finish_and_cancel_enforce_valid_transitions(): void
    {
        $this->take('Umum', 'Ani');
        $this->take('Umum', 'Budi');
        $first = QueueTicket::where('queue_number', 1)->first();
        $second = QueueTicket::where('queue_number', 2)->first();

        // waiting tidak bisa langsung finished
        $this->patchJson("/api/queues/{$first->id}/finish")->assertStatus(409)->assertJson(['code' => 'INVALID_STATUS_TRANSITION']);

        $this->postJson('/api/queues/call-next', ['poly' => 'Umum']);
        $this->patchJson("/api/queues/{$first->id}/finish")->assertOk()->assertJsonPath('data.status', 'finished');

        // finished tidak bisa dibatalkan; waiting bisa
        $this->patchJson("/api/queues/{$first->id}/cancel")->assertStatus(409);
        $this->patchJson("/api/queues/{$second->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_live_display_shows_now_serving_next_and_waiting_count_per_poly(): void
    {
        $this->take('Umum', 'Ani');
        $this->take('Umum', 'Budi');
        $this->take('Umum', 'Citra');
        $this->take('Gigi', 'Gita');
        $this->postJson('/api/queues/call-next', ['poly' => 'Umum']);

        $response = $this->getJson('/api/display')->assertOk()->assertJsonPath('data.date', '2026-11-16');

        $umum = collect($response->json('data.polies'))->firstWhere('poly', 'Umum');
        $this->assertSame(1, $umum['now_serving']['queue_number']);
        $this->assertSame(2, $umum['next_waiting']['queue_number']);
        $this->assertSame(2, $umum['waiting_count']);
        $this->assertSame(3, $umum['total_issued']);

        $gigi = collect($response->json('data.polies'))->firstWhere('poly', 'Gigi');
        $this->assertNull($gigi['now_serving']);
        $this->assertSame(1, $gigi['next_waiting']['queue_number']);
    }

    public function test_live_display_can_be_filtered_by_poly_and_lists_empty_polies(): void
    {
        $this->getJson('/api/display?poly=Gigi')
            ->assertOk()
            ->assertJsonCount(1, 'data.polies')
            ->assertJsonPath('data.polies.0.poly', 'Gigi')
            ->assertJsonPath('data.polies.0.now_serving', null)
            ->assertJsonPath('data.polies.0.waiting_count', 0);
    }

    public function test_live_display_only_shows_today(): void
    {
        QueueTicket::create(['patient_name' => 'Kemarin', 'poly' => 'Umum', 'queue_number' => 7, 'date' => '2026-11-15', 'status' => 'called']);

        $umum = collect($this->getJson('/api/display')->json('data.polies'))->firstWhere('poly', 'Umum');
        $this->assertNull($umum['now_serving']);
        $this->assertSame(0, $umum['total_issued']);
    }
}
