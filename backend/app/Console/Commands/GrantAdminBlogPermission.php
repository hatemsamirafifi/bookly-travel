<?php

namespace App\Console\Commands;

use App\Domains\Admin\Models\AdminPermission;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GrantAdminBlogPermission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:grant-blog-permission
                            {--email=* : Explicit administrator email address(es)}
                            {--id=* : Explicit administrator user ID(s)}
                            {--dry-run : Simulate execution without persisting changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Safely and idempotently backfill manage_blog permission for designated administrator accounts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $emails = array_filter((array) $this->option('email'));
        $ids = array_filter((array) $this->option('id'));
        $dryRun = (bool) $this->option('dry-run');

        if (empty($emails) && empty($ids)) {
            $this->error('Operation rejected: You must designate at least one administrator using --email or --id. Indiscriminate mass updates are forbidden.');

            return Command::FAILURE;
        }

        $query = User::query()->where('role', 'admin');

        $query->where(function ($q) use ($emails, $ids) {
            if (! empty($emails)) {
                $q->whereIn('email', $emails);
            }
            if (! empty($ids)) {
                if (! empty($emails)) {
                    $q->orWhereIn('id', $ids);
                } else {
                    $q->whereIn('id', $ids);
                }
            }
        });

        $admins = $query->with('adminPermission')->get();

        if ($admins->isEmpty()) {
            $this->warn('No matching administrator accounts found for the specified identifier(s).');

            return Command::SUCCESS;
        }

        $this->info(sprintf(
            '%s %d administrator account(s)...',
            $dryRun ? '[DRY RUN] Previewing update for' : 'Processing update for',
            $admins->count()
        ));

        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($admins as $admin) {
            $maskedEmail = $this->maskEmail($admin->email);
            $permission = $admin->adminPermission;
            $currentFlags = $permission?->flags ?? [];

            if (! empty($currentFlags['manage_blog'])) {
                $this->line(" - Admin #{$admin->id} ({$maskedEmail}): already has manage_blog permission (no-op).");
                $skippedCount++;

                continue;
            }

            $newFlags = array_merge($currentFlags, ['manage_blog' => true]);

            if ($dryRun) {
                $this->info(" - [DRY RUN] Admin #{$admin->id} ({$maskedEmail}): would be updated with manage_blog: true.");
            } else {
                if ($permission) {
                    $permission->update(['flags' => $newFlags]);
                } else {
                    AdminPermission::create([
                        'user_id' => $admin->id,
                        'flags' => $newFlags,
                    ]);
                }

                Log::info('Admin permission manage_blog backfilled', [
                    'user_id' => $admin->id,
                    'actor' => 'artisan:admin:grant-blog-permission',
                ]);

                $this->info(" - Admin #{$admin->id} ({$maskedEmail}): successfully updated with manage_blog: true.");
            }

            $updatedCount++;
        }

        $summary = sprintf(
            '%s: %d account(s) %s, %d account(s) already up to date.',
            $dryRun ? '[DRY RUN SUMMARY]' : 'Summary',
            $updatedCount,
            $dryRun ? 'would be updated' : 'updated',
            $skippedCount
        );

        $this->info($summary);

        return Command::SUCCESS;
    }

    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }

        $name = $parts[0];
        $domain = $parts[1];

        $maskedName = strlen($name) <= 2
            ? substr($name, 0, 1) . '*'
            : substr($name, 0, 2) . str_repeat('*', max(1, strlen($name) - 2));

        return "{$maskedName}@{$domain}";
    }
}
