<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * GET /health — dipakai smoke test Jenkins / load balancer.
 * 200 bila aplikasi hidup DAN database terjangkau; 503 bila database mati.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $startedAt = hrtime(true);

        try {
            DB::connection()->select('select 1');

            $body = [
                'status' => 'ok',
                'database' => 'up',
                'latency_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
            ];
            $code = 200;
        } catch (Throwable $e) {
            Log::error('Health check gagal: database tidak terjangkau', ['error' => $e->getMessage()]);

            $body = ['status' => 'error', 'database' => 'down'];
            if (config('app.debug')) {
                $body['error'] = $e->getMessage();
            }
            $code = 503;
        }

        return response()->json([
            'service' => config('app.name'),
            'timestamp' => now()->toIso8601String(),
        ] + $body, $code)->header('Cache-Control', 'no-store');
    }
}
