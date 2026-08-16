<?php

namespace App\Http\Requests\Knowledge;

use App\Enums\KnowledgeType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKnowledgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(KnowledgeType::class)],
            'content' => ['required', 'string'],
            'component_id' => ['required', 'integer', 'exists:component,id'],
            'valid_until' => [
                Rule::requiredIf(fn () => $this->input('type') === KnowledgeType::Temporario->value),
                'nullable', 'date',
            ],
        ];
    }
}
