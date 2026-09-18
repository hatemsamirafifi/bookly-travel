<?php

use Illuminate\Support\Facades\DB;

it('runs only against a disposable test database identity', function () {
    $identity = DB::selectOne('SELECT current_database() AS database, current_user AS username');

    expect($identity->database)->toStartWith('bookly_test')
        ->and($identity->database)->not->toBe('bookly')
        ->and($identity->username)->toBe('bookly_test')
        ->and(config('database.default'))->toBe('pgsql')
        ->and(config('database.connections.pgsql.host'))->toBe('bookly-test-postgres')
        ->and(config('database.connections.pgsql.url'))->toBeEmpty();
});
