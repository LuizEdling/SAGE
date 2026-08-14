<?php

namespace App\Repositories;

use App\Models\AgentFunction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class AgentFunctionRepository extends BaseRepository
{
    protected string $modelClass = AgentFunction::class;

    public function paginateFiltered(Request $request, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['component', 'approver', 'parameters'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('component_id'), fn ($q) => $q->where('component_id', $request->query('component_id')))
            ->latest('id')
            ->paginate($perPage);
    }

    public function find(int $id): AgentFunction
    {
        return $this->query()->with(['component', 'approver', 'parameters'])->findOrFail($id);
    }
}
