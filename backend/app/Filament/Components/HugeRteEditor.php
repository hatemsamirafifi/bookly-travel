<?php

namespace App\Filament\Components;

use App\Domains\CMS\Services\HtmlSanitizerService;
use Filament\Forms\Components\Field;

/**
 * Self-hosted rich-text editor (Admin 2.0 Phase G, T-G04).
 *
 * Replaces Filament's CDN-dependent RichEditor with a contenteditable editor
 * backed by `public/vendor/hugerte/hugerte.min.js`. The field keeps the plain
 * `body_*` field names so existing persistence and tests are unchanged, and
 * sanitizes the raw HTML through the server-side allow-list on dehydrate.
 *
 * Toolbar buttons are grouped into profiles:
 *   `full`  — the complete editorial set (default),
 *   `basic` — inline formatting only (stubs for Ulte for ulterior use).
 */
class HugeRteEditor extends Field
{
    protected string $view = 'filament.components.hugerte-editor';

    protected string $toolbarProfile = 'full';

    /**
     * @var array<string, list<string>>
     */
    public static array $toolbars = [
        'full' => [
            'bold',
            'italic',
            'underline',
            'strikethrough',
            'heading1',
            'heading2',
            'heading3',
            'paragraph',
            'orderedlist',
            'unorderedlist',
            'blockquote',
            'link',
            'unlink',
        ],
        'basic' => [
            'bold',
            'italic',
            'underline',
            'paragraph',
            'unorderedlist',
            'link',
        ],
    ];

    public function toolbarProfile(string $profile): static
    {
        $this->toolbarProfile = $profile;

        return $this;
    }

    public function getToolbarProfile(): string
    {
        return $this->toolbarProfile;
    }

    /**
     * @return list<string>
     */
    public function getToolbarButtons(): array
    {
        return static::$toolbars[$this->toolbarProfile] ?? static::$toolbars['full'];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrateStateUsing(function (mixed $state): string {
            return app(HtmlSanitizerService::class)->sanitize(is_string($state) ? $state : null);
        });
    }
}
