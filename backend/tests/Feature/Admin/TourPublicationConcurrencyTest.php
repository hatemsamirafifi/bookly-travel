<?php

use App\Domains\Admin\Actions\ApproveTourAction;
use App\Domains\Admin\Models\GovernanceAuditLog;
use App\Domains\Partner\Models\Partner;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Spec019TranslationProcess;

it('serializes publication and owner reassignment against partner suspension', function (string $operation) {
    // A named connection commits fixtures so an independent worker can see them.
    $previousDefault = config('database.default');
    config()->set('database.connections.egm002_owner', config('database.connections.pgsql'));
    DB::setDefaultConnection('egm002_owner');
    $actor = $owner = $partner = $category = $tour = $worker = null;
    $originalPartner = $originalOwner = null;
    $directory = sys_get_temp_dir() . '/egm002-approval-' . uniqid();
    mkdir($directory, 0700);
    $arrival = $directory . '/arrival';
    $output = $directory . '/stdout';
    $error = $directory . '/stderr';

    try {
        $identity = DB::selectOne('select current_database() as db, current_user as usr');
        expect($identity->db)->toBe('bookly_test')->and($identity->usr)->toBe('bookly_test');
        Queue::fake();
        $actor = User::factory()->admin()->create();
        $actor->adminPermission()->create(['flags' => ['manage_tours' => true]]);
        $owner = User::factory()->partner()->create();
        $partner = Partner::create([
            'user_id' => $owner->id, 'role' => 'partner',
            'onboarding_status' => 'approved', 'is_active' => true,
        ]);
        $category = Category::create(['slug' => 'approval-race-' . uniqid(), 'name' => 'Approval race']);
        $tour = Tour::create([
            'partner_id' => $partner->id, 'category_id' => $category->id,
            'slug' => 'approval-race-' . uniqid(), 'source_locale' => 'es',
            'location' => 'Sevilla', 'duration_minutes' => 120, 'duration_label' => '2 hours',
            'price_amount' => 5000, 'status' => 'pending_review',
        ]);
        $tour->translations()->create([
            'locale' => 'es', 'title' => 'Paseo por Sevilla', 'description' => 'Descripción de origen.',
        ]);
        $arguments = [(string) $actor->id, (string) $tour->id, $arrival];
        if ($operation === 'owner reassignment') {
            $originalOwner = User::factory()->partner()->create();
            $originalPartner = Partner::create([
                'user_id' => $originalOwner->id, 'role' => 'partner',
                'onboarding_status' => 'approved', 'is_active' => true,
            ]);
            $tour->update(['partner_id' => $originalPartner->id]);
            app(ApproveTourAction::class)->execute($actor, $tour->fresh());
            $arguments[] = (string) $partner->id;
        }

        DB::beginTransaction();
        $partner->update(['onboarding_status' => 'suspended', 'is_active' => false]);
        $parentPid = (int) DB::selectOne('select pg_backend_pid() as pid')->pid;
        $worker = Spec019TranslationProcess::spawn(
            base_path('tests/Support/TourApprovalWorker.php'),
            $arguments,
            $output, $error, 'Concurrent tour approval',
        );
        Spec019TranslationProcess::waitForFile($arrival, 60, 'Approval worker arrival', $error);
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
        expect($blocked)->toBeTrue('The tour write must wait for the owner governance transaction.');
        DB::commit();

        expect($worker->waitExit(60))->toBe(0);
        if ($operation === 'owner reassignment') {
            expect(trim(file_get_contents($output)))->toBe('refused-form')
                ->and($tour->fresh()->status)->toBe('published')
                ->and($tour->fresh()->partner_id)->toBe($originalPartner->id)
                ->and(GovernanceAuditLog::where('target_id', $tour->id)->count())->toBe(1);
        } else {
            expect(trim(file_get_contents($output)))->toBe('refused:422')
                ->and($tour->fresh()->status)->toBe('pending_review')
                ->and($tour->fresh()->published_at)->toBeNull()
                ->and(GovernanceAuditLog::where('target_id', $tour->id)->count())->toBe(0);
        }
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        $worker?->close();
        if ($tour !== null) {
            DB::table('governance_audit_logs')->where('target_type', 'tour')->where('target_id', $tour->id)->delete();
            $tour->delete();
        }
        $partner?->delete();
        $owner?->delete();
        $originalPartner?->delete();
        $originalOwner?->delete();
        $actor?->adminPermission()->delete();
        $actor?->delete();
        $category?->delete();
        DB::setDefaultConnection($previousDefault);
        DB::purge('egm002_owner');
        foreach ([$arrival, $output, $error] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($directory);
    }
})->with(['approval', 'owner reassignment']);
