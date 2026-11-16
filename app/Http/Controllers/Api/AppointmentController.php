<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentRequest;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(private readonly AppointmentService $appointments)
    {
    }

    /** GET /api/appointments?date=&doctor_id=&status= (staf, berisi data pasien). */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'doctor_id' => ['sometimes', 'integer'],
            'status' => ['sometimes', 'in:booked,completed,cancelled'],
        ]);

        return Appointment::query()
            ->with(['doctor:id,name,poly', 'schedule'])
            ->when($filters['date'] ?? null, fn ($q, $v) => $q->where('appointment_date', $v))
            ->when($filters['doctor_id'] ?? null, fn ($q, $v) => $q->where('doctor_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('appointment_date')->orderBy('id')
            ->paginate(15);
    }

    public function store(AppointmentRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->appointments->book($request->validated())], 201);
    }

    public function show(Appointment $appointment): JsonResponse
    {
        return response()->json(['data' => $appointment->load(['doctor', 'schedule'])]);
    }

    public function cancel(Appointment $appointment): JsonResponse
    {
        return response()->json(['data' => $this->appointments->cancel($appointment)]);
    }
}
