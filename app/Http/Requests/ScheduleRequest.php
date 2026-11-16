<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // akses dijaga middleware "staff"
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            // ISO-8601: 1 = Senin ... 7 = Minggu
            'day_of_week' => [$required, 'integer', 'between:1,7'],
            'start_time' => [$required, 'date_format:H:i,H:i:s'],
            'end_time' => [$required, 'date_format:H:i,H:i:s'],
            'max_quota' => ['sometimes', 'integer', 'between:1,500'],
        ];
    }
}
