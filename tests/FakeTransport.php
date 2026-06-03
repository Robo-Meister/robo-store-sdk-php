<?php

declare(strict_types=1);

namespace RoboMeister\RoboStoreApi\Tests;

use RoboMeister\RoboStoreApi\Transport\Response;
use RoboMeister\RoboStoreApi\Transport\TransportInterface;

final class FakeTransport implements TransportInterface
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: string|null}> */
    public array $requests = [];

    /** @var list<Response> */
    private array $responses;

    public function __construct(Response ...$responses)
    {
        $this->responses = $responses;
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 10): Response
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];

        return array_shift($this->responses) ?? new Response(500, [], '{"error":"No fake response configured."}');
    }
}
