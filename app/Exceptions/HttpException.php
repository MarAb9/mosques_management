<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Exception carrying an HTTP status code and a user-safe (Arabic) message.
 */
class HttpException extends Exception
{
    public function __construct(
        private readonly int $status,
        string $userMessage,
        private readonly ?string $redirectTo = null,
    ) {
        parent::__construct($userMessage);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function redirectTo(): ?string
    {
        return $this->redirectTo;
    }
}
