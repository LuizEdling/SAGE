<?php

namespace App\Enums;

enum MessageAuthor: string
{
    case Usuario = 'usuario';
    case Agente = 'agente';
    case Sistema = 'sistema';
}
