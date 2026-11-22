<?php

use App\Domains\Admin\Actions\RequestTourChangesAction;
use App\Domains\Partner\Exceptions\TourRevisionException;
use App\Domains\Partner\Services\TourRevisionService;
use App\Models\Category;
use App\Models\Tour;
use Illuminate\Support\Facades\DB;
use Tests\Support\Spec019TranslationProcess;
use Tests\Support\TourRevisionFixtures;

// A separate named connection commits fixture rows without escaping or committing
// the Pest RefreshDatabase transaction. Every fixture is removed by its own ID.
function egm003WithCommittedGovernanceFixture(callable $scenario): void
{
    $previousDefault = DB::getDefaultConnection();
    config()->set('database.connections.egm003_owner', config('database.connections.pgsql'));
    DB::setDefaultConnection('egm003_owner');
    $category = $partner = $otherPartner = $admin = $tour = null;
    $owner = $otherOwner = null;
    try {
        $identity = DB::selectOne('select current_database() as db, current_user as usr');
        expect($identity->db)->toBe('bookly_test')->and($identity->usr)->toBe('bookly_test');
        $partner = TourRevisionFixtures::partner();
        $otherPartner = TourRevisionFixtures::partner();
        $owner = $partner->user;
        $otherOwner = $otherPartner->user;
        $admin = TourRevisionFixtures::admin('manage_tours');
        $category = Category::create(['slug' => 'egm003-race-' . uniqid(), 'name' => 'Revision race']);
        $tour = Tour::create([
            'partner_id' => $partner->id, 'category_id' => $category->id,
            'slug' => 'egm003-race-' . uniqid(), 'source_locale' => 'es',
            'location' => 'Sevilla', 'duration_minutes' => 120, 'duration_label' => '2 hours',
            'group_size_min' => 1, 'group_size_max' => 10,
            'price_amount' => 5000, 'status' => 'published', 'published_at' => now()->subDay(),
        ]);
        $tour->translations()->create([
            'locale' => 'es', 'title' => 'Paseo por Sevilla', 'description' => 'Descripción de origen.',
        ]);
        $baseline = app(TourRevisionService::class)->captureBaseline($tour);
        $scenario(compact('tour', 'partner', 'otherPartner', 'owner', 'admin', 'baseline'));
    } finally {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        if ($tour !== null) {
            DB::table('governance_audit_logs')->where('target_type', 'tour')->where('target_id', $tour->id)->delete();
            $tour->delete();
        }
        $partner?->delete();
        $otherPartner?->delete();
        $owner?->delete();
        $otherOwner?->delete();
        $admin?->adminPermission()->delete();
        $admin?->delete();
        $category?->delete();
        DB::setDefaultConnection($previousDefault);
        DB::purge('egm003_owner');
    }
}

