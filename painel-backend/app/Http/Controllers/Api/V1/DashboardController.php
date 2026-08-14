<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Component;
use App\Models\Conversation;
use App\Repositories\KnowledgeRepository;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly KnowledgeRepository $knowledgeRepository) {}

    public function stats(): JsonResponse
    {
        return ApiResponse::data([
            ...$this->knowledgeRepository->pipelineCounts(),
            'modulos' => Component::count(),
            'conversas' => Conversation::count(),
        ]);
    }
}
