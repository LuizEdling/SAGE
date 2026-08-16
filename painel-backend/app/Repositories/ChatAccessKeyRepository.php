<?php

namespace App\Repositories;

use App\Exceptions\ResourceInUseException;
use App\Models\ChatAccessKey;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class ChatAccessKeyRepository extends BaseRepository
{
    protected string $modelClass = ChatAccessKey::class;

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()->with('createdBy')->withCount('conversations')->latest('id')->paginate($perPage);
    }

    /**
     * As conversas guardam a chave pela qual chegaram, e o histórico não pode
     * ser perdido: chave já usada não é removida, só desativada.
     */
    public function delete(Model $model): void
    {
        $conversas = $model->conversations()->count();

        if ($conversas > 0) {
            throw new ResourceInUseException(
                "Esta chave tem {$conversas} conversa(s) no histórico e não pode ser removida. Desative-a para bloquear o acesso."
            );
        }

        parent::delete($model);
    }
}
