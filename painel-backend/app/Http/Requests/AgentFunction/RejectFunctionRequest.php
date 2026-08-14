<?php

namespace App\Http\Requests\AgentFunction;

use Illuminate\Foundation\Http\FormRequest;

class RejectFunctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
