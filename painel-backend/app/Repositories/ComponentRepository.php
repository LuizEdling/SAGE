<?php

namespace App\Repositories;

use App\Exceptions\ResourceInUseException;
use App\Models\Component;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class ComponentRepository extends BaseRepository
{
    protected string $modelClass = Component::class;

    public function paginateWithImages(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()->with('images')->latest('id')->paginate($perPage);
    }

    public function delete(Model $model): void
    {
        if ($model->knowledges()->exists() || $model->functions()->exists()) {
            throw new ResourceInUseException(
                'Componente possui conhecimentos ou funções vinculados e não pode ser removido.'
            );
        }

        parent::delete($model);
    }
}
