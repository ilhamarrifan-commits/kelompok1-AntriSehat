<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_returns_200_json_when_database_is_up(): void
    {
        $this->getJson('/health')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJson(['status' => 'ok', 'database' => 'up'])
            ->assertJsonStructure(['service', 'timestamp', 'status', 'database', 'latency_ms']);
    }

    public function test_health_works_without_accept_json_header(): void
    {
        // Jenkins/curl biasa tidak mengirim "Accept: application/json".
        $this->get('/health')->assertOk()->assertJson(['database' => 'up']);
    }

    public function test_health_returns_503_when_database_is_unreachable(): void
    {
        // Koneksi sengaja rusak, apa pun driver default-nya (sqlite/mysql).
        config([
            'database.connections.rusak' => ['driver' => 'sqlite', 'database' => '/path/yang/tidak/ada/db.sqlite', 'prefix' => ''],
            'database.default' => 'rusak',
        ]);
        DB::purge('rusak');

        $this->getJson('/health')
            ->assertStatus(503)
            ->assertJson(['status' => 'error', 'database' => 'down']);
    }
}
