<?php

namespace App\Filament\Components;

use App\Domains\CMS\Services\HtmlSanitizerService;
use Filament\Forms\Components\Field;

/**
 * Official HugeRTE integration. HTML is sanitized on save by the existing
 * CMS allow-list. No schema changes and no remote/CDN runtime dependencies.
 */
class HugeRteEditor extends Field
{
    protected string $view = 'filament.components.hugerte-editor';

    protected string $toolbarProfile = 'full';

    /**
     * @var array<string, list<string>>
     */
    public static array $toolbars = [
        'full' => ['undo', 'redo', 'blocks', 'bold', 'italic', 'underline', 'strikethrough', 'forecolor', 'backcolor', 'alignleft', 'aligncenter', 'alignright', 'bullist', 'numlist', 'outdent', 'indent', 'link', 'image', 'table', 'blockquote', 'codesample', 'removeformat', 'searchreplace', 'preview', 'code', 'fullscreen', 'wordcount'],
        'basic' => ['undo', 'redo', 'bold', 'italic', 'underline', 'blocks', 'bullist', 'numlist', 'link'],
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
