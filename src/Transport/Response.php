<?php

declare(strict_types=1);

namespace RoboMeister\RoboStoreApi\Transport;

use RoboMeister\RoboStoreApi\Exception\RoboStoreApiException;

final class Response
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly array $headers,
        private readonly string $body
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ($this->body === '') {
            return [];
        }

        $decoded = json_decode($this->body, true);
        if (!is_array($decoded)) {
            throw new RoboStoreApiException('The Store API response was not valid JSON.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
