<?php

declare(strict_types=1);

namespace RoboMeister\RoboStoreApi;

final class ClientOptions
{
    public function __construct(
        private readonly string $baseUrl = 'https://store.robo-meister.com',
        private readonly ?string $apiToken = null,
        private readonly int $timeoutSeconds = 10,
        private readonly string $userAgent = 'robo-store-api-php/0.1'
    ) {
    }

    public function getBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    public function getApiToken(): ?string
    {
        return $this->apiToken;
    }

    public function getTimeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }
}
