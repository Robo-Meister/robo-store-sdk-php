<?php

declare(strict_types=1);

namespace RoboMeister\RoboStoreApi;

use RoboMeister\RoboStoreApi\Exception\ApiException;
use RoboMeister\RoboStoreApi\Exception\RoboStoreApiException;
use RoboMeister\RoboStoreApi\Transport\CurlTransport;
use RoboMeister\RoboStoreApi\Transport\TransportInterface;

final class RoboStoreApiClient
{
    private readonly ClientOptions $options;
    private readonly TransportInterface $transport;

    public function __construct(?ClientOptions $options = null, ?TransportInterface $transport = null)
    {
        $this->options = $options ?? new ClientOptions();
        $this->transport = $transport ?? new CurlTransport();
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $filters
     * @return array<string, mixed>
     */
    public function offers(string $product, array $filters = []): array
    {
        return $this->get('/api/store/offers', ['product' => $product] + $filters);
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $filters
     * @return array<string, mixed>
     */
    public function filteredOffers(array $filters = []): array
    {
        return $this->get('/api/store/offers/filter', $filters);
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $context
     * @return array<string, mixed>
     */
    public function recommendations(array $context = []): array
    {
        return $this->get('/api/store/recommendations', $context);
    }

    /**
     * Browse Store listings, including storefront price labels and relationship links.
     * This is discovery, not installation eligibility or workspace recommendations.
     *
     * @return array<string, mixed>
     */
    public function discoverPackages(int $page = 1, int $limit = 12): array
    {
        if ($page < 1 || $limit < 1 || $limit > 24) {
            throw new \InvalidArgumentException('Page must be positive and limit must be between 1 and 24.');
        }

        return $this->get('/packages/api/discover', ['page' => $page, 'limit' => $limit]);
    }

    /**
     * Read publisher-authored relationships for a numeric Store catalog record.
     * Missing, withdrawn and non-public source listings return the API's 404.
     * Payload is preserved, including unresolved dependencies and future fields.
     *
     * @return array<string, mixed>
     */
    public function packageRelationships(int $packageId): array
    {
        $this->assertCatalogId($packageId);

        return $this->get('/packages/catalog/' . $packageId . '/relationships');
    }

    /** Build an informational browser URL; does not install, activate or purchase. */
    public function packageDetailsUrl(int $packageId): string
    {
        $this->assertCatalogId($packageId);

        return $this->buildUrl('/packages/catalog/' . $packageId);
    }

    private function assertCatalogId(int $packageId): void
    {
        if ($packageId < 1) {
            throw new \InvalidArgumentException('A positive numeric Store catalog ID is required.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentProviders(): array
    {
        return $this->get('/api/store/payment-providers');
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public function activateFree(string $sku, string $product, array $options = []): array
    {
        return $this->post('/api/store/activate-free', ['sku' => $sku, 'product' => $product] + $options);
    }


    /**
     * List the latest public app/pack versions that are free to download.
     *
     * @return array<string, mixed>
     */
    public function publicFreeCatalog(): array
    {
        return $this->get('/packages/api/public-catalog/free');
    }

    /**
     * List free public app/pack versions published after an ISO-8601 date.
     *
     * @return array<string, mixed>
     */
    public function publicFreeCatalogChanges(string|\DateTimeInterface $since): array
    {
        if ($since instanceof \DateTimeInterface) {
            $since = $since->format(DATE_ATOM);
        }

        return $this->get('/packages/api/public-catalog/changes', ['since' => $since]);
    }

    /**
     * Build the direct download URL for a free public app/pack version.
     */
    public function publicFreePackageDownloadUrl(string $channel, string $name, string $version): string
    {
        $path = $channel === ''
            ? sprintf(
                '/packages/api/public-catalog/%s/%s/download',
                rawurlencode($name),
                rawurlencode($version)
            )
            : sprintf(
                '/packages/api/public-catalog/%s/%s/%s/download',
                rawurlencode($channel),
                rawurlencode($name),
                rawurlencode($version)
            );

        return $this->buildUrl($path);
    }

    /**
     * Download a free public app/pack version and return its raw archive bytes.
     */
    public function downloadPublicFreePackage(string $channel, string $name, string $version): string
    {
        $response = $this->transport->request(
            'GET',
            $this->publicFreePackageDownloadUrl($channel, $name, $version),
            $this->headers(['Accept' => 'application/octet-stream']),
            null,
            $this->options->getTimeoutSeconds()
        );

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            return $response->getBody();
        }

        throw new ApiException($response->getStatusCode(), 'Store API package download failed.', []);
    }

    /**
     * Download a free public app/pack version into a local cache file.
     */
    public function downloadPublicFreePackageToFile(string $channel, string $name, string $version, string $destination): string
    {
        $bytes = $this->downloadPublicFreePackage($channel, $name, $version);
        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RoboStoreApiException(sprintf('Unable to create cache directory "%s".', $directory));
        }

        if (file_put_contents($destination, $bytes) === false) {
            throw new RoboStoreApiException(sprintf('Unable to write package cache file "%s".', $destination));
        }

        return $destination;
    }

    /**
     * @param array<string, scalar|null> $options
     */
    public function checkoutStartUrl(string $sku, array $options = []): string
    {
        return $this->buildUrl('/checkout/start', ['sku' => $sku] + $options);
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        $response = $this->transport->request(
            'GET',
            $this->buildUrl($path, $query),
            $this->headers(),
            null,
            $this->options->getTimeoutSeconds()
        );

        return $this->decodeResponse($response->getStatusCode(), $response->json());
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload = []): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $response = $this->transport->request(
            'POST',
            $this->buildUrl($path),
            $this->headers(['Content-Type' => 'application/json']),
            $body,
            $this->options->getTimeoutSeconds()
        );

        return $this->decodeResponse($response->getStatusCode(), $response->json());
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     */
    public function buildUrl(string $path, array $query = []): string
    {
        $path = '/' . ltrim($path, '/');
        $url = $this->options->getBaseUrl() . $path;
        $query = $this->normalizeQuery($query);

        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }

        return $url;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function headers(array $headers = []): array
    {
        $defaultHeaders = [
            'Accept' => 'application/json',
            'User-Agent' => $this->options->getUserAgent(),
        ];

        $apiToken = $this->options->getApiToken();
        if ($apiToken !== null && trim($apiToken) !== '') {
            $defaultHeaders['Authorization'] = sprintf('Bearer %s', $apiToken);
        }

        return $headers + $defaultHeaders;
    }

    /**
     * @param array<string, scalar|list<scalar>|null> $query
     * @return array<string, scalar|list<scalar>>
     */
    private function normalizeQuery(array $query): array
    {
        $normalized = [];
        foreach ($query as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $items = array_values(array_filter($value, static fn ($item): bool => is_scalar($item) && (string) $item !== ''));
                if ($items === []) {
                    continue;
                }
                $normalized[$key] = $items;
                continue;
            }

            if (!is_scalar($value)) {
                throw new RoboStoreApiException(sprintf('Query parameter "%s" must be scalar or a list of scalars.', $key));
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function decodeResponse(int $statusCode, array $payload): array
    {
        if ($statusCode >= 200 && $statusCode < 300) {
            return $payload;
        }

        $message = 'Store API request failed.';
        if (isset($payload['error']) && is_scalar($payload['error'])) {
            $message = (string) $payload['error'];
        } elseif (isset($payload['message']) && is_scalar($payload['message'])) {
            $message = (string) $payload['message'];
        }

        throw new ApiException($statusCode, $message, $payload);
    }
}

