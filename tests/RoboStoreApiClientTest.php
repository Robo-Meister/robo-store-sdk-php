<?php

declare(strict_types=1);

namespace RoboMeister\RoboStoreApi\Tests;

use PHPUnit\Framework\TestCase;
use RoboMeister\RoboStoreApi\ClientOptions;
use RoboMeister\RoboStoreApi\Exception\ApiException;
use RoboMeister\RoboStoreApi\RoboStoreApiClient;
use RoboMeister\RoboStoreApi\Transport\Response;

final class RoboStoreApiClientTest extends TestCase
{
    public function testOffersBuildsStoreOffersRequest(): void
    {
        $transport = new FakeTransport(new Response(200, [], '{"offers":[]}'));
        $client = new RoboStoreApiClient(new ClientOptions('https://store.example', 'token-123'), $transport);

        $payload = $client->offers('FLOWBEACON', ['type' => 'subscription', 'tags' => ['ai', 'ops']]);

        $this->assertSame(['offers' => []], $payload);
        $this->assertCount(1, $transport->requests);
        $this->assertSame('GET', $transport->requests[0]['method']);
        $this->assertStringStartsWith('https://store.example/api/store/offers?', $transport->requests[0]['url']);
        $this->assertStringContainsString('product=FLOWBEACON', $transport->requests[0]['url']);
        $this->assertStringContainsString('type=subscription', $transport->requests[0]['url']);
        $this->assertSame('Bearer token-123', $transport->requests[0]['headers']['Authorization']);
    }

    public function testActivateFreePostsJsonPayload(): void
    {
        $transport = new FakeTransport(new Response(200, [], '{"status":"activated"}'));
        $client = new RoboStoreApiClient(new ClientOptions('https://store.example'), $transport);

        $payload = $client->activateFree('FLOWBEACON_FREE', 'FLOWBEACON', [
            'owner_type' => 'organisation',
            'owner_id' => 'org-123',
            'idempotency_key' => 'idem-123',
        ]);

        $this->assertSame(['status' => 'activated'], $payload);
        $this->assertSame('POST', $transport->requests[0]['method']);
        $this->assertSame('https://store.example/api/store/activate-free', $transport->requests[0]['url']);
        $this->assertSame('application/json', $transport->requests[0]['headers']['Content-Type']);
        $this->assertJsonStringEqualsJsonString(
            '{"sku":"FLOWBEACON_FREE","product":"FLOWBEACON","owner_type":"organisation","owner_id":"org-123","idempotency_key":"idem-123"}',
            (string) $transport->requests[0]['body']
        );
    }

    public function testCheckoutStartUrlBuildsBrowserRedirectUrl(): void
    {
        $client = new RoboStoreApiClient(new ClientOptions('https://store.example/'));

        $url = $client->checkoutStartUrl('FLOWBEACON_LTD', [
            'return_url' => 'https://app.example/billing/return',
            'idempotency_key' => 'idem-123',
        ]);

        $this->assertSame(
            'https://store.example/checkout/start?sku=FLOWBEACON_LTD&return_url=https%3A%2F%2Fapp.example%2Fbilling%2Freturn&idempotency_key=idem-123',
            $url
        );
    }

    public function testApiErrorsThrowApiException(): void
    {
        $transport = new FakeTransport(new Response(400, [], '{"error":"product is required."}'));
        $client = new RoboStoreApiClient(new ClientOptions('https://store.example'), $transport);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('product is required.');

        $client->get('/api/store/offers');
    }
}
