<?php

// The test process must never inherit Docker's development database settings.
// Set every environment adapter before Laravel or Dotenv is loaded.
$settings = [
    'APP_ENV' => 'testing',
    'APP_DEBUG' => 'false',
    'APP_CONFIG_CACHE' => __DIR__ . '/../storage/framework/testing-config.php',
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST' => 'bookly-test-postgres',
    'DB_PORT' => '5432',
    'DB_DATABASE' => 'bookly_test',
    'DB_USERNAME' => 'bookly_test',
    'DB_PASSWORD' => '',
    'DB_URL' => '',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'MAIL_MAILER' => 'array',
    'PAYMENT_GATEWAY' => 'stripe',
    'SCOUT_DRIVER' => 'collection',
    'SCOUT_PREFIX' => 'bookly_test_',
    'MEILISEARCH_HOST' => 'http://bookly-test-meilisearch:7700',
    'MEILISEARCH_KEY' => 'test-key',
    'SCOUT_QUEUE_CONNECTION' => 'sync',
    'SCOUT_QUEUE' => 'default',
];
foreach ($settings as $name => $value) {
    putenv($name . '=' . $value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}

// Separate suite invocations may not share the base database. ParaTest workers
// carry TEST_TOKEN and Laravel gives each worker its own suffixed database.
if (empty($_SERVER['TEST_TOKEN'])) {
    $testDatabaseLock = fopen(__DIR__ . '/../storage/framework/testing-database.lock', 'c');
    if ($testDatabaseLock === false || ! flock($testDatabaseLock, LOCK_EX | LOCK_NB)) {
        throw new RuntimeException('Another test suite owns the disposable test database.');
    }
}

require __DIR__ . '/../vendor/autoload.php';
