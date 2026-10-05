<?php

namespace App\Http\Requests\Knowledge;

use Illuminate\Foundation\Http\FormRequest;

class ImportKnowledgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:zip',
                'mimetypes:application/zip,application/x-zip-compressed,application/octet-stream',
                'max:10240',
            ],
        ];
    }
}
