<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_name' => ['required', 'string', 'min:2', 'max:150'],
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'schedule_id' => ['required', 'integer', 'exists:doctor_schedules,id'],
            'appointment_date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
