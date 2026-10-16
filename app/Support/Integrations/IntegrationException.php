<?php

namespace App\Support\Integrations;

use RuntimeException;
use Throwable;

class IntegrationException extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly bool $retryable = false,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function permanent(string $message): self
    {
        return new self($message, 0, null, false);
    }

    public static function temporary(string $message): self
    {
        return new self($message, 0, null, true);
    }
}
