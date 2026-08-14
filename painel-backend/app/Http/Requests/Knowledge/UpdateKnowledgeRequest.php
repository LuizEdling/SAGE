<?php

namespace App\Http\Requests\Knowledge;

use App\Enums\KnowledgeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKnowledgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', Rule::enum(KnowledgeType::class)],
            'content' => ['sometimes', 'required', 'string'],
            'component_id' => ['sometimes', 'required', 'integer', 'exists:component,id'],
            'valid_until' => ['nullable', 'date'],
        ];
    }
}
