<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AgentFunction\RejectFunctionRequest;
use App\Models\AgentFunction;
use App\Services\ApprovalService;
use App\Support\ApiResponse;
use App\Transformers\AgentFunctionTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FunctionApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvalService,
        private readonly AgentFunctionTransformer $transformer,
    ) {}

    public function approve(Request $request, AgentFunction $function): JsonResponse
    {
        $this->authorize('approve', AgentFunction::class);

        $this->approvalService->approve($function, $request->user());
        $function->load(['component', 'approver', 'parameters']);

        return ApiResponse::data($this->transformer->item($function));
    }

    public function reject(RejectFunctionRequest $request, AgentFunction $function): JsonResponse
    {
        $this->authorize('reject', AgentFunction::class);

        $this->approvalService->reject($function, $request->user());
        $function->load(['component', 'approver', 'parameters']);

        return ApiResponse::data($this->transformer->item($function));
    }
}
