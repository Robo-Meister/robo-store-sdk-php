<?php

declare(strict_types=1);

namespace RoboMeister\RoboStoreApi\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoboMeister\RoboStoreApi\ClientOptions;
use RoboMeister\RoboStoreApi\Exception\ApiException;
use RoboMeister\RoboStoreApi\RoboStoreApiClient;
use RoboMeister\RoboStoreApi\Transport\Response;

final class PackageDiscoveryTest extends TestCase
{
    public function testDiscoveryAndRelationshipsPreservePayloadUsingExistingTransport(): void
    {
        $relationships = ['packageId' => 42, 'relationships' => [
            'required' => [['name' => 'base', 'status' => 'unresolved', 'listing' => null]],
            'recommended' => [['name' => 'free', 'status' => 'public_listing', 'listing' => ['isFree' => true, 'priceLabel' => 'Free package']]],
            'optional' => [['name' => 'paid', 'status' => 'public_listing', 'listing' => ['isFree' => false, 'priceLabel' => '120 credits']]],
            'alternatives' => [],
        ], 'future_field' => ['preserved' => true]];
        $catalog = ['packages' => [['id' => 42]], 'pagination' => ['hasMore' => true, 'nextPage' => 3]];
        $transport = new FakeTransport(
            new Response(200, [], json_encode($catalog, JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode($relationships, JSON_THROW_ON_ERROR))
        );
        $client = new RoboStoreApiClient(new ClientOptions('https://store.example/prefix', 'test-token'), $transport);
        self::assertSame($catalog, $client->discoverPackages(2, 24));
        self::assertSame($relationships, $client->packageRelationships(42));
        self::assertSame('https://store.example/prefix/packages/api/discover?page=2&limit=24', $transport->requests[0]['url']);
        self::assertSame('https://store.example/prefix/packages/catalog/42/relationships', $transport->requests[1]['url']);
        foreach ($transport->requests as $request) {
            self::assertSame('GET', $request['method']);
            self::assertNull($request['body']);
            self::assertSame('Bearer test-token', $request['headers']['Authorization']);
        }
        self::assertSame('https://store.example/prefix/packages/catalog/42', $client->packageDetailsUrl(42));
        self::assertCount(2, $transport->requests);
    }

    #[DataProvider('invalidArguments')]
    public function testRejectsInvalidArgumentsBeforeTransport(string $method, array $args): void
    {
        $transport = new FakeTransport();
        $client = new RoboStoreApiClient(null, $transport);
        try {
            $client->$method(...$args);
            self::fail('Invalid argument accepted');
        } catch (\InvalidArgumentException) {
            self::assertSame([], $transport->requests);
        }
    }

    public static function invalidArguments(): iterable
    {
        yield ['discoverPackages', [0, 12]];
        yield ['discoverPackages', [1, 0]];
        yield ['discoverPackages', [1, 25]];
        yield ['packageRelationships', [0]];
        yield ['packageRelationships', [-1]];
        yield ['packageDetailsUrl', [0]];
    }

    public function testPublicRequestDoesNotRequireTokenOrHideApiFailure(): void
    {
        $transport = new FakeTransport(new Response(404, [], '{"error":"Extension not found."}'));
        $client = new RoboStoreApiClient(new ClientOptions('https://store.example'), $transport);
        try {
            $client->packageRelationships(42);
            self::fail('404 was swallowed');
        } catch (ApiException $error) {
            self::assertSame(404, $error->getStatusCode());
            self::assertSame('Extension not found.', $error->getMessage());
            self::assertArrayNotHasKey('Authorization', $transport->requests[0]['headers']);
        }
    }
}
