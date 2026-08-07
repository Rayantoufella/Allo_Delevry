<?php

namespace App\Exceptions;

class AiAnalysisException extends \RuntimeException
{
    /**
     * Create a new AI analysis exception instance.
     */
    public function __construct(string $message = 'Erreur lors de l\'analyse IA de la demande.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
