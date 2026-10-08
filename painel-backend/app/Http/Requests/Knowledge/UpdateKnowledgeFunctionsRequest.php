<?php

namespace App\Http\Requests\Knowledge;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKnowledgeFunctionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'function_ids' => ['present', 'array'],
            'function_ids.*' => ['integer', 'distinct', 'exists:function,id'],
        ];
    }
}
