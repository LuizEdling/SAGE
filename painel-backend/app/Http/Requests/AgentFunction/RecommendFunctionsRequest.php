<?php

namespace App\Http\Requests\AgentFunction;

use Illuminate\Foundation\Http\FormRequest;

class RecommendFunctionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'knowledge_id' => ['required', 'integer', 'exists:knowledge,id'],
        ];
    }
}
