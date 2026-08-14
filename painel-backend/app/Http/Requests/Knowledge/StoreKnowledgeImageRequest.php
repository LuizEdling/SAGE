<?php

namespace App\Http\Requests\Knowledge;

use Illuminate\Foundation\Http\FormRequest;

class StoreKnowledgeImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 'mimes' checa o conteúdo real do arquivo (não só a extensão) e
            // exclui SVG/BMP de propósito — SVG pode conter <script> embutido.
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,gif,webp',
                'dimensions:max_width=8000,max_height=8000',
                'max:5120',
            ],
            'caption' => ['nullable', 'string', 'max:255'],
        ];
    }
}
