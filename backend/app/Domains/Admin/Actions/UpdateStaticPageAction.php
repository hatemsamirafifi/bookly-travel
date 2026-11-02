<?php

namespace App\Domains\Admin\Actions;

use App\Domains\Admin\Models\StaticPage;
use App\Domains\Admin\Services\GovernanceAuditService;
use App\Domains\CMS\Services\ContentRevisionService;
use App\Domains\CMS\Services\HtmlSanitizerService;
use App\Domains\Translation\Services\AutoTranslationService;
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
    public function __construct(
        private readonly GovernanceAuditService $audit,
        private readonly ContentRevisionService $revisions,
        private readonly HtmlSanitizerService $htmlSanitizer,
        private readonly AutoTranslationService $translations,
    ) {}

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
            'meta_title' => $page->meta_title,
            'canonical_url' => $page->canonical_url,
            'scheduled_at' => $page->scheduled_at?->toIso8601String(),
        ];

        return DB::transaction(function () use ($actor, $page, $data, $publish, $before) {
            $willPublish = $publish || ($data['status'] ?? null) === StaticPage::STATUS_PUBLISHED;

            $title = array_key_exists('title', $data)
                ? $this->mergeLocalizedField(
                    $page->title,
                    is_array($data['title']) ? $this->htmlSanitizer->sanitizeLocalizedMap($data['title']) : $data['title']
                )
                : $page->title;

            $rawBody = array_key_exists('body', $data) ? $data['body'] : $page->body;
            $sanitizedBody = match (true) {
                is_array($rawBody) => $this->htmlSanitizer->sanitizeLocalizedMap($rawBody),
                is_string($rawBody) => $this->htmlSanitizer->sanitize($rawBody),
                default => $rawBody,
            };
            $body = array_key_exists('body', $data) && is_array($sanitizedBody)
                ? $this->mergeLocalizedField($page->body, $sanitizedBody)
                : $sanitizedBody;

            $metaDescription = array_key_exists('meta_description', $data)
                ? $this->mergeLocalizedField(
                    $page->meta_description,
                    is_array($data['meta_description'])
                        ? $this->htmlSanitizer->sanitizeLocalizedMap($data['meta_description'])
                        : $data['meta_description']
                )
                : $page->meta_description;

            $metaTitle = array_key_exists('meta_title', $data)
                ? $this->mergeLocalizedField(
                    $page->meta_title,
                    is_array($data['meta_title'])
                        ? $this->htmlSanitizer->sanitizeLocalizedMap($data['meta_title'])
                        : $data['meta_title']
                )
                : $page->meta_title;

            $page->fill([
                'slug' => $data['slug'] ?? $page->slug,
                'title' => $title,
                'body' => $body,
                'meta_description' => $metaDescription,
                'meta_title' => $metaTitle,
                'canonical_url' => array_key_exists('canonical_url', $data)
                    ? $data['canonical_url']
                    : $page->canonical_url,
                'scheduled_at' => array_key_exists('scheduled_at', $data)
                    ? $data['scheduled_at']
                    : $page->scheduled_at,
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

            $this->revisions->recordRevision(
                $page,
                $actor,
                ($action === 'cms.publish' ? 'Publish static page: ' : 'Update static page: ') . $page->slug,
            );

            // Phase H: reconcile field-level translation state and dispatch
            // workers for anything that needs (re)translating.
            $manualEdits = $this->translations->detectManualEdits($before, $page);
            $this->translations->syncAndDispatch($page, $actor, $manualEdits);

            return $page;
        });
    }

    /**
     * Merge incoming locale keys onto the existing localized array so omitted
     * locales are preserved while explicit `null` values clear non-English keys.
     *
     * @return array<string, mixed>|null
     */
    private function mergeLocalizedField(mixed $existing, mixed $incoming): ?array
    {
        if (! is_array($incoming)) {
            return is_array($existing) ? $existing : null;
        }

        $merged = is_array($existing) ? $existing : [];

        foreach ($incoming as $locale => $value) {
            if ($value === null && $locale !== 'en') {
                unset($merged[$locale]);

                continue;
            }

            $merged[$locale] = $value;
        }

        return $merged;
    }
}
