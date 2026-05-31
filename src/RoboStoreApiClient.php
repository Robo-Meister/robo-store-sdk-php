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
