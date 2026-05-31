<?php

declare(strict_types=1);

namespace RoboMeister\RoboStoreApi\Exception;

final class ApiException extends RoboStoreApiException
{
    /**
     * @param array<string, mixed>|null $response
     */
    public function __construct(
        private readonly int $statusCode,
        string $message,
        private readonly ?array $response = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResponse(): ?array
    {
        return $this->response;
    }
}
