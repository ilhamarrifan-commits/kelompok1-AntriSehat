<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleRequest;
use App\Models\Doctor;
use App\Models\DoctorSchedule;
use App\Services\AppointmentService;
use App\Services\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorScheduleController extends Controller
{
    public function __construct(private readonly ScheduleService $schedules)
    {
    }

    public function index(Doctor $doctor): JsonResponse
    {
        return response()->json([
            'data' => $doctor->schedules()->orderBy('day_of_week')->orderBy('start_time')->get(),
        ]);
    }

    public function show(DoctorSchedule $schedule): JsonResponse
    {
        return response()->json(['data' => $schedule->load('doctor')]);
    }

    public function store(ScheduleRequest $request, Doctor $doctor): JsonResponse
    {
        $schedule = $this->schedules->create($doctor, $request->validated());

        return response()->json(['data' => $schedule->refresh()], 201);
    }

    public function update(ScheduleRequest $request, DoctorSchedule $schedule): JsonResponse
    {
        return response()->json(['data' => $this->schedules->update($schedule, $request->validated())]);
    }

    public function destroy(DoctorSchedule $schedule): JsonResponse
    {
        $this->schedules->delete($schedule);

        return response()->json(null, 204);
    }

    /** GET /api/schedules/{schedule}/availability?date=YYYY-MM-DD — sisa kuota sesi. */
    public function availability(Request $request, DoctorSchedule $schedule, AppointmentService $appointments): JsonResponse
    {
        $date = $request->validate(['date' => ['required', 'date_format:Y-m-d']])['date'];

        return response()->json(['data' => $appointments->availability($schedule, $date)]);
    }
}
