<?php

namespace App\Enums;

enum ExecutionStatus: string
{
    case Pendente = 'pendente';
    case Autorizada = 'autorizada';
    case Executada = 'executada';
    case Negada = 'negada';
    case Falhou = 'falhou';
}
