<?php

namespace App\Exceptions;

use RuntimeException;

class MobileApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>|null  $errors
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 403,
        public readonly ?array $errors = null,
    ) {
        parent::__construct($message);
    }
}