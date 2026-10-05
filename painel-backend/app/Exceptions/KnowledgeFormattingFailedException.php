<?php

namespace App\Exceptions;

class KnowledgeFormattingFailedException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            errorCode: 'KNOWLEDGE_FORMATTING_FAILED',
            message: 'Não foi possível formatar o conhecimento no momento. Tente novamente.',
            status: 502,
        );
    }
}
