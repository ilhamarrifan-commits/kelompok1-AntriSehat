<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TakeQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'poly' => ['required', 'string', 'max:100'],
            'patient_name' => ['required', 'string', 'min:2', 'max:150'],
        ];
    }
}
