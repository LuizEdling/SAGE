<?php

namespace App\Transformers;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

abstract class Transformer
{
    /** @return array<string, mixed> */
    abstract public function transform(Model $model): array;

    public function item(Model $model): array
    {
        return $this->transform($model);
    }

    /** @param  Collection<int, Model>  $items */
    public function collection(Collection $items): array
    {
        return $items->map(fn (Model $m) => $this->transform($m))->all();
    }

    /** @return array{data: array, meta?: array} */
    public function paginated(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => collect($paginator->items())->map(fn (Model $m) => $this->transform($m))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
