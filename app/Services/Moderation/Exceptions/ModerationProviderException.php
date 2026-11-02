<?php

namespace App\Services\Moderation\Exceptions;

use RuntimeException;

class ModerationProviderException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorType = 'provider_error',
        public readonly ?string $providerCode = null,
        public readonly bool $retryable = false,
        public readonly int $operations = 0,
        public readonly ?string $requestId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    public function isUsageLimit(): bool
    {
        return $this->errorType === 'usage_limit';
    }

    public function isProviderCapacity(): bool
    {
        return $this->errorType === 'provider_capacity';
    }

    public function requiresAction(): bool
    {
        return in_array($this->errorType, [
            'plan_error',
            'credentials_error',
            'configuration_error',
        ], true);
    }
}
