# Robo Store API PHP SDK

Composer package for integrating PHP services with the Robo Meister Store API.

```bash
composer require robo-meister/robo-store-api
```

## Requirements

- PHP 8.2 or newer
- `ext-curl`
- `ext-json`

## Quick start

```php
<?php

use RoboMeister\RoboStoreApi\ClientOptions;
use RoboMeister\RoboStoreApi\RoboStoreApiClient;

$client = new RoboStoreApiClient(new ClientOptions(
    baseUrl: 'https://store.robo-meister.com',
    apiToken: getenv('ROBO_STORE_API_TOKEN') ?: null,
));

$offers = $client->offers('FLOWBEACON');
$freeCatalog = $client->publicFreeCatalog();
$changes = $client->publicFreeCatalogChanges(new DateTimeImmutable('yesterday'));
$cachedPath = $client->downloadPublicFreePackageToFile('apps', 'inventory-tracker', '1.0.0', __DIR__ . '/cache/inventory-tracker.rappkg');

$checkoutUrl = $client->checkoutStartUrl('FLOWBEACON_LTD', [
    'owner_type' => 'organisation',
    'owner_id' => 'org_123',
    'return_url' => 'https://flowbeacon.example/billing/return',
    'idempotency_key' => 'checkout-org_123-FLOWBEACON_LTD',
]);
```

## Supported helpers

| Method | Store endpoint | Purpose |
| --- | --- | --- |
| `offers($product, $filters = [])` | `GET /api/store/offers` | Fetch public offers for a product. |
| `filteredOffers($filters = [])` | `GET /api/store/offers/filter` | Fetch locally filtered offers by product/category/tags. |
| `recommendations($context = [])` | `GET /api/store/recommendations` | Fetch compatibility recommendations. |
| `paymentProviders()` | `GET /api/store/payment-providers` | Discover enabled payment providers. |
| `activateFree($sku, $product, $options = [])` | `POST /api/store/activate-free` | Activate a `_FREE` SKU for an owner. |
| `publicFreeCatalog()` | `GET /packages/api/public-catalog/free` | List the latest public app/pack versions that are free to download. |
| `publicFreeCatalogChanges($since)` | `GET /packages/api/public-catalog/changes?since=...` | List new free public app/pack versions since an ISO-8601 date for daily cache refresh jobs. |
| `publicFreePackageDownloadUrl($channel, $name, $version)` | `GET /packages/api/public-catalog/{channel}/{name}/{version}/download` or `GET /packages/api/public-catalog/{name}/{version}/download` when channel is empty | Build a direct archive download URL. |
| `downloadPublicFreePackage($channel, $name, $version)` | `GET /packages/api/public-catalog/{channel}/{name}/{version}/download` or no-channel route when channel is empty | Download a free public app/pack archive as bytes. |
| `downloadPublicFreePackageToFile($channel, $name, $version, $destination)` | `GET /packages/api/public-catalog/{channel}/{name}/{version}/download` or no-channel route when channel is empty | Download a free public app/pack archive into a local cache path. |
| `checkoutStartUrl($sku, $options = [])` | `GET /checkout/start` | Build a browser redirect URL for paid checkout. |

Low-level `get()`, `post()`, and `buildUrl()` methods are also available for endpoints that do not yet have a typed helper.

## Publishing checklist

1. Push this directory to the public package repository used by Packagist.
2. Confirm `composer.json` has the package name `robo-meister/robo-store-api`.
3. Tag the first release, for example `v0.1.0`.
4. Submit the repository URL in Packagist and enable GitHub auto-update hooks.
5. Verify installation with `composer require robo-meister/robo-store-api:^0.1` in a clean project.

## Testing

```bash
composer install
composer test
```
