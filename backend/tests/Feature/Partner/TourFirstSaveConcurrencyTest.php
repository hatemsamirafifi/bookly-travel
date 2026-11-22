<?php

use App\Domains\Partner\Models\Partner;
use App\Domains\Partner\Models\TourDraft;
use App\Domains\Partner\Services\TourService;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourTranslationState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Spec019TranslationProcess;

function egm005WithCommittedFirstSaveFixture(callable $scenario): void
{
    $previousDefault = DB::getDefaultConnection();
    config()->set('database.connections.egm005_first_save', config('database.connections.pgsql'));
    DB::setDefaultConnection('egm005_first_save');

    $partnerUser = $partner = $category = null;
    $dir = sys_get_temp_dir() . '/egm005-create-race-' . uniqid();
    mkdir($dir, 0700);

    try {
        $identity = DB::selectOne('select current_database() as db, current_user as usr');
        expect($identity->db)->toBe('bookly_test')->and($identity->usr)->toBe('bookly_test');

        $partnerUser = User::factory()->partner()->create();
        $partner = Partner::create([
            'user_id' => $partnerUser->id,
            'role' => 'partner',
            'onboarding_status' => 'complete',
            'is_active' => true,
        ]);
        $category = Category::create([
            'slug' => 'egm005-d2-race-' . uniqid(),
            'name' => 'D2 Race Category',
        ]);

        $scenario(compact('partner', 'partnerUser', 'category', 'dir'));
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        if ($partner !== null) {
            $tourIds = DB::table('tours')->where('partner_id', $partner->id)->pluck('id');
            DB::table('tour_drafts')->where('partner_id', $partner->id)->delete();
            if ($tourIds->isNotEmpty()) {
                DB::table('tour_options')->whereIn('tour_id', $tourIds)->delete();
                DB::table('tour_translations')->whereIn('tour_id', $tourIds)->delete();
                DB::table('tour_translation_states')->whereIn('tour_id', $tourIds)->delete();
                DB::table('tours')->whereIn('id', $tourIds)->delete();
            }
            $partner->delete();
        }

        $partnerUser?->delete();
        $category?->delete();

        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }

        DB::setDefaultConnection($previousDefault);
        DB::purge('egm005_first_save');
    }
}

function egm005RunTwoFirstSaveWorkers(
    int $partnerId,
    array $payload1,
    array $payload2,
    string $dir,
): array {
    $payloadFile1 = $dir . '/payload1.json';
    $payloadFile2 = $dir . '/payload2.json';
    $ready1 = $dir . '/ready1';
    $ready2 = $dir . '/ready2';
    $out1 = $dir . '/out1';
    $err1 = $dir . '/err1';
    $out2 = $dir . '/out2';
    $err2 = $dir . '/err2';
    $goFile = $dir . '/go';

    file_put_contents($payloadFile1, json_encode($payload1, JSON_THROW_ON_ERROR));
    file_put_contents($payloadFile2, json_encode($payload2, JSON_THROW_ON_ERROR));

    $workerPath = base_path('tests/Support/TourCreateIdempotencyWorker.php');
    $w1 = Spec019TranslationProcess::spawn(
        $workerPath,
        [(string) $partnerId, $payloadFile1, $ready1, $goFile],
        $out1,
        $err1,
        'D2 first-save worker 1',
    );
    $w2 = Spec019TranslationProcess::spawn(
        $workerPath,
        [(string) $partnerId, $payloadFile2, $ready2, $goFile],
        $out2,
        $err2,
        'D2 first-save worker 2',
    );

    try {
        Spec019TranslationProcess::waitForFile($ready1, 90, 'D2 worker 1 ready', $err1, $w1);
        Spec019TranslationProcess::waitForFile($ready2, 90, 'D2 worker 2 ready', $err2, $w2);
        Spec019TranslationProcess::signal($goFile);

        expect($w1->waitExit(60))->toBe(0)
            ->and($w2->waitExit(60))->toBe(0);

        return [
            trim((string) file_get_contents($out1)),
            trim((string) file_get_contents($out2)),
            (string) @file_get_contents($err1),
            (string) @file_get_contents($err2),
        ];
    } finally {
        $w1->close();
        $w2->close();
    }
}

