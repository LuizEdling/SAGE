<?php

namespace App\Exceptions;

use Exception;

abstract class DomainException extends Exception
{
    public function __construct(
        protected string $errorCode,
        string $message,
        protected int $status = 422,
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function status(): int
    {
        return $this->status;
    }
}
