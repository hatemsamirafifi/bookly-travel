<?php
$proofRoot = __DIR__;
$env = [
 'APP_ENV'=>'testing','APP_DEBUG'=>'false','APP_CONFIG_CACHE'=>'/tmp/spec019-proof-config-never.php',
 'DB_CONNECTION'=>'pgsql','DB_HOST'=>'bookly-spec019-browser-postgres-1','DB_PORT'=>'5432',
 'DB_DATABASE'=>'bookly','DB_USERNAME'=>'bookly','DB_PASSWORD'=>'secret','DB_URL'=>'',
 'REDIS_HOST'=>'bookly-spec019-browser-redis-1','REDIS_PASSWORD'=>'','REDIS_URL'=>'',
 'REDIS_PREFIX'=>'spec019_live_local_20261010_','REDIS_DB'=>'0',
 'CACHE_STORE'=>'array','SESSION_DRIVER'=>'array','SCOUT_DRIVER'=>'collection',
 'QUEUE_CONNECTION'=>'redis','REDIS_QUEUE'=>'spec019-live-local-20261010','REDIS_QUEUE_RETRY_AFTER'=>'90','QUEUE_FAILED_DRIVER'=>'null',
 'SCOUT_QUEUE_CONNECTION'=>'sync','PULSE_ENABLED'=>'false','TELESCOPE_ENABLED'=>'false','NIGHTWATCH_ENABLED'=>'false',
 'MAIL_MAILER'=>'array'
];
foreach ($env as $name=>$value) {putenv($name.'='.$value);$_ENV[$name]=$_SERVER[$name]=$value;}
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = Illuminate\Support\Facades\DB::connection();
$identity=$db->selectOne('select current_database() as db,current_user as usr');
if ($identity->db!=='bookly'||$identity->usr!=='bookly'||config('database.connections.pgsql.host')!=='bookly-spec019-browser-postgres-1') {
 throw new RuntimeException('Isolated worker proof DB identity mismatch; refusing fixture writes.');
}
$db->statement("SET statement_timeout='10000'");
$db->statement("SET lock_timeout='5000'");