it('rechecks ownership after waiting for a concurrent transfer', function (string $operation) {
    egm003WithCommittedGovernanceFixture(function (array $fixture) use ($operation): void {
        $revisionId = $fixture['baseline']->id;
        $actorId = $fixture['owner']->id;
        if ($operation === 'revision approval') {
            $service = app(TourRevisionService::class);
            $candidate = $service->createCandidate($fixture['tour']->id, $actorId, $revisionId, ['source' => ['title' => 'Review ownership race']]);
            $service->submitCandidate($fixture['tour']->id, $candidate->id, $actorId);
            $revisionId = $candidate->id;
            $actorId = $fixture['admin']->id;
        }
        $directory = sys_get_temp_dir() . '/egm003-owner-' . uniqid();
        mkdir($directory, 0700);
        $arrival = $directory . '/arrival';
        $output = $directory . '/stdout';
        $error = $directory . '/stderr';
        $worker = null;
        try {
            DB::beginTransaction();
            DB::table('tours')->where('id', $fixture['tour']->id)->update(['partner_id' => $fixture['otherPartner']->id]);
            $parentPid = (int) DB::selectOne('select pg_backend_pid() as pid')->pid;
            $worker = Spec019TranslationProcess::spawn(
                base_path('tests/Support/TourRevisionWorker.php'),
                [$operation, (string) $actorId, (string) $fixture['tour']->id, (string) $revisionId, $arrival],
                $output, $error, 'Concurrent owned revision access',
            );
            Spec019TranslationProcess::waitForFile($arrival, 90, 'Revision worker arrival', $error, $worker);
            $childPid = (int) file_get_contents($arrival);
            $blocked = false;
            $deadline = microtime(true) + 10;
            while (microtime(true) < $deadline && $worker->isAlive()) {
                $blocked = (int) DB::selectOne(
                    'select case when ? = any(pg_blocking_pids(?)) then 1 else 0 end as blocked',
                    [$parentPid, $childPid],
                )->blocked === 1;
                if ($blocked) {
                    break;
                }
                usleep(20000);
            }
            expect($blocked)->toBeTrue('The read/write must reach the actual PostgreSQL Tour lock.');
            DB::commit();
            expect($worker->waitExit(60))->toBe(0)
                ->and(trim(file_get_contents($output)))->toBe($operation === 'revision approval' ? 'refused:409' : 'refused:404')
                ->and($fixture['tour']->fresh()->price_amount)->toBe(5000);
            if ($operation === 'revision approval') {
                expect($candidate->fresh()->state)->toBe('proposed')
                    ->and($fixture['tour']->fresh()->published_revision_id)->toBe($fixture['baseline']->id);
            }
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $worker?->close();
            foreach ([$arrival, $arrival . '.spawned', $output, $error] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($directory);
        }
    });
})->with(['detail', 'governance', 'revision detail', 'revision history', 'operational save', 'revision approval']);

it('commits obsolete request reconciliation before reporting a refused response', function () {
    egm003WithCommittedGovernanceFixture(function (array $fixture): void {
        $tour = $fixture['tour'];
        $service = app(TourRevisionService::class);
        $candidate = $service->createCandidate($tour->id, $fixture['owner']->id, $fixture['baseline']->id, [
            'source' => ['title' => 'Contenido propuesto'],
        ]);
        $service->submitCandidate($tour->id, $candidate->id, $fixture['owner']->id);
        $request = app(RequestTourChangesAction::class)->execute($fixture['admin'], $tour, [
            'context' => 'published_review', 'reason' => 'Revise the source title.',
            'field_refs' => TourRevisionFixtures::refs('source.title'),
        ]);
        DB::table('tours')->where('id', $tour->id)->update(['status' => 'archived']);

        expect(fn () => $service->respondToRequest(
            $tour->id, $request->id, $fixture['partner']->id, $fixture['owner']->id,
            'The obsolete proposal must be refused.', ['revision_id' => $candidate->id],
        ))->toThrow(TourRevisionException::class);

        expect($candidate->fresh()->state)->toBe('superseded')
            ->and($request->fresh()->state)->toBe('superseded');
    });
});

it('rechecks reviewer permission after a real moderation lock wait', function (string $operation) {
    egm003WithCommittedGovernanceFixture(function (array $fixture) use ($operation): void {
        $service = app(TourRevisionService::class);
        $candidate = $service->createCandidate($fixture['tour'], $fixture['owner']->id, $fixture['baseline']->id, ['source' => ['title' => 'Proposed review title']]);
        $service->submitCandidate($fixture['tour'], $candidate->id, $fixture['owner']->id);
        $fixture['admin']->adminPermission()->update(['flags' => ['manage_tours' => true, 'correct_tour_content' => true]]);
        $directory = sys_get_temp_dir() . '/egm003-permission-' . uniqid();
        mkdir($directory, 0700);
        $arrival = $directory . '/arrival';
        $output = $directory . '/stdout';
        $error = $directory . '/stderr';
        $worker = null;
        try {
            DB::beginTransaction();
            DB::table('tours')->where('id', $fixture['tour']->id)->update(['price_amount' => 5000]);
            DB::table('admin_permissions')->where('user_id', $fixture['admin']->id)->update(['flags' => '{}']);
            $parentPid = (int) DB::selectOne('select pg_backend_pid() as pid')->pid;
            $worker = Spec019TranslationProcess::spawn(base_path('tests/Support/TourRevisionWorker.php'),
                [$operation, (string) $fixture['admin']->id, (string) $fixture['tour']->id, (string) $candidate->id, $arrival],
                $output, $error, 'Concurrent moderation permission revocation');
            Spec019TranslationProcess::waitForFile($arrival, 90, 'Moderation worker arrival', $error, $worker);
            $childPid = (int) file_get_contents($arrival);
            $blocked = false;
            $deadline = microtime(true) + 10;
            while (microtime(true) < $deadline && $worker->isAlive()) {
                $blocked = (int) DB::selectOne('select case when ? = any(pg_blocking_pids(?)) then 1 else 0 end as blocked', [$parentPid, $childPid])->blocked === 1;
                if ($blocked) {
                    break;
                }
                usleep(20000);
            }
            expect($blocked)->toBeTrue('The moderator must wait on the actual locked Tour row.');
            DB::commit();
            expect($worker->waitExit(60))->toBe(0)
                ->and(trim(file_get_contents($output)))->toBe('refused:403')
                ->and($candidate->fresh()->state)->toBe('proposed')
                ->and($fixture['tour']->revisionRequests()->count())->toBe(0);
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $worker?->close();
            foreach ([$arrival, $arrival . '.spawned', $output, $error] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($directory);
        }
    });
})->with(['moderation request', 'moderation correction', 'moderation rejection', 'revision approval']);
