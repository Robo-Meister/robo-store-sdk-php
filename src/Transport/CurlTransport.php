<?php

declare(strict_types=1);

namespace RoboMeister\RoboStoreApi\Transport;

use RoboMeister\RoboStoreApi\Exception\RoboStoreApiException;

final class CurlTransport implements TransportInterface
{
    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 10): Response
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new RoboStoreApiException('Unable to initialise cURL.');
        }

        $responseHeaders = [];
        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = sprintf('%s: %s', $name, $value);
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);
                $line = trim($line);
                if ($line === '' || !str_contains($line, ':')) {
                    return $length;
                }

                [$name, $value] = explode(':', $line, 2);
                $name = strtolower(trim($name));
                $responseHeaders[$name][] = trim($value);

                return $length;
            },
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($handle);
        if ($responseBody === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new RoboStoreApiException(sprintf('Store API request failed: %s', $error));
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new Response($statusCode, $responseHeaders, (string) $responseBody);
    }
}
