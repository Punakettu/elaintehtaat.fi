<?php

/**
 * @file
 * Drupal settings for the Docker Compose environment.
 *
 * Everything environment-specific comes from environment variables set in
 * docker-compose.yml / .env. Put personal overrides in settings.local.php
 * (git-ignored).
 */

// --- Database ---------------------------------------------------------------
$databases['default']['default'] = [
  'driver' => 'mysql',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
  'host' => getenv('MYSQL_HOST') ?: 'mysql',
  'port' => getenv('MYSQL_PORT') ?: 3306,
  'database' => getenv('MYSQL_DATABASE') ?: 'drupal',
  'username' => getenv('MYSQL_USER') ?: 'drupal',
  'password' => getenv('MYSQL_PASSWORD') ?: 'drupal',
  'prefix' => '',
  'isolation_level' => 'READ COMMITTED',
];

// --- Core settings -----------------------------------------------------------
$settings['hash_salt'] = getenv('DRUPAL_HASH_SALT') ?: 'insecure-default-hash-salt';
$settings['update_free_access'] = FALSE;
$settings['config_sync_directory'] = '../config/sync';
$settings['file_public_path'] = 'sites/default/files';
$settings['file_private_path'] = '../private';
$settings['file_temp_path'] = '/tmp';
// Drupal 11.4+ defaults translations:// to public://translations, which is an
// S3 bucket here (see s3fs below) and cannot be used by the local-only
// translations stream wrapper. Keep .po files on local disk, outside webroot.
$settings['locale_translation_path'] = '../private/translations';
$settings['container_yamls'][] = $app_root . '/' . $site_path . '/services.yml';
$settings['file_scan_ignore_directories'] = ['node_modules', 'bower_components'];
$settings['entity_update_batch_size'] = 50;
$settings['entity_update_backup'] = TRUE;
$settings['migrate_node_migrate_type_classic'] = FALSE;
$settings['state_cache'] = TRUE;

$settings['trusted_host_patterns'] = [
  '^localhost$',
  '^127\.0\.0\.1$',
  '^nginx$',
  '^php$',
];

// --- Reverse proxy (Varnish) -------------------------------------------------
// Only Varnish (and nginx on the debug port) can reach PHP inside the Docker
// network, so trusting the immediate upstream is safe here.
$settings['reverse_proxy'] = TRUE;
$settings['reverse_proxy_addresses'] = [$_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'];
$settings['reverse_proxy_trusted_headers'] =
  \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_FOR
  | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_HOST
  | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PORT
  | \Symfony\Component\HttpFoundation\Request::HEADER_X_FORWARDED_PROTO;
// Let Varnish cache anonymous pages without a Vary: Cookie header.
$settings['omit_vary_cookie'] = TRUE;

// --- Redis -------------------------------------------------------------------
// The redis module can serve as cache backend even before it is enabled, as
// long as its services file is loaded here. Skipped during installation.
if (extension_loaded('redis')
  && file_exists($app_root . '/modules/contrib/redis/redis.services.yml')
  && !\Drupal\Core\Installer\InstallerKernel::installationAttempted()) {

  $settings['redis.connection']['interface'] = 'PhpRedis';
  $settings['redis.connection']['host'] = getenv('REDIS_HOST') ?: 'redis';
  $settings['redis.connection']['port'] = getenv('REDIS_PORT') ?: 6379;
  $settings['redis.connection']['base'] = 0;

  $settings['cache']['default'] = 'cache.backend.redis';
  $settings['cache_prefix']['default'] = 'elaintehtaat';

  // Keep the form cache in the database: it must never be evicted.
  $settings['cache']['bins']['form'] = 'cache.backend.database';

  $settings['container_yamls'][] = 'modules/contrib/redis/example.services.yml';
  $settings['container_yamls'][] = 'modules/contrib/redis/redis.services.yml';

  // Store the compiled container itself in Redis so bootstrap skips the DB.
  $settings['bootstrap_container_definition'] = [
    'parameters' => [],
    'services' => [
      'redis.factory' => [
        'class' => 'Drupal\redis\ClientFactory',
      ],
      'cache.backend.redis' => [
        'class' => 'Drupal\redis\Cache\CacheBackendFactory',
        'arguments' => ['@redis.factory', '@cache_tags_provider.container', '@serialization.phpserialize'],
      ],
      'cache.container' => [
        'class' => '\Drupal\redis\Cache\PhpRedis',
        'factory' => ['@cache.backend.redis', 'get'],
        'arguments' => ['container'],
      ],
      'cache_tags_provider.container' => [
        'class' => 'Drupal\redis\Cache\RedisCacheTagsChecksum',
        'arguments' => ['@redis.factory'],
      ],
      'serialization.phpserialize' => [
        'class' => 'Drupal\Component\Serialization\PhpSerialize',
      ],
    ],
  ];
}

// --- S3 file storage (RustFS via drupal/s3fs) ---------------------------------
// public:// is stored in the S3 bucket; private:// stays on local disk.
// PHP talks to the endpoint inside the Docker network (S3_ENDPOINT), while
// the URLs handed to browsers use S3_PUBLIC_HOST.
$settings['s3fs.use_s3_for_public'] = TRUE;
$settings['s3fs.use_s3_for_private'] = FALSE;
$settings['s3fs.access_key'] = getenv('S3_ACCESS_KEY') ?: '';
$settings['s3fs.secret_key'] = getenv('S3_SECRET_KEY') ?: '';

$config['s3fs.settings']['bucket'] = getenv('S3_BUCKET') ?: 'drupal-files';
$config['s3fs.settings']['region'] = getenv('S3_REGION') ?: 'us-east-1';
$config['s3fs.settings']['use_customhost'] = TRUE;
$config['s3fs.settings']['hostname'] = getenv('S3_ENDPOINT') ?: 'rustfs:9000';
$config['s3fs.settings']['use_path_style_endpoint'] = TRUE;
$config['s3fs.settings']['use_https'] = FALSE;
$config['s3fs.settings']['use_cname'] = TRUE;
$config['s3fs.settings']['domain'] = getenv('S3_PUBLIC_HOST') ?: 'localhost:9000';
$config['s3fs.settings']['domain_root'] = 'none';
$config['s3fs.settings']['disable_version_sync'] = TRUE;

// --- CI ----------------------------------------------------------------------
// GitHub Actions sets CI=true.
if (getenv('CI')) {
  include $app_root . '/' . $site_path . '/settings.ci.php';
}

// --- Local overrides ---------------------------------------------------------
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
