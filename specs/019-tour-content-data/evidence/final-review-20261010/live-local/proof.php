<?php
require __DIR__.'/bootstrap.php';
set_exception_handler(function(Throwable $error):void {echo json_encode(['proof_failed'=>true,'exception_type'=>get_class($error),'message'=>$error instanceof RuntimeException?$error->getMessage():'Unexpected proof harness failure; inspect locally without exporting secrets'])."\n";exit(1);});
use App\Models\Tour;
use App\Domains\Partner\Services\TourTranslationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
$mode=$argv[1]??'';
$file=__DIR__.'/owned-fixture.json';
function ensure(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
function owned():array {return json_decode(file_get_contents(__DIR__.'/owned-fixture.json'),true,512,JSON_THROW_ON_ERROR);}
function record(string $name,array $data):void {file_put_contents(__DIR__.'/'.$name.'.json',json_encode($data,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));file_put_contents(__DIR__.'/observations.jsonl',json_encode(['at'=>gmdate('c'),'step'=>$name]+$data,JSON_THROW_ON_ERROR)."\n",FILE_APPEND);echo json_encode(['step'=>$name]+$data,JSON_THROW_ON_ERROR)."\n";}
if($mode==='archive-rehearsal'){require __DIR__.'/archive-owned-rehearsal.php';exit(0);}
if($mode==='source'){
 ensure(!is_file($file),'Owned fixture already exists; inspect before creating another.');
 $auth=json_decode(file_get_contents('/auth/partner.json'),true,512,JSON_THROW_ON_ERROR);$token=null;
 foreach($auth['origins'] as $origin)foreach($origin['localStorage'] as $entry)if($entry['name']==='auth_token')$token=$entry['value'];
 ensure(is_string($token)&&$token!=='','Missing owned partner auth.');
 $title='Spec019 local real generation '.gmdate('YmdHis');
 $source=['title'=>$title,'description'=>'Walk through historic Rome with a local guide, exploring old streets and two authentic stops while learning about their history, culture and stories during this carefully planned walking experience.','meeting_point'=>'Colosseum Main Entrance','cancellation_policy'=>'Cancel up to 24 hours in advance.','highlights'=>['Old-town route'],'inclusions'=>['Live guide'],'exclusions'=>[],'important_information'=>['Comfortable shoes recommended'],'itinerary'=>[['day'=>7,'title'=>'Arrival','description'=>null,'stops'=>[['title'=>'Meet the guide','description'=>null,'duration_minutes'=>30],['title'=>'Old town walk','description'=>'Explore historic streets.','duration_minutes'=>null]]],['day'=>7,'title'=>'Explore','description'=>null,'stops'=>[]]]];
 $payload=['title'=>$title,'description'=>$source['description'],'category'=>'walking','destination'=>'Rome, Italy','duration_value'=>3,'duration_unit'=>'hour','difficulty_level'=>'easy','price_from'=>75,'guide_languages'=>['en'],'translations'=>['en'=>$source],'media'=>[['url'=>'https://cdn.bookly.test/tours/rome-cover.jpg','is_cover'=>true,'sort_order'=>0,'alt_text'=>'Historic Rome walking route']]];
 $started=microtime(true);$response=Http::withToken($token)->acceptJson()->timeout(20)->post('http://nginx/api/partner/tours',$payload);
 ensure($response->status()===201,'Actual source HTTP create failed status '.$response->status());
 $id=$response->json('data.id');ensure(is_int($id)&&$id>0,'Source did not return a tour ID.');
 $tour=Tour::with(['translations','translationStates','media'])->findOrFail($id);
 $en=$tour->translations->firstWhere('locale','en');$service=app(TourTranslationService::class);
 $states=$tour->translationStates->pluck('status','locale')->all();
 $metadata=['tour_id'=>$id,'slug'=>$tour->slug,'partner_id'=>$tour->partner_id,'source_hash'=>$service->sourceHash($en),'source_sha256'=>hash('sha256',json_encode($service->sourcePayload($en))),'legacy_raw'=>$tour->getRawOriginal('itinerary'),'queue'=>config('queue.connections.redis.queue')];
 file_put_contents($file,json_encode($metadata,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
 ensure($service->sourcePayload($en)==$source,'Accepted source differs from authored source.');
 ensure(($states['es']??null)==='pending'&&($states['it']??null)==='pending','Source did not persist pending derivatives.');
 // Non-production fixture promotion only: the HTTP create correctly returned a draft.
 // This deliberately does not claim to exercise the administrative publication workflow.
 Tour::withoutEvents(fn()=>$tour->update(['status'=>'published','published_at'=>now()]));
 App\Domains\Partner\Models\AvailabilityRule::create(['tour_id'=>$id,'rule_type'=>'recurring','days_of_week'=>[0,1,2,3,4,5,6],'capacity'=>10]);
 $fallback=Http::acceptJson()->get('http://nginx/api/public/tours/'.$tour->slug.'?locale=es');
 ensure($fallback->status()===200&&$fallback->json('data.content_locale')==='en','Pending source did not publicly fall back to English.');
 record('source-http-pending',['http_status'=>201,'elapsed_seconds'=>microtime(true)-$started,'tour_id'=>$id,'states'=>$states,'public_status'=>$fallback->status(),'public_content_locale'=>$fallback->json('data.content_locale'),'configured_model'=>config('services.gemini.translation_model'),'key_present'=>is_string(config('services.gemini.api_key'))&&config('services.gemini.api_key')!=='','source_hash'=>$metadata['source_hash']]);
}elseif($mode==='recover'){
 $m=owned();$code=Artisan::call('tours:queue-translations',['--after-id'=>$m['tour_id']-1,'--limit'=>1]);ensure($code===0,'Recovery failed.');
 record('bounded-recovery',['exit'=>$code,'output'=>trim(Artisan::output()),'queue_size'=>Queue::connection('redis')->size($m['queue']),'retry_after'=>config('queue.connections.redis.retry_after')]);
}elseif($mode==='worker'||$mode==='rollback-worker'){
 $m=owned();ensure(extension_loaded('pcntl'),'Worker timeout requires pcntl.');
 if($mode==='rollback-worker'){
  $tour=Tour::with(['translations','translationStates'])->findOrFail($m['tour_id']);$service=app(TourTranslationService::class);
  foreach(['es','it']as$locale){ensure($service->currentTranslation($tour,$locale)!==null,'Rollback expects actual ready rows.');Illuminate\Support\Facades\Bus::dispatch(new App\Domains\Partner\Jobs\GenerateTourTranslationJob($tour->id,$locale,$m['source_hash']));}
  app()->bind(App\Domains\Partner\Contracts\TourContentTranslator::class,fn()=>new class implements App\Domains\Partner\Contracts\TourContentTranslator {public function translate(array $strings,string $locale):array {throw new RuntimeException('Ready duplicate unexpectedly attempted provider work during rollback.');}});
 }
 $events=[];app('events')->listen(Illuminate\Queue\Events\JobProcessing::class,function($event)use(&$events){$events[]=['event'=>'processing','job'=>$event->job->resolveName(),'attempt'=>$event->job->attempts(),'at'=>gmdate('c')];});
 app('events')->listen(Illuminate\Queue\Events\JobProcessed::class,function($event)use(&$events){$events[]=['event'=>'processed','job'=>$event->job->resolveName(),'at'=>gmdate('c')];});
 // Quiet prevents an exception trace, prompt or provider response from leaking.
 $start=microtime(true);$code=Artisan::call('queue:work',['connection'=>'redis','--queue'=>$m['queue'],'--timeout'=>60,'--tries'=>4,'--sleep'=>1,'--stop-when-empty'=>true,'--max-time'=>180,'--quiet'=>true]);
 record($mode==='rollback-worker'?'compatible-rollback-worker':'actual-worker',['exit'=>$code,'elapsed_seconds'=>microtime(true)-$start,'events'=>$events,'remaining_queue'=>Queue::connection('redis')->size($m['queue'])]);ensure($code===0,'Live worker exited unsuccessfully.');
}elseif($mode==='inspect'){
 $m=owned();$tour=Tour::with(['translations','translationStates','media'])->findOrFail($m['tour_id']);$service=app(TourTranslationService::class);$en=$tour->translations->firstWhere('locale','en');
 ensure($service->sourceHash($en)===$m['source_hash'],'Live generation rewrote English.');
 $results=[];
 foreach(['es','it']as$locale){$row=$service->currentTranslation($tour,$locale);$state=$tour->translationStates->firstWhere('locale',$locale);$results[$locale]=['status'=>$state?->status,'error_category'=>$state?->last_error_code,'matching_hash'=>$state?->translated_hash===$m['source_hash'],'derivative_present'=>$row!==null];
 if($row){$data=$service->sourcePayload($row);ensure(count($data)===9,'Derivative must preserve nine-field shape.');ensure(array_column($data['itinerary'],'day')===[7,7],'Generated day order changed.');ensure($data['itinerary'][0]['description']===null&&$data['itinerary'][0]['stops'][0]['duration_minutes']===30&&$data['itinerary'][0]['stops'][1]['duration_minutes']===null,'Generated null/numbers changed.');ensure(count($data['itinerary'][0]['stops'])===2&&$data['itinerary'][1]['stops']===[]&&$data['exclusions']===[],'Generated list shape changed.');}
 $http=Http::acceptJson()->get('http://nginx/api/public/tours/'.$tour->slug.'?locale='.$locale);$results[$locale]['public_http_status']=$http->status();$results[$locale]['public_content_locale']=$http->json('data.content_locale');$results[$locale]['price_amount']=$http->json('data.pricing.base_price.amount');
 }
 record('live-derivative-inspection',['results'=>$results,'english_preserved'=>true,'raw_legacy_preserved'=>$tour->getRawOriginal('itinerary')===$m['legacy_raw'],'queue_size'=>Queue::connection('redis')->size($m['queue'])]);
 foreach($results as $locale=>$result)ensure($result['status']==='ready'&&$result['matching_hash']&&$result['derivative_present']&&$result['public_content_locale']===$locale&&$result['price_amount']===7500,'Real generation did not yield current '.$locale.' next-read replacement.');
}elseif($mode==='adoption-rehearsal'){
 $m=owned();$parent=Tour::with('translations')->findOrFail($m['tour_id']);
 ensure(str_starts_with($parent->slug,'spec019-local-real-generation-')&&$parent->partner_id===$m['partner_id'],'Owned adoption fixture parent mismatch.');
 $f=__DIR__.'/owned-adoption-fixture.json';ensure(!is_file($f),'Owned adoption fixture already exists.');
 $legacy=['Meet the local guide','Explore the historic route'];
 $tour=Tour::withoutEvents(fn()=>Tour::create(['partner_id'=>$parent->partner_id,'category_id'=>$parent->category_id,'slug'=>'spec019-legacy-rehearsal-'.bin2hex(random_bytes(5)),'location'=>'Rome, Italy','duration_minutes'=>180,'duration_label'=>'3 hours','group_size_min'=>1,'group_size_max'=>10,'price_amount'=>7500,'status'=>'published','published_at'=>now(),'difficulty_level'=>null,'guide_languages'=>['en'],'cover_image_url'=>null]));
 file_put_contents($f,json_encode(['tour_id'=>$tour->id,'slug'=>$tour->slug,'partner_id'=>$tour->partner_id],JSON_THROW_ON_ERROR));
 DB::table('tours')->where('id',$tour->id)->where('partner_id',$m['partner_id'])->update(['itinerary'=>json_encode($legacy,JSON_THROW_ON_ERROR)]);
 $service=app(TourTranslationService::class);$source=$service->sourcePayload($parent->translations->firstWhere('locale','en'));$source['title']='Spec019 legacy adoption rehearsal';$source['itinerary']=null;
 App\Models\TourTranslation::withoutEvents(fn()=>$tour->translations()->create(['locale'=>'en']+$source));
 App\Domains\Partner\Models\AvailabilityRule::create(['tour_id'=>$tour->id,'rule_type'=>'recurring','days_of_week'=>[0,1,2,3,4,5,6],'capacity'=>10]);
 $raw=$tour->fresh()->getRawOriginal('itinerary');$beforeQueue=Queue::connection('redis')->size($m['queue']);
 $args=['--after-id'=>$tour->id-1,'--limit'=>1];
 ensure(Artisan::call('tours:adopt-itineraries',$args+['--dry-run'=>true])===0,'Adoption dry-run failed.');$dry=trim(Artisan::output());
 ensure($tour->translations()->where('locale','en')->firstOrFail()->itinerary===null&&$tour->translationStates()->count()===0&&Queue::connection('redis')->size($m['queue'])===$beforeQueue,'Dry-run changed source, states or queue.');
 ensure(Artisan::call('tours:adopt-itineraries',$args)===0,'Adoption apply failed.');$apply=trim(Artisan::output());
 $adopted=$tour->translations()->where('locale','en')->firstOrFail()->itinerary;
 ensure(array_column($adopted,'title')===$legacy&&array_column($adopted,'day')===[1,2],'Actual adoption changed positional order.');
 ensure($tour->fresh()->getRawOriginal('itinerary')===$raw,'Actual adoption rewrote legacy data.');
 $states=$tour->translationStates()->pluck('status','locale')->all();ensure(($states['es']??null)==='pending'&&($states['it']??null)==='pending','Actual adoption did not schedule current generation.');
 $afterQueue=Queue::connection('redis')->size($m['queue']);
 ensure(Artisan::call('tours:adopt-itineraries',$args)===0,'Adoption repeat failed.');$repeat=trim(Artisan::output());
 ensure(Queue::connection('redis')->size($m['queue'])===$afterQueue&&$tour->translations()->where('locale','en')->firstOrFail()->itinerary===$adopted,'Adoption repeat rewrote source or dispatched jobs.');
 $public=Http::acceptJson()->get('http://nginx/api/public/tours/'.$tour->slug.'?locale=es');
 ensure($public->status()===200&&$public->json('data.images')===[]&&$public->json('data.reviews.count')===0&&$public->json('data.itinerary')===$adopted,'Actual adopted public response did not preserve days/no-photos/no-reviews.');
 file_put_contents(__DIR__.'/adoption-fixture-public.json',json_encode($public->json('data'),JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
 record('bounded-adoption-rehearsal',['tour_id'=>$tour->id,'slug'=>$tour->slug,'dry_run_output'=>$dry,'apply_output'=>$apply,'repeat_output'=>$repeat,'raw_legacy_unchanged'=>true,'dry_run_no_writes'=>true,'repeat_no_writes'=>true,'days'=>array_column($adopted,'day'),'queue_delta'=>$afterQueue-$beforeQueue,'states'=>$states,'public_http'=>200,'public_content_locale'=>$public->json('data.content_locale'),'images'=>0,'review_count'=>0]);
}elseif($mode==='fingerprint'){
 $m=owned();$tour=Tour::with(['translations','translationStates','media'])->findOrFail($m['tour_id']);
 $data=['tour'=>$tour->only(['id','slug','status','published_at','price_amount','difficulty_level','guide_languages','cover_image_url']),'legacy_raw'=>$tour->getRawOriginal('itinerary'),'translations'=>$tour->translations->sortBy('locale')->map(fn($row)=>['locale'=>$row->locale]+app(TourTranslationService::class)->sourcePayload($row))->values()->all(),'states'=>$tour->translationStates->sortBy('locale')->map(fn($row)=>['locale'=>$row->locale,'status'=>$row->status,'source_hash'=>$row->source_hash,'translated_hash'=>$row->translated_hash,'last_error_code'=>$row->last_error_code])->values()->all(),'media'=>$tour->media->sortBy('sort_order')->map(fn($row)=>$row->only(['url','type','sort_order','alt_text']))->values()->all()];
 $hash=hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));$baseline=__DIR__.'/retained-fingerprint.json';
 if(is_file($baseline)){ensure(json_decode(file_get_contents($baseline),true)['sha256']===$hash,'Compatible rollback changed retained content/state/media.');record('after-rollback-retained-data',['sha256'=>$hash,'unchanged'=>true,'translations'=>count($data['translations']),'media'=>count($data['media'])]);}
 else {file_put_contents($baseline,json_encode(['sha256'=>$hash],JSON_THROW_ON_ERROR));record('before-rollback-retained-data',['sha256'=>$hash,'translations'=>count($data['translations']),'media'=>count($data['media'])]);}
}else throw new RuntimeException('Unknown proof mode.');
