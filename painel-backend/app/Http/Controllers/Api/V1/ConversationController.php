<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Repositories\ConversationRepository;
use App\Support\ApiResponse;
use App\Transformers\ConversationTransformer;
use Illuminate\Http\JsonResponse;

class ConversationController extends Controller
{
    public function __construct(
        private readonly ConversationRepository $repository,
        private readonly ConversationTransformer $transformer,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Conversation::class);

        return ApiResponse::data($this->transformer->paginated($this->repository->paginate()));
    }

    public function show(Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $conversation->loadTranscript();

        return ApiResponse::data($this->transformer->item($conversation));
    }
}
