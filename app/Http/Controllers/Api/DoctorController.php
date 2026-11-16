<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DoctorRequest;
use App\Models\Doctor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $doctors = Doctor::query()
            ->when($request->query('poly'), fn ($q, $poly) => $q->where('poly', $poly))
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->with('schedules')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $doctors]);
    }

    public function show(Doctor $doctor): JsonResponse
    {
        return response()->json(['data' => $doctor->load('schedules')]);
    }

    public function store(DoctorRequest $request): JsonResponse
    {
        $doctor = Doctor::create($request->validated());

        return response()->json(['data' => $doctor->refresh()], 201);
    }

    public function update(DoctorRequest $request, Doctor $doctor): JsonResponse
    {
        $doctor->update($request->validated());

        return response()->json(['data' => $doctor->refresh()->load('schedules')]);
    }

    public function destroy(Doctor $doctor): JsonResponse
    {
        if ($doctor->appointments()->exists()) {
            throw new BusinessRuleException(
                'Dokter sudah memiliki riwayat janji temu dan tidak dapat dihapus. Nonaktifkan dokter (is_active=false) sebagai gantinya.',
                'DOCTOR_HAS_APPOINTMENTS',
                409,
            );
        }

        $doctor->delete();

        return response()->json(null, 204);
    }
}
