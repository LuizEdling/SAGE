<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Component\StoreComponentImageRequest;
use App\Models\Component;
use App\Models\ComponentImage;
use App\Support\ApiResponse;
use App\Transformers\ComponentImageTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class ComponentImageController extends Controller
{
    public function __construct(private readonly ComponentImageTransformer $transformer) {}

    public function store(StoreComponentImageRequest $request, Component $component): JsonResponse
    {
        $this->authorize('update', $component);

        $path = $request->file('image')->store('component', 'public');

        $image = $component->images()->create([
            'url' => Storage::disk('public')->url($path),
            'caption' => $request->validated('caption'),
        ]);

        return ApiResponse::data($this->transformer->item($image), status: 201);
    }

    public function destroy(Component $component, ComponentImage $image): JsonResponse
    {
        $this->authorize('update', $component);

        $image->delete();

        return ApiResponse::message('Imagem removida.');
    }
}
