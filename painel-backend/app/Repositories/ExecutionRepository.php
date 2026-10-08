<?php

namespace App\Repositories;

use App\Models\Execution;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/** @extends BaseRepository<Execution> */
class ExecutionRepository extends BaseRepository
{
    protected string $modelClass = Execution::class;

    public function paginateFiltered(Request $request, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['function', 'message.conversation'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('operation_type'), fn ($q) => $q->whereHas(
                'function',
                fn ($f) => $f->where('operation_type', $request->query('operation_type'))
            ))
            ->latest('id')
            ->paginate($perPage);
    }
}
