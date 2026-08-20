<?php

namespace App\Repositories;

use App\Models\Conversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ConversationRepository extends BaseRepository
{
    protected string $modelClass = Conversation::class;

    public function find(int $id): Conversation
    {
        return $this->query()->with(['messages.knowledges'])->findOrFail($id);
    }

    /** Listagem do painel de supervisão, com o total de mensagens de cada conversa. */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()->withCount('messages')->latest('id')->paginate($perPage);
    }
}
