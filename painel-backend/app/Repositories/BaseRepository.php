<?php

namespace App\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
abstract class BaseRepository
{
    /** @var class-string<TModel> */
    protected string $modelClass;

    protected function query(): Builder
    {
        return $this->modelClass::query();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()->latest('id')->paginate($perPage);
    }

    public function find(int $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Model
    {
        // refresh() garante que defaults definidos só na migration (ex: status
        // = 'pendente') fiquem disponíveis no Model em memória logo após o insert.
        return $this->modelClass::create($attributes)->refresh();
    }

    /** @param  array<string, mixed>  $attributes */
    public function update(Model $model, array $attributes): Model
    {
        $model->fill($attributes);
        $model->save();

        return $model;
    }

    public function delete(Model $model): void
    {
        $model->delete();
    }
}
