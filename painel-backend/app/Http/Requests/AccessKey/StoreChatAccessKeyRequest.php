<?php

namespace App\Http\Requests\AccessKey;

use Illuminate\Foundation\Http\FormRequest;

class StoreChatAccessKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'active' => ['boolean'],
        ];
    }
}
