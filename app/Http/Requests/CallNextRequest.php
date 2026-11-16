<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CallNextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // akses dijaga middleware "staff"
    }

    public function rules(): array
    {
        return ['poly' => ['required', 'string', 'max:100']];
    }
}
