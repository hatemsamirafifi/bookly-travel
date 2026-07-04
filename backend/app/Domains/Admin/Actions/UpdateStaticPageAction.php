<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Models\StaticPage;
use App\Domains\Admin\Services\GovernanceAuditService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Save and/or publish a localized static page (Spec 013, US9, FR-015).
 *
 * Persists the localized JSONB content (`title`/`body`/`meta_description`),
 * stamps `updated_by`, and writes an immutable governance audit entry:
 * `cms.publish` when the page transitions to published, otherwise `cms.update`.
 * The actor is the admin whose id is recorded on `updated_by`.
 */
class UpdateStaticPageAction
{
    public function __construct(private readonly GovernanceAuditService $audit)
    {
    }

    /**
     * @param  array  $data  slug, title (locale=>string), body (locale=>string),
     *                       meta_description (locale=>string|null), optional status.
     * @param  bool  $publish  Publish now (status=published, published_at=now).
     */
    public function execute(User $actor, StaticPage $page, array $data, bool $publish = false): StaticPage
    {
        $before = [
            'slug' => $page->slug,
            'status' => $page->status,
            'title' => $page->title,
            'body' => $page->body,
            'meta_description' => $page->meta_description,
        ];

        return DB::transaction(function () use ($actor, $page, $data, $publish, $before) {
            $willPublish = $publish || ($data['status'] ?? null) === StaticPage::STATUS_PUBLISHED;

            $page->fill([
                'slug' => $data['slug'] ?? $page->slug,
                'title' => $data['title'] ?? $page->title,
                'body' => $data['body'] ?? $page->body,
                'meta_description' => array_key_exists('meta_description', $data)
                    ? $data['meta_description']
                    : $page->meta_description,
                'updated_by' => $actor->id,
            ]);

            if ($willPublish) {
                $page->status = StaticPage::STATUS_PUBLISHED;
                $page->published_at = $page->published_at ?? now();
            } else {
                $page->status = $page->status ?? StaticPage::STATUS_DRAFT;
            }

            $page->save();
            $page->refresh();

            $action = $willPublish && $before['status'] !== StaticPage::STATUS_PUBLISHED
                ? 'cms.publish'
                : 'cms.update';

            $after = [
                'slug' => $page->slug,
                'status' => $page->status,
                'title' => $page->title,
                'body' => $page->body,
                'meta_description' => $page->meta_description,
            ];

            $this->audit->log($actor, $action, $page, $before, $after);

            return $page;
        });
    }
}