<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CallNextRequest;
use App\Http\Requests\TakeQueueRequest;
use App\Models\QueueTicket;
use App\Services\QueueService;
use App\Support\ClinicClock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueController extends Controller
{
    public function __construct(private readonly QueueService $queues)
    {
    }

    /** GET /api/queues?poly=&date=&status= (staf) — default: hari ini. */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'poly' => ['sometimes', 'string', 'max:100'],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'in:waiting,called,finished,cancelled'],
        ]);

        $tickets = QueueTicket::query()
            ->where('date', $filters['date'] ?? ClinicClock::today())
            ->when($filters['poly'] ?? null, fn ($q, $v) => $q->where('poly', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('poly')->orderBy('queue_number')
            ->get();

        return response()->json(['data' => $tickets]);
    }

    /** POST /api/queues — pasien mengambil nomor antrean di sebuah poli. */
    public function store(TakeQueueRequest $request): JsonResponse
    {
        $ticket = $this->queues->take($request->validated('poly'), $request->validated('patient_name'));

        return response()->json([
            'data' => $ticket->refresh(),
            'waiting_ahead' => $this->queues->waitingAhead($ticket),
        ], 201);
    }

    /** POST /api/queues/call-next (staf) — panggil antrean berikutnya di sebuah poli. */
    public function callNext(CallNextRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->queues->callNext($request->validated('poly'))]);
    }

    public function finish(QueueTicket $ticket): JsonResponse
    {
        return response()->json(['data' => $this->queues->finish($ticket)]);
    }

    public function cancel(QueueTicket $ticket): JsonResponse
    {
        return response()->json(['data' => $this->queues->cancel($ticket)]);
    }

    /** GET /api/display[?poly=] — layar antrean live, publik. */
    public function display(Request $request): JsonResponse
    {
        $poly = $request->validate(['poly' => ['sometimes', 'string', 'max:100']])['poly'] ?? null;

        return response()->json(['data' => $this->queues->liveDisplay($poly)])
            ->header('Cache-Control', 'no-store');
    }
}