test('simultaneous identical first-save requests with the same creation_id produce a single tour and one replay', function () {
    egm005WithCommittedFirstSaveFixture(function (array $fixture): void {
        $creationId = (string) Str::uuid();
        $payload = [
            'creation_id' => $creationId,
            'category_id' => $fixture['category']->id,
            'destination' => 'Florence, Italy',
            'duration_value' => 3,
            'duration_unit' => 'hour',
            'difficulty_level' => 'easy',
            'source_locale' => 'es',
            'title' => 'Recorrido concurrente por Florencia',
            'description' => str_repeat('Un paseo idempotente bajo concurrencia real en PostgreSQL. ', 4),
            'options' => [
                [
                    'title' => 'Turno de mañana',
                    'pickup_instructions' => 'Piazza del Duomo',
                    'guide_languages' => ['es'],
                    'price_amount' => 4500,
                    'group_size_max' => 10,
                    'start_time' => '09:00:00',
                ],
            ],
        ];

        [$raw1, $raw2, $err1, $err2] = egm005RunTwoFirstSaveWorkers(
            $fixture['partner']->id,
            $payload,
            $payload,
            $fixture['dir'],
        );

        expect($raw1)->toMatch('/^(created|replayed):\d+$/', "Worker 1 stderr: {$err1}")
            ->and($raw2)->toMatch('/^(created|replayed):\d+$/', "Worker 2 stderr: {$err2}");

        [$kind1, $id1] = explode(':', $raw1);
        [$kind2, $id2] = explode(':', $raw2);
        $kinds = [$kind1, $kind2];
        sort($kinds);

        $createdTour = Tour::findOrFail((int) $id1);

        expect($kinds)->toBe(['created', 'replayed'])
            ->and((int) $id1)->toBe((int) $id2)
            ->and(Tour::where('partner_id', $fixture['partner']->id)->count())->toBe(1)
            ->and($createdTour->options()->count())->toBe(1)
            ->and($createdTour->generationEpoch())->toBe(1)
            ->and(TourTranslationState::where('tour_id', $createdTour->id)->count())->toBe(2);

        $drafts = TourDraft::where('partner_id', $fixture['partner']->id)
            ->where('creation_id', $creationId)
            ->get();

        expect($drafts)->toHaveCount(1)
            ->and($drafts->first()->tour_id)->toBe((int) $id1)
            ->and($drafts->first()->creation_payload_hash)->toBeString()->toHaveLength(64);
    });
});

test('simultaneous divergent first-save requests with the same creation_id create one tour and return 409 conflict', function () {
    egm005WithCommittedFirstSaveFixture(function (array $fixture): void {
        $creationId = (string) Str::uuid();
        $base = [
            'creation_id' => $creationId,
            'category_id' => $fixture['category']->id,
            'destination' => 'Rome, Italy',
            'duration_value' => 2,
            'duration_unit' => 'hour',
            'difficulty_level' => 'easy',
            'source_locale' => 'en',
            'title' => 'Colosseum Early Entry',
            'description' => str_repeat('Concurrent divergent payload check on creation_id reuse. ', 4),
        ];

        $divergent = $base;
        $divergent['title'] = 'Vatican Museum Afternoon';
        $divergent['destination'] = 'Vatican City';

        [$raw1, $raw2, $err1, $err2] = egm005RunTwoFirstSaveWorkers(
            $fixture['partner']->id,
            $base,
            $divergent,
            $fixture['dir'],
        );

        expect($raw1)->toMatch('/^(created:\d+|conflict:409:CREATION_ID_REUSED_WITH_DIFFERENT_PAYLOAD)$/', "Worker 1 stderr: {$err1}")
            ->and($raw2)->toMatch('/^(created:\d+|conflict:409:CREATION_ID_REUSED_WITH_DIFFERENT_PAYLOAD)$/', "Worker 2 stderr: {$err2}");

        $outcomes = [
            $raw1 === 'conflict:409:CREATION_ID_REUSED_WITH_DIFFERENT_PAYLOAD' ? 'conflict' : 'created',
            $raw2 === 'conflict:409:CREATION_ID_REUSED_WITH_DIFFERENT_PAYLOAD' ? 'conflict' : 'created',
        ];
        sort($outcomes);

        expect($outcomes)->toBe(['conflict', 'created'])
            ->and(Tour::where('partner_id', $fixture['partner']->id)->count())->toBe(1)
            ->and(TourDraft::where('partner_id', $fixture['partner']->id)->where('creation_id', $creationId)->count())->toBe(1);
    });
});

test('simultaneous first-save requests sharing a pre-existing unmaterialized wizard draft create a single tour', function () {
    egm005WithCommittedFirstSaveFixture(function (array $fixture): void {
        $creationId = (string) Str::uuid();

        app(TourService::class)->saveGeneralDraft($fixture['partner']->id, [
            'creation_id' => $creationId,
            'source_locale' => 'it',
            'current_step' => 'details',
            'payload' => [
                'title' => 'Tour in barca a Venezia',
            ],
        ]);

        $payload = [
            'creation_id' => $creationId,
            'category_id' => $fixture['category']->id,
            'destination' => 'Venice, Italy',
            'duration_value' => 4,
            'duration_unit' => 'hour',
            'difficulty_level' => 'moderate',
            'source_locale' => 'it',
            'title' => 'Tour in barca a Venezia',
            'description' => str_repeat('Materializzazione simultanea da una bozza esistente senza duplicati. ', 4),
        ];

        [$raw1, $raw2, $err1, $err2] = egm005RunTwoFirstSaveWorkers(
            $fixture['partner']->id,
            $payload,
            $payload,
            $fixture['dir'],
        );

        expect($raw1)->toMatch('/^(created|replayed):\d+$/', "Worker 1 stderr: {$err1}")
            ->and($raw2)->toMatch('/^(created|replayed):\d+$/', "Worker 2 stderr: {$err2}");

        [$kind1, $id1] = explode(':', $raw1);
        [$kind2, $id2] = explode(':', $raw2);
        $kinds = [$kind1, $kind2];
        sort($kinds);

        expect($kinds)->toBe(['created', 'replayed'])
            ->and((int) $id1)->toBe((int) $id2)
            ->and(Tour::where('partner_id', $fixture['partner']->id)->count())->toBe(1)
            ->and(TourDraft::where('partner_id', $fixture['partner']->id)->where('creation_id', $creationId)->count())->toBe(1)
            ->and(TourDraft::where('partner_id', $fixture['partner']->id)->where('creation_id', $creationId)->first()->tour_id)->toBe((int) $id1);
    });
});
