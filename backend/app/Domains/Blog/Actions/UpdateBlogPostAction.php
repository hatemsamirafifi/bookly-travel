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

            $targetStatus = $data['status'] ?? ($post ? $post->status : 'draft');

            // Publishing validation: EN title + body required
            if ($targetStatus === 'published') {
                $enTitle = $data['title']['en'] ?? ($post?->getTranslation('title', 'en'));
                $enBody = $data['body']['en'] ?? ($post?->getTranslation('body', 'en'));

                if (empty($enTitle) || empty($enBody)) {
                    throw ValidationException::withMessages([
                        'status' => ['English title and body are required to publish an article.'],
                    ]);
                }

                // If publishing for the first time without published_at, set to now
                if (empty($data['published_at']) && (! $post || ! $post->published_at)) {
                    $data['published_at'] = Carbon::now();
                }
            }

            // If updating status to archived
            if ($targetStatus === 'archived') {
                $actionType = 'blog.archive';
            } elseif ($targetStatus === 'published' && ($isCreate || $post?->status !== 'published')) {
                $actionType = 'blog.publish';
            } elseif ($isCreate) {
                $actionType = 'blog.create';
            } else {
                $actionType = 'blog.update';
            }

            if ($isCreate) {
                $post = new BlogPost();
            }

            // Extract related tours relation if provided
            $relatedTours = $data['related_tours'] ?? null;
            unset($data['related_tours']);

            // Extract categories relation if provided
            $categories = $data['categories'] ?? null;
            unset($data['categories']);

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
                $post->tours()->sync($syncData);
            }

            // Sync categories pivot
            if (is_array($categories)) {
                $post->categories()->sync($categories);
            }

            $afterState = $post->fresh()->toArray();

            // Governance Audit log
            $this->auditService->log(
                actor: $actor,
                action: $actionType,
                target: $post,
                before: $beforeState,
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
            if ($post->status === 'published' && $post->scheduled_at && $post->scheduled_at->isFuture()) {
                PublishScheduledBlogPostJob::dispatch($post->id)->delay($post->scheduled_at);
            }

            return $post;
        });
    }
}
