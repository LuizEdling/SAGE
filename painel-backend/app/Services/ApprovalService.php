<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Knowledge;
use App\Models\User;
use App\Services\Knowledge\IngestionDispatcherInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Máquina de estados de aprovação, aplicável a Knowledge e AgentFunction
 * (o TCC §4.4 diz que gestores aprovam/reprovam conhecimento E funções).
 */
class ApprovalService
{
    public function __construct(
        private readonly IngestionDispatcherInterface $ingestionDispatcher,
    ) {}

    /**
     * @param  Model  $item  precisa ter as colunas status/approved_by/approved_at
     */
    public function approve(Model $item, User $approver): Model
    {
        $this->assertTransition($item, ApprovalStatus::Aprovado);

        DB::transaction(function () use ($item, $approver) {
            $item->status = ApprovalStatus::Aprovado;
            $item->approved_by = $approver->id;
            $item->approved_at = now();
            $item->save();
        });

        if ($item instanceof Knowledge) {
            $this->ingestionDispatcher->dispatch($item);
        }

        return $item->refresh();
    }

    public function reject(Model $item, User $approver): Model
    {
        $this->assertTransition($item, ApprovalStatus::Reprovado);

        $item->status = ApprovalStatus::Reprovado;
        $item->approved_by = $approver->id;
        $item->approved_at = now();
        $item->save();

        return $item->refresh();
    }

    /**
     * Editar o conteúdo de um item já decidido volta ele para "pendente" e
     * limpa a aprovação anterior — precisa passar por aprovação de novo.
     */
    public function resetToPendingIfDecided(Model $item): void
    {
        if ($item->status !== ApprovalStatus::Pendente) {
            $item->status = ApprovalStatus::Pendente;
            $item->approved_by = null;
            $item->approved_at = null;
        }
    }

    private function assertTransition(Model $item, ApprovalStatus $to): void
    {
        if ($item->status !== ApprovalStatus::Pendente) {
            throw new InvalidStatusTransitionException($item->status->value, $to->value);
        }
    }
}
