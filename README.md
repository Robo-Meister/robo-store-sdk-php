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
| `discoverPackages($page = 1, $limit = 12)` | `GET /packages/api/discover` | Browse storefront listings with pagination (limit 1–24). |
| `packageRelationships($packageId)` | `GET /packages/catalog/{id}/relationships` | Read public publisher-authored dependencies, companions and alternatives. |
| `packageDetailsUrl($packageId)` | `/packages/catalog/{id}` | Build an informational browser URL without making a request. |
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


## Extension discovery and Command Center integration

Requires Store PRs #565 and #567 deployed. Existing `recommendations()` remains a
compatibility call to the deprecated Store proxy. Never call that proxy from
Connector recommendations: it can proxy back to Connector.

Use a public client (no token required) to read publisher suggestions:

```php
$catalog = $client->discoverPackages(page: 1, limit: 12);
foreach ($catalog['packages'] ?? [] as $package) {
    // Numeric Store record ID, not an organisation ID, package name or SKU.
    $packageId = (int) $package['id'];
    $relations = $client->packageRelationships($packageId);
    $detailsUrl = $client->packageDetailsUrl($packageId);
}
// Fetch the next page only when pagination.hasMore is true.
```

Fetch relationships on demand; do not synchronously fetch all pages and all related
packages for each Command Center render. These helpers reuse the SDK transport,
base URL, timeout, authorization and error handling. They do not follow arbitrary
URLs from metadata and make no installation, checkout or activation requests.

`relationships` has `required`, `recommended`, `optional` and `alternatives`
arrays. Each item has `name`, `status` and `listing`. An unresolved dependency
has `status: unresolved` and `listing: null`. A resolved item has
`status: public_listing` and a listing with `title`, `summary`, `priceLabel`,
`isFree`, `typeLabel`, `detailsUrl`. All payload fields are preserved.

`isFree` and the price label describe the catalog package only. They do not prove
that prerequisites or external usage are free, that an organisation owns a license,
or that the current workspace can install it. Preserve unresolved requirements;
do not interpret an empty result or 404 as an entitlement decision.

Command Center's canonical `WorkspaceExpansionRecommendationReader` currently
uses local catalogue sources and actor-bound installation evidence. This SDK PR
does not change that behavior. A subsequent Connector adapter must reconcile exact
package identity/version, publication, audience and canonical installed-state facts
through that mechanism before displaying personalized suggestions. The public
Store relationship response alone is not that proof.

Release this SDK change through the normal Packagist tag process, then update
consumers' composer.json constraints and composer.lock to the released version.
No release tag or consumer lockfile change is included here; do not use an
unreleased development branch in production.
