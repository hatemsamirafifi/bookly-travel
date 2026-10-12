<?php
require_once __DIR__.'/bootstrap.php';
set_exception_handler(function(Throwable $error):void {echo json_encode(['proof_failed'=>true,'message'=>$error instanceof RuntimeException?$error->getMessage():'Unexpected fixture cleanup failure'])."\n";exit(1);});
use App\Models\Tour;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
$fixtures=[json_decode(file_get_contents(__DIR__.'/owned-fixture.json'),true,512,JSON_THROW_ON_ERROR),json_decode(file_get_contents(__DIR__.'/owned-adoption-fixture.json'),true,512,JSON_THROW_ON_ERROR)];
$results=[];
function contentSnapshot(Tour $tour):array {
 return ['legacy_raw'=>$tour->getRawOriginal('itinerary'),'price'=>$tour->price_amount,'translations'=>DB::table('tour_translations')->where('tour_id',$tour->id)->orderBy('locale')->get()->toArray(),'states'=>DB::table('tour_translation_states')->where('tour_id',$tour->id)->orderBy('locale')->get()->toArray(),'media'=>DB::table('tour_media')->where('tour_id',$tour->id)->orderBy('id')->get()->toArray(),'availability'=>DB::table('availability_rules')->where('tour_id',$tour->id)->orderBy('id')->get()->toArray()];
}
DB::transaction(function()use($fixtures,&$results){
 foreach($fixtures as $fixture){
  $tour=Tour::whereKey($fixture['tour_id'])->lockForUpdate()->firstOrFail();
  if($tour->slug!==$fixture['slug']||$tour->partner_id!==$fixture['partner_id']||$tour->status!=='published'||!str_starts_with($tour->slug,'spec019-'))throw new RuntimeException('Owned rehearsal fixture identity/status mismatch.');
  $before=hash('sha256',json_encode(contentSnapshot($tour),JSON_THROW_ON_ERROR));
  Tour::withoutEvents(fn()=>$tour->update(['status'=>'archived']));
  $after=hash('sha256',json_encode(contentSnapshot($tour->fresh()),JSON_THROW_ON_ERROR));
  if($before!==$after)throw new RuntimeException('Archiving changed retained source, derivative, legacy, media or availability data.');
  $results[]=['tour_id'=>$tour->id,'slug'=>$tour->slug,'status'=>'archived','retained_content_sha256'=>$after,'source_rows_retained'=>true,'raw_legacy_retained'=>true,'media_retained'=>true,'availability_retained'=>true];
 }
});
foreach($results as &$result){$response=Http::acceptJson()->get('http://nginx/api/public/tours/'.$result['slug'].'?locale=en');if($response->status()!==410)throw new RuntimeException('Archived rehearsal fixture did not return existing 410 contract.');$result['public_http_status']=$response->status();}
file_put_contents(__DIR__.'/archived-rehearsal-fixtures.json',json_encode(['observed_at'=>gmdate('c'),'scope'=>'Only the two exclusively owned synthetic rehearsal tours; reversible status archive, no row deletion','results'=>$results],JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['archived_owned_fixtures'=>count($results),'all_content_retained'=>true,'public_http'=>410])."\n";
