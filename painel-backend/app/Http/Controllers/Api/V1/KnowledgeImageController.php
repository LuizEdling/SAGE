<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\StoreKnowledgeImageRequest;
use App\Models\Knowledge;
use App\Models\KnowledgeImage;
use App\Support\ApiResponse;
use App\Transformers\KnowledgeImageTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class KnowledgeImageController extends Controller
{
    public function __construct(private readonly KnowledgeImageTransformer $transformer) {}

    public function store(StoreKnowledgeImageRequest $request, Knowledge $knowledge): JsonResponse
    {
        $this->authorize('update', $knowledge);

        $path = $request->file('image')->store('knowledge', 'public');

        $image = $knowledge->images()->create([
            'url' => Storage::disk('public')->url($path),
            'caption' => $request->validated('caption'),
        ]);

        return ApiResponse::data($this->transformer->item($image), status: 201);
    }

    public function destroy(Knowledge $knowledge, KnowledgeImage $image): JsonResponse
    {
        $this->authorize('update', $knowledge);

        $image->delete();

        return ApiResponse::message('Imagem removida.');
    }
}
