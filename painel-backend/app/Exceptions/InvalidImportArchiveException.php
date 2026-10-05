<?php

namespace App\Exceptions;

class InvalidImportArchiveException extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct(
            errorCode: 'INVALID_IMPORT_ARCHIVE',
            message: $message,
            status: 422,
        );
    }
}
