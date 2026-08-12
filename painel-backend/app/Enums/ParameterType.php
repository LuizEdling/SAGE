<?php

namespace App\Enums;

enum ParameterType: string
{
    case String = 'string';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Date = 'date';
    case Float = 'float';
}
