<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Knowledge\ImportKnowledgeRequest;
use App\Http\Requests\Knowledge\StoreKnowledgeRequest;
use App\Http\Requests\Knowledge\UpdateKnowledgeRequest;
use App\Models\Knowledge;
use App\Repositories\KnowledgeRepository;
use App\Services\ApprovalService;
use App\Services\Knowledge\DuplicateCheckerInterface;
use App\Services\Knowledge\IngestionDispatcherInterface;
use App\Services\Knowledge\KnowledgeImportService;
use App\Support\ApiResponse;
use App\Transformers\KnowledgeTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeController extends Controller
{
    public function __construct(
        private readonly KnowledgeRepository $repository,
        private readonly KnowledgeTransformer $transformer,
        private readonly DuplicateCheckerInterface $duplicateChecker,
        private readonly ApprovalService $approvalService,
        private readonly IngestionDispatcherInterface $ingestionDispatcher,
        private readonly KnowledgeImportService $importService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Knowledge::class);

        return ApiResponse::data($this->transformer->paginated($this->repository->paginateFiltered($request)));
    }

    public function store(StoreKnowledgeRequest $request): JsonResponse
    {
        $this->authorize('create', Knowledge::class);

        $knowledge = $this->repository->create($request->validated());
        $knowledge->load(['component', 'approver', 'images', 'functions']);

        return ApiResponse::data($this->transformer->item($knowledge), status: 201);
    }

    public function show(Knowledge $knowledge): JsonResponse
    {
        $this->authorize('view', $knowledge);

        $knowledge->load(['component', 'approver', 'images', 'functions']);

        return ApiResponse::data($this->transformer->item($knowledge));
    }

    public function update(UpdateKnowledgeRequest $request, Knowledge $knowledge): JsonResponse
    {
        $this->authorize('update', $knowledge);

        $wasApproved = $knowledge->status === ApprovalStatus::Aprovado;

        $this->approvalService->resetToPendingIfDecided($knowledge);
        $this->repository->update($knowledge, $request->validated());

        // Editar um conhecimento aprovado invalida o que já está indexado no
        // Qdrant (o conteúdo mudou) — some da base de busca até ser aprovado
        // de novo, para o agente nunca responder com base em texto antigo.
        if ($wasApproved) {
            $this->ingestionDispatcher->remove($knowledge);
        }

        $knowledge->load(['component', 'approver', 'images', 'functions']);

        return ApiResponse::data($this->transformer->item($knowledge));
    }

    public function destroy(Knowledge $knowledge): JsonResponse
    {
        $this->authorize('delete', $knowledge);

        $this->ingestionDispatcher->remove($knowledge);
        $this->repository->delete($knowledge);

        return ApiResponse::message('Conhecimento removido.');
    }

    public function checkDuplicate(Request $request): JsonResponse
    {
        $this->authorize('create', Knowledge::class);

        $data = $request->validate([
            'component_id' => ['required', 'integer', 'exists:component,id'],
            'content' => ['required', 'string'],
        ]);

        $duplicates = $this->duplicateChecker->findDuplicates($data['component_id'], $data['content']);

        return ApiResponse::data(['duplicates' => $duplicates->values()]);
    }

    public function import(ImportKnowledgeRequest $request): JsonResponse
    {
        $this->authorize('create', Knowledge::class);

        $summary = $this->importService->importFromZip($request->file('file'));

        return ApiResponse::data($summary, status: 201);
    }
}
