<?php
declare(strict_types=1);

use App\Domains\Partner\Models\Partner;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslation;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$env = [
 'APP_ENV'=>'testing','APP_DEBUG'=>'false','APP_KEY'=>'base64:Ym9va2x5LXNwZWMwMTktYnJvd3Nlci10ZXN0LWtleTE=',
 'APP_CONFIG_CACHE'=>'/tmp/spec019-us4-never-config.php','APP_SERVICES_CACHE'=>'/tmp/spec019-us4-services.php','APP_PACKAGES_CACHE'=>'/tmp/spec019-us4-packages.php',
 'DB_CONNECTION'=>'pgsql','DB_HOST'=>'bookly-spec019-browser-postgres-1','DB_PORT'=>'5432','DB_DATABASE'=>'bookly','DB_USERNAME'=>'bookly','DB_PASSWORD'=>'secret','DB_URL'=>'',
 'CACHE_STORE'=>'array','SESSION_DRIVER'=>'array','SCOUT_DRIVER'=>'collection','QUEUE_CONNECTION'=>'sync','MAIL_MAILER'=>'array',
 'GEMINI_API_KEY'=>'','PULSE_ENABLED'=>'false','TELESCOPE_ENABLED'=>'false','NIGHTWATCH_ENABLED'=>'false','BCRYPT_ROUNDS'=>'4',
];
foreach ($env as $name=>$value) {putenv($name.'='.$value);$_ENV[$name]=$_SERVER[$name]=$value;}
require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db = DB::connection();
$identity = $db->selectOne('select current_database() as db,current_user as usr');
if ($identity->db !== 'bookly' || $identity->usr !== 'bookly' || config('database.connections.pgsql.host') !== 'bookly-spec019-browser-postgres-1') {
 throw new RuntimeException('Refusing writes outside the exact owned US4 proof database.');
}
$db->statement("SET statement_timeout='10000'");
$db->statement("SET lock_timeout='5000'");
$file=__DIR__.'/fixture.json';
$mode=$argv[1]??'';
$snapshot=static function(int $id): array {
 return [
  'tour'=>(array)DB::table('tours')->where('id',$id)->first(),
  'english'=>(array)DB::table('tour_translations')->where('tour_id',$id)->where('locale','en')->first(),
  'media'=>DB::table('tour_media')->where('tour_id',$id)->orderBy('sort_order')->get(['id','tour_id','type','url','thumbnail_url','sort_order','created_at'])->map(fn($row)=>(array)$row)->all(),
 ];
};
$save=static fn(array $data)=>file_put_contents($file,json_encode($data,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
if($mode==='baseline') {
 if(is_file($file)) throw new RuntimeException('Owned fixture already exists; inspect before rerunning.');
 if(Schema::hasTable('migrations') && DB::table('migrations')->count() !== 0) throw new RuntimeException('Baseline requires the empty owned reboot-reset database.');
 if(Artisan::call('migrate',['--force'=>true])!==0) throw new RuntimeException('Old baseline migration failed.');
 if(Schema::hasColumn('tour_media','alt_text')) throw new RuntimeException('Old snapshot unexpectedly contains new alt storage.');
 $owned=['user_id'=>null,'partner_id'=>null,'category_id'=>null,'tour_id'=>null];$save($owned);
 $user=User::factory()->partner()->create();$owned['user_id']=$user->id;$save($owned);
 $partner=Partner::create(['user_id'=>$user->id,'role'=>'partner','onboarding_status'=>'complete','is_active'=>true]);$owned['partner_id']=$partner->id;$save($owned);
 $category=Category::create(['slug'=>'us4-migration-proof-'.bin2hex(random_bytes(5)),'name'=>'Migration proof']);$owned['category_id']=$category->id;$save($owned);
 $tour=Tour::withoutEvents(fn()=>Tour::create(['partner_id'=>$partner->id,'category_id'=>$category->id,'slug'=>'us4-migration-proof-'.bin2hex(random_bytes(5)),'location'=>'Rome','duration_minutes'=>120,'duration_label'=>'2 hours','group_size_min'=>1,'group_size_max'=>10,'price_amount'=>5000,'status'=>'draft','difficulty_level'=>null,'guide_languages'=>['de','en'],'cover_image_url'=>'https://images.example.com/cover.jpg']));
 $owned['tour_id']=$tour->id;$save($owned);
 TourTranslation::withoutEvents(fn()=>$tour->translations()->create(['locale'=>'en','title'=>'Legacy migration proof','description'=>'Existing English source retained.','itinerary'=>null]));
 $tour->media()->createMany([['type'=>'image','url'=>'https://images.example.com/side.jpg','sort_order'=>0],['type'=>'image','url'=>'https://images.example.com/cover.jpg','sort_order'=>1]]);
 $owned['before']=$snapshot($tour->id);$save($owned);
 echo json_encode(['mode'=>'baseline','baseline'=>'4613fe8','database'=>$identity->db,'user'=>$identity->usr,'has_alt_column'=>false,'legacy_media_count'=>2,'difficulty'=>null,'fixture_id'=>$tour->id],JSON_THROW_ON_ERROR)."\n";
} elseif($mode==='upgrade') {
 $owned=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);$id=$owned['tour_id'];
 if(Artisan::call('migrate',['--force'=>true,'--realpath'=>true,'--path'=>['/workspace/backend/database/migrations/2026_10_02_000001_add_alt_text_to_tour_media_table.php']])!==0) throw new RuntimeException('Additive migration failed.');
 if(!Schema::hasColumn('tour_media','alt_text') || $snapshot($id)!==$owned['before']) throw new RuntimeException('Additive migration changed existing rows.');
 if(DB::table('tour_media')->where('tour_id',$id)->whereNotNull('alt_text')->exists()) throw new RuntimeException('Legacy alt was fabricated.');
 $migration=require '/workspace/backend/database/migrations/2026_10_02_000001_add_alt_text_to_tour_media_table.php';
 $migration->down();
 if(Schema::hasColumn('tour_media','alt_text') || $snapshot($id)!==$owned['before']) throw new RuntimeException('Disposable schema down changed old rows.');
 $migration->up();
 if(!Schema::hasColumn('tour_media','alt_text') || $snapshot($id)!==$owned['before']) throw new RuntimeException('Disposable schema up changed old rows.');
 DB::table('tour_media')->where('tour_id',$id)->where('sort_order',0)->update(['type'=>'gallery']);
 DB::table('tour_media')->where('tour_id',$id)->where('sort_order',1)->update(['type'=>'cover','alt_text'=>'Harbor at sunrise']);
 $oldReader=Tour::with('media')->findOrFail($id);
 if($oldReader->allImageUrls()!==['https://images.example.com/cover.jpg','https://images.example.com/side.jpg']) throw new RuntimeException('Compatible old reader lost the typed gallery.');
 if(DB::table('tour_media')->where('tour_id',$id)->where('type','cover')->value('alt_text')!=='Harbor at sunrise') throw new RuntimeException('Application rollback lost retained alt.');
 echo json_encode(['mode'=>'upgrade','additive_rows_unchanged'=>true,'legacy_alt_null'=>true,'disposable_down_up'=>true,'old_application_reader'=>'4613fe8','compatible_gallery'=>true,'application_rollback_retains_column_and_alt'=>true,'migration_records'=>DB::table('migrations')->where('migration','2026_10_02_000001_add_alt_text_to_tour_media_table')->count()],JSON_THROW_ON_ERROR)."\n";
} elseif($mode==='cleanup') {
 $owned=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
 if($owned['tour_id'])DB::table('tours')->where('id',$owned['tour_id'])->delete();
 if($owned['partner_id'])DB::table('partners')->where('id',$owned['partner_id'])->delete();
 if($owned['user_id'])DB::table('users')->where('id',$owned['user_id'])->delete();
 if($owned['category_id'])DB::table('categories')->where('id',$owned['category_id'])->delete();
 rename($file,__DIR__.'/fixture-cleaned.json'); echo "OWNED_US4_FIXTURES_CLEANED\n";
} else throw new RuntimeException('Unknown proof action.');
