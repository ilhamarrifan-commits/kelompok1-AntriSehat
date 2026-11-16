<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('antrisehat.staff_token');

        if ($expected === '') {
            // Fail-closed di production: endpoint staf tidak boleh terbuka.
            if (app()->isProduction()) {
                return $this->deny('STAFF_API_TOKEN belum dikonfigurasi di server.');
            }

            return $next($request);
        }

        $given = (string) ($request->header('X-Staff-Token') ?: $request->bearerToken());

        if (! hash_equals($expected, $given)) {
            return $this->deny('Token staf tidak valid.');
        }

        return $next($request);
    }

    private function deny(string $message): Response
    {
        return response()->json(['message' => $message, 'code' => 'UNAUTHORIZED'], 401);
    }
}
