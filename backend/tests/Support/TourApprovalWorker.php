<?php

use App\Domains\Admin\Actions\ApproveTourAction;
use App\Filament\Resources\TourResource\Pages\EditTour;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Standalone worker. The parent Pest process owns the disposable database lock.
[, $actorId, $tourId, $arrival] = $_SERVER['argv'];
$newOwnerId = $_SERVER['argv'][4] ?? null;
$environment = [
    'APP_ENV' => 'testing', 'APP_DEBUG' => 'false',
    'APP_CONFIG_CACHE' => sys_get_temp_dir() . '/egm002-worker-' . uniqid() . '.php',
    'DB_CONNECTION' => 'pgsql', 'DB_HOST' => 'bookly-test-postgres',
    'DB_PORT' => '5432', 'DB_DATABASE' => 'bookly_test',
    'DB_USERNAME' => 'bookly_test', 'DB_PASSWORD' => '', 'DB_URL' => '',
    'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SCOUT_DRIVER' => 'collection',
    'SCOUT_QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'SESSION_DRIVER' => 'array',
];
foreach ($environment as $name => $value) {
    putenv($name . '=' . $value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$identity = DB::selectOne('select current_database() as db, current_user as usr');
if ($identity->db !== 'bookly_test' || $identity->usr !== 'bookly_test') {
    throw new RuntimeException('Refusing publication outside the disposable test database.');
}
DB::statement("SET lock_timeout = '30000'");
DB::statement("SET statement_timeout = '60000'");
Queue::fake();
$actor = User::findOrFail((int) $actorId);
$tour = Tour::findOrFail((int) $tourId);

if ($newOwnerId !== null) {
    Livewire::actingAs($actor);
    $component = Livewire::test(EditTour::class, ['record' => $tour->id])
        ->fillForm(['partner_id' => (int) $newOwnerId]);
    file_put_contents($arrival, (string) DB::selectOne('select pg_backend_pid() as pid')->pid);
    $component->call('save')->assertHasFormErrors(['partner_id']);
    echo 'refused-form';
    exit(0);
}

file_put_contents($arrival, (string) DB::selectOne('select pg_backend_pid() as pid')->pid);

try {
    app(ApproveTourAction::class)->execute($actor, $tour);
    echo 'published';
} catch (HttpException $exception) {
    echo 'refused:' . $exception->getStatusCode();
}
