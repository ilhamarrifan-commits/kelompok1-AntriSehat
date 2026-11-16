<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\DoctorScheduleController;
use App\Http\Controllers\Api\QueueController;
use Illuminate\Support\Facades\Route;

/*
| Semua route di sini otomatis berawalan /api.
*/

// ---- Publik (pasien & layar antrean) ----
Route::get('doctors', [DoctorController::class, 'index']);
Route::get('doctors/{doctor}', [DoctorController::class, 'show']);
Route::get('doctors/{doctor}/schedules', [DoctorScheduleController::class, 'index']);
Route::get('schedules/{schedule}', [DoctorScheduleController::class, 'show']);
Route::get('schedules/{schedule}/availability', [DoctorScheduleController::class, 'availability']);

Route::post('queues', [QueueController::class, 'store']);
Route::get('display', [QueueController::class, 'display']);

Route::post('appointments', [AppointmentController::class, 'store']);

// ---- Staf klinik (dijaga middleware "staff" / STAFF_API_TOKEN) ----
Route::middleware('staff')->group(function () {
    Route::post('doctors', [DoctorController::class, 'store']);
    Route::match(['put', 'patch'], 'doctors/{doctor}', [DoctorController::class, 'update']);
    Route::delete('doctors/{doctor}', [DoctorController::class, 'destroy']);

    Route::post('doctors/{doctor}/schedules', [DoctorScheduleController::class, 'store']);
    Route::match(['put', 'patch'], 'schedules/{schedule}', [DoctorScheduleController::class, 'update']);
    Route::delete('schedules/{schedule}', [DoctorScheduleController::class, 'destroy']);

    Route::get('queues', [QueueController::class, 'index']);
    Route::post('queues/call-next', [QueueController::class, 'callNext']);
    Route::patch('queues/{ticket}/finish', [QueueController::class, 'finish']);
    Route::patch('queues/{ticket}/cancel', [QueueController::class, 'cancel']);

    Route::get('appointments', [AppointmentController::class, 'index']);
    Route::get('appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::patch('appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
});
