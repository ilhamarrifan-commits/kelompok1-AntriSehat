<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\QueueTicket;
use App\Support\ClinicClock;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $today = ClinicClock::today();
        
        $stats = [
            'total_doctors' => Doctor::active()->count(),
            'today_queues' => QueueTicket::where('date', $today)->count(),
            'waiting_queues' => QueueTicket::where('date', $today)->where('status', QueueTicket::WAITING)->count(),
            'today_appointments' => Appointment::where('appointment_date', $today)->count(),
        ];

        $doctors = Doctor::with('schedules')->orderBy('name')->get();
        $queues = QueueTicket::where('date', $today)->orderBy('poly')->orderBy('queue_number')->get();
        $appointments = Appointment::with(['doctor', 'schedule'])->where('appointment_date', $today)->orderBy('id')->get();

        return view('dashboard', compact('stats', 'doctors', 'queues', 'appointments', 'today'));
    }
}
