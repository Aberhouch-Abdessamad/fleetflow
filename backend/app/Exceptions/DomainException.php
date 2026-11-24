<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business-rule violation. Rendered as a 422 (or the given status) with a
 * stable machine-readable code so the frontend can react precisely.
 */
class DomainException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'domain_error',
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
