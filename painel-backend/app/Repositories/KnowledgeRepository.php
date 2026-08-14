<?php

namespace App\Repositories;

use App\Models\Knowledge;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class KnowledgeRepository extends BaseRepository
{
    protected string $modelClass = Knowledge::class;

    public function paginateFiltered(Request $request, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['component', 'approver', 'images'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('component_id'), fn ($q) => $q->where('component_id', $request->query('component_id')))
            ->when($request->filled('q'), fn ($q) => $q->where('content', 'like', '%'.$request->query('q').'%'))
            ->latest('id')
            ->paginate($perPage);
    }

    public function find(int $id): Knowledge
    {
        return $this->query()->with(['component', 'approver', 'images'])->findOrFail($id);
    }

    /** @return array{pendentes: int, aprovados: int, indexados: int} */
    public function pipelineCounts(): array
    {
        return [
            'pendentes' => $this->query()->where('status', 'pendente')->count(),
            'aprovados' => $this->query()->where('status', 'aprovado')->count(),
            'indexados' => $this->query()->whereNotNull('qdrant_point_id')->count(),
        ];
    }
}
