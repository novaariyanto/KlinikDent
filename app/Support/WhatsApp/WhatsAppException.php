<?php

namespace App\Support\WhatsApp;

use RuntimeException;

class WhatsAppException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly bool $retryable = false,
        public readonly mixed $response = null,
    ) {
        parent::__construct($message);
    }
}
