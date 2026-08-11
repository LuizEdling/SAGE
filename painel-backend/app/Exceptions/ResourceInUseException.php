<?php

namespace App\Exceptions;

class ResourceInUseException extends DomainException
{
    public function __construct(string $message = 'Recurso está em uso e não pode ser removido.')
    {
        parent::__construct(
            errorCode: 'RESOURCE_IN_USE',
            message: $message,
            status: 422,
        );
    }
}
