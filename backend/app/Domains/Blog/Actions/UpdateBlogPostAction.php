<?php

namespace App\Domains\Blog\Actions;

use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\Blog\Jobs\InvalidateBlogCacheJob;
use App\Domains\Blog\Jobs\PublishScheduledBlogPostJob;
use App\Domains\Blog\Jobs\RegenerateSitemapJob;
use App\Domains\Blog\Models\BlogPost;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UpdateBlogPostAction
{
    public function __construct(
        private readonly GovernanceAuditService $auditService
    ) {}

    /**
     * Create or update a BlogPost record.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function execute(User $actor, array $data, ?BlogPost $post = null): BlogPost
    {
        return DB::transaction(function () use ($actor, $data, $post) {
            $isCreate = $post === null;
            $beforeState = $post ? $post->toArray() : null;

            // Generate slug if not provided and EN title is available
            if (empty($data['slug']) && ! empty($data['title']['en'])) {
                $data['slug'] = Str::slug($data['title']['en']);
            }

            // Ensure unique slug if changing or creating
            if (! empty($data['slug'])) {
                $query = BlogPost::where('slug', $data['slug']);
                if ($post) {
                    $query->where('id', '!=', $post->id);
                }
                if ($query->exists()) {
                    throw ValidationException::withMessages([
                        'slug' => ['The slug has already been taken.'],
                    ]);
                }
            }

            $targetStatus = $data['status'] ?? ($post ? $post->status : BlogPost::STATUS_DRAFT);

            // Validate status enum
            $validStatuses = [BlogPost::STATUS_DRAFT, BlogPost::STATUS_PUBLISHED, BlogPost::STATUS_ARCHIVED];
            if (! in_array($targetStatus, $validStatuses, true)) {
                throw ValidationException::withMessages([
                    'status' => ['Invalid status. Must be one of: draft, published, archived.'],
                ]);
            }

            // Publishing validation: EN title + body required
            if ($targetStatus === BlogPost::STATUS_PUBLISHED) {
                $enTitle = $data['title']['en'] ?? ($post?->contentFor('title', 'en'));
                $enBody = $data['body']['en'] ?? ($post?->contentFor('body', 'en'));

                if (empty($enTitle) || empty($enBody)) {
                    throw ValidationException::withMessages([
                        'status' => ['English title and body are required to publish an article.'],
                    ]);
                }

                // If publishing for the first time without published_at, set to now
                // — but only when not scheduled for the future (WR-003/CR-004)
                $scheduledAt = $data['scheduled_at'] ?? ($post?->scheduled_at);
                $isScheduled = $scheduledAt && Carbon::parse($scheduledAt)->isFuture();
                if (! $isScheduled && empty($data['published_at']) && (! $post || ! $post->published_at)) {
                    $data['published_at'] = Carbon::now();
                }
            }

            // If updating status to archived
            if ($targetStatus === BlogPost::STATUS_ARCHIVED) {
                // WR-004: archived requires a prior publish
                if ($post && ! $post->published_at) {
                    throw ValidationException::withMessages([
                        'status' => ['Cannot archive a post that has never been published.'],
                    ]);
                }
                $actionType = 'blog.archive';
            } elseif ($targetStatus === BlogPost::STATUS_PUBLISHED && ($isCreate || $post->status !== BlogPost::STATUS_PUBLISHED)) {
                $actionType = 'blog.publish';
            } elseif ($isCreate) {
                $actionType = 'blog.create';
            } else {
                $actionType = 'blog.update';
            }

            // WR-004: demoting published → draft clears published_at and scheduled_at
            if ($targetStatus === BlogPost::STATUS_DRAFT && $post && $post->status === BlogPost::STATUS_PUBLISHED) {
                $data['published_at'] = null;
                $data['scheduled_at'] = null;
            }

            if ($isCreate) {
                $post = new BlogPost;
            }

            // Extract related tours relation if provided
            $relatedTours = $data['related_tours'] ?? null;
            unset($data['related_tours']);

            // blog_category_id is a single FK (1:N), handled via fill() — no pivot sync needed

            $post->fill($data);
            $post->save();

            // Sync related tours pivot
            if (is_array($relatedTours)) {
                $syncData = [];
                foreach ($relatedTours as $index => $tourItem) {
                    $tourId = is_array($tourItem) ? ($tourItem['tour_id'] ?? $tourItem['id'] ?? null) : $tourItem;
                    $sortOrder = is_array($tourItem) ? ($tourItem['sort_order'] ?? $index) : $index;
                    if ($tourId) {
                        $syncData[$tourId] = ['sort_order' => $sortOrder];
                    }
                }
                $post->relatedTours()->sync($syncData);
            }

            // WR-005: Store only scalar governance fields, not full JSONB content
            $afterState = [
                'status' => $post->status,
                'slug' => $post->slug,
                'published_at' => $post->published_at?->toIso8601String(),
                'scheduled_at' => $post->scheduled_at?->toIso8601String(),
                'is_featured' => (bool) $post->is_featured,
                'blog_category_id' => $post->blog_category_id,
            ];

            $beforeScalars = $beforeState ? [
                'status' => $beforeState['status'] ?? null,
                'slug' => $beforeState['slug'] ?? null,
                'published_at' => isset($beforeState['published_at']) ? $beforeState['published_at'] : null,
                'scheduled_at' => isset($beforeState['scheduled_at']) ? $beforeState['scheduled_at'] : null,
                'is_featured' => $beforeState['is_featured'] ?? null,
                'blog_category_id' => $beforeState['blog_category_id'] ?? null,
            ] : null;

            // Governance Audit log
            $this->auditService->log(
                actor: $actor,
                action: $actionType,
                target: $post,
                before: $beforeScalars,
                after: $afterState,
                metadata: [
                    'status' => $post->status,
                    'slug' => $post->slug,
                ]
            );

            // Invalidate cache
            InvalidateBlogCacheJob::dispatch();
            RegenerateSitemapJob::dispatch();

            // Scheduled publishing dispatch if scheduled in future
            if ($post->status === BlogPost::STATUS_PUBLISHED && $post->scheduled_at && $post->scheduled_at->isFuture()) {
                PublishScheduledBlogPostJob::dispatch($post->id)->delay($post->scheduled_at);
            }

            return $post;
        });
    }
}
