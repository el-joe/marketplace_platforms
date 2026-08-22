<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | framework. This connection is utilized if another isn't explicitly
    | specified when running a cache operation inside the application.
    |
    */

    'default' => env('CACHE_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "array", "database", "file", "memcached",
    |                    "redis", "dynamodb", "storage", "octane",
    |                    "session", "failover", "null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'storage' => [
            'driver' => 'storage',
            'disk' => env('CACHE_STORAGE_DISK'),
            'path' => env('CACHE_STORAGE_PATH', 'framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

        'failover' => [
            'driver' => 'failover',
            'stores' => [
                'database',
                'array',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, and DynamoDB cache
    | stores, there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),

    /*
    |--------------------------------------------------------------------------
    | Serializable Classes
    |--------------------------------------------------------------------------
    |
    | This value determines the classes that can be unserialized from cache
    | storage. By default, no PHP classes will be unserialized from your
    | cache to prevent gadget chain attacks if your APP_KEY is leaked.
    |
    */

    'serializable_classes' => false,

    /*
    |--------------------------------------------------------------------------
    | Collection Cache Registry
    |--------------------------------------------------------------------------
    |
    | All Cache::remember / SafeCache::remember calls that store PHP array
    | collections. Kept here for documentation and future cache-warming scripts.
    | Format: 'key_pattern' => ['ttl' => seconds, 'service' => class, 'tags' => [...]]
    |
    | Versioned caches: key includes a version counter (incremented on model save/delete).
    | Tagged caches: use Cache::tags([...]) — flush entire tag on related model change.
    |
    */

    'collection_caches' => [

        // ── Navigation / Category trees ─────────────────────────────────────
        'unified_nav_v{n}_{country_id}' => [
            'ttl'          => 600,
            'service'      => \App\Services\Customer\UnifiedCategoryService::class,
            'versioned'    => true,
            'version_key'  => 'unified_nav_cache_version',
            'flushed_by'   => ['Category', 'ClassifiedCategory', 'TravelCategory', 'TravelPackage', 'ClassifiedListing'],
            'tags'         => [],
            'description'  => 'Full nav tree with product/classified/travel sections.',
        ],

        'category_tree_v{n}_{country_id}' => [
            'ttl'          => 600,
            'service'      => \App\Services\Customer\CategoryService::class,
            'versioned'    => true,
            'version_key'  => 'category_tree_version',
            'flushed_by'   => ['Category', 'ClassifiedCategory', 'TravelCategory'],
            'tags'         => [],
            'description'  => 'Full category tree returned by GET /categories.',
        ],

        'nav_tree:{country_id}' => [
            'ttl'          => 600,
            'service'      => \App\Services\Customer\NavigationService::class,
            'versioned'    => false,
            'flushed_by'   => ['NavigationService::forgetTree'],
            'tags'         => [],
            'description'  => 'Legacy nav tree (product + classified + travel nodes).',
        ],

        // ── Page builder blocks ──────────────────────────────────────────────
        'browse_page_blocks:{type}:{country_id}:{node_id}' => [
            'ttl'          => 300,
            'service'      => \App\Services\Customer\BrowseService::class,
            'versioned'    => false,
            'flushed_by'   => ['Cache::tags([pages])->flush()'],
            'tags'         => ['pages'],
            'description'  => 'Page builder blocks for browse category pages.',
        ],

        'page_block:{block_id}:{country_id}' => [
            'ttl'          => 'dynamic (block.cache_ttl_seconds)',
            'service'      => \App\Services\Customer\PageRendererService::class,
            'versioned'    => false,
            'flushed_by'   => ['Cache::tags([pages])->flush()', 'PageBuilderService::flushPageCache'],
            'tags'         => ['pages'],
            'description'  => 'Hydrated individual page block content.',
        ],

        // ── Product detail enrichment ────────────────────────────────────────
        'product_delivery_options:{product}:{country}:{zone}:{listing}:{mode}' => [
            'ttl'          => 300,
            'service'      => \App\Services\Customer\ProductDetailEnrichmentService::class,
            'versioned'    => false,
            'flushed_by'   => ['No explicit flush — TTL only (5 min)'],
            'tags'         => [],
            'description'  => 'Available shipping options for a product + zone combination.',
        ],

        'product_coupons:v{n}:{product_id}:{country_id}:{customer_id|guest}' => [
            'ttl'          => 120,
            'service'      => \App\Services\Customer\ProductDetailEnrichmentService::class,
            'versioned'    => true,
            'version_key'  => 'product_coupons:version',
            'flushed_by'   => ['CouponObserver (saved/deleted)'],
            'tags'         => [],
            'description'  => 'Applicable coupons shown on the product detail page.',
        ],

        'category_product_count:{country_id}:{category_id}' => [
            'ttl'          => 600,
            'service'      => \App\Services\Customer\CategoryService::class,
            'versioned'    => false,
            'flushed_by'   => ['Cache::tags([categories])->flush()'],
            'tags'         => ['categories'],
            'description'  => 'Product count badge shown next to category name.',
        ],

        // ── App config ───────────────────────────────────────────────────────
        'app_config_{country_id}' => [
            'ttl'          => 300,
            'service'      => \App\Http\Controllers\Customer\AppConfigController::class,
            'versioned'    => false,
            'flushed_by'   => ['Admin\AnnouncementBarController on save/delete'],
            'tags'         => [],
            'description'  => 'Flutter app config: contexts, nav items, announcement bar.',
        ],

        // ── Payment gateways ─────────────────────────────────────────────────
        'payment_gateways:{country_id}' => [
            'ttl'          => 'No TTL — forget() on change',
            'service'      => \App\Http\Controllers\Customer\CheckoutController::class,
            'versioned'    => false,
            'flushed_by'   => ['Admin\PaymentGatewayController on update/delete/toggle'],
            'tags'         => [],
            'description'  => 'Active payment gateways for a country (checkout).',
        ],

        // ── Similar classified listings ─────────────────────────────────────
        'similar_classified:{listing_id}' => [
            'ttl'          => 300,
            'service'      => \App\Http\Controllers\Customer\ListingController::class,
            'versioned'    => false,
            'flushed_by'   => ['No explicit flush — TTL only (5 min)'],
            'tags'         => [],
            'description'  => 'Similar classified listings for the detail page carousel.',
        ],

    ],

];
