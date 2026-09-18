<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = require dirname(__DIR__) . '/bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            $config = $app['config'];
            $connection = $config->get('database.connections.pgsql');
            if ($app->configurationIsCached()
                || $config->get('app.env') !== 'testing'
                || $config->get('database.default') !== 'pgsql'
                || $connection['host'] !== 'bookly-test-postgres'
                || $connection['database'] !== 'bookly_test'
                || $connection['username'] !== 'bookly_test'
                || ! empty($connection['url'])) {
                throw new RuntimeException('Unsafe test database configuration: refusing to boot or migrate. Use tests/bootstrap.php and the disposable test PostgreSQL service.');
            }
        });
        $app->make(Kernel::class)->bootstrap();
        $database = $app['db']->connection()->selectOne('SELECT current_database() AS name, current_user AS username');
        if ($database->name !== 'bookly_test' || $database->username !== 'bookly_test') {
            throw new RuntimeException('Resolved test database identity is unsafe; migrations refused.');
        }

        return $app;
    }
}
