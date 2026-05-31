<?php

declare(strict_types=1);

namespace RoboMeister\RoboStoreApi\Transport;

interface TransportInterface
{
    /**
     * @param array<string, string> $headers
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 10): Response;
}
