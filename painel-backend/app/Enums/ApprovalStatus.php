<?php

namespace App\Enums;

enum ApprovalStatus: string
{
    case Pendente = 'pendente';
    case Aprovado = 'aprovado';
    case Reprovado = 'reprovado';
}
