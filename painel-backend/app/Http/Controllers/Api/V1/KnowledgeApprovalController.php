<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\RejectKnowledgeRequest;
use App\Models\Knowledge;
use App\Services\ApprovalService;
use App\Support\ApiResponse;
use App\Transformers\KnowledgeTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly KnowledgeTransformer $transformer,
    ) {}

    public function approve(Request $request, Knowledge $knowledge): JsonResponse
    {
        $this->authorize('approve', Knowledge::class);

        $this->approvalService->approve($knowledge, $request->user());
        $knowledge->load(['component', 'approver', 'images']);

        return ApiResponse::data($this->transformer->item($knowledge));
    }

    public function reject(RejectKnowledgeRequest $request, Knowledge $knowledge): JsonResponse
    {
        $this->authorize('reject', Knowledge::class);

        $this->approvalService->reject($knowledge, $request->user());
        $knowledge->load(['component', 'approver', 'images']);

        return ApiResponse::data($this->transformer->item($knowledge));
    }
}
