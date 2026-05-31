<?php

declare(strict_types=1);

namespace RoboMeister\RoboStoreApi\Tests;

use RoboMeister\RoboStoreApi\Transport\Response;
use RoboMeister\RoboStoreApi\Transport\TransportInterface;

final class FakeTransport implements TransportInterface
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string|null}> */
    public array $requests = [];

    public function __construct(private readonly Response $response)
    {
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 10): Response
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        return $this->response;
    }
}
