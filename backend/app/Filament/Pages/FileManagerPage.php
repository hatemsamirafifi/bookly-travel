<?php

namespace App\Filament\Pages;

use App\Domains\Admin\Services\AdminAuthorizationService;
use App\Domains\Media\Models\MediaAsset;
use App\Domains\Media\Models\MediaFolder;
use App\Domains\Media\Services\FileManagerService;
use App\Domains\Media\Services\MediaUsageTrackerService;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * R2 File Manager for the Media & Storage console (T-F02).
 *
 * Browses the canonical folder tree and every asset's usage state, and
 * exposes folder create/move/delete, asset move/copy/restore, duplicate
 * listing and trashed-asset inspection. Access requires `manage_files`
 * OR `manage_media`.
 */
class FileManagerPage extends Page
{
    protected static string $view = 'filament.pages.file-manager-page';

    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $navigationGroup = 'Media & Storage';

    protected static ?string $navigationLabel = 'File Manager';

    protected static ?string $slug = 'file-manager';

    protected static ?int $navigationSort = 2;

    /** The folder being browsed (null = root). */
    #[Url]
    public ?int $folderId = null;

    public string $tab = 'active';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        $authz = app(AdminAuthorizationService::class);

        return $authz->can($user, 'manage_files')
            || $authz->can($user, 'manage_media');
    }

    public function mount(): void
    {
        $this->tab = 'active';
    }

    public function updatedFolderId(): void
    {
        $this->tab = 'active';
    }

    public function currentFolder(): ?MediaFolder
    {
        return $this->folderId !== null
            ? MediaFolder::query()->find($this->folderId)
            : null;
    }

    public function breadcrumbs(): array
    {
        return app(FileManagerService::class)->breadcrumbs($this->currentFolder());
    }

    /**
     * @return Collection<int, MediaFolder>
     */
    public function folders(): Collection
    {
        return MediaFolder::query()
            ->where('parent_id', $this->folderId)
            ->withCount(['assets', 'children'])
            ->orderBy('slug')
            ->get();
    }

    /**
     * @return Collection<int, MediaAsset>
     */
    public function assets(): Collection
    {
        return MediaAsset::query()
            ->where('folder_id', $this->folderId)
            ->withCount('usages')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return Collection<int, MediaAsset>
     */
    public function trashedAssets(): Collection
    {
        return MediaAsset::query()
            ->onlyTrashed()
            ->orderByDesc('deleted_at')
            ->get();
    }

    /**
     * @return array<string, int>
     */
    public function kpis(): array
    {
        return [
            'total_assets' => MediaAsset::query()->count(),
            'total_folders' => MediaFolder::query()->count(),
            'trashed_assets' => $this->trashedAssets()->count(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createFolder')
                ->label('Create folder')
                ->icon('heroicon-o-folder-plus')
                ->form([
                    TextInput::make('name')
                        ->label('Folder name')
                        ->required(),
                    Select::make('parent_id')
                        ->label('Parent folder')
                        ->options($this->folderOptions())
                        ->default($this->folderId)
                        ->nullable(),
                ])
                ->action(function (array $data): void {
                    try {
                        $folder = app(FileManagerService::class)
                            ->createFolder($this->user(), $data['name'], filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null);

                        Notification::make()->title('Folder created: ' . $folder->path)->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Cannot create folder')->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('moveFolder')
                ->label('Move folder')
                ->icon('heroicon-o-arrows-pointing-out')
                ->color('gray')
                ->visible(fn (): bool => $this->currentFolder() !== null)
                ->form([
                    Select::make('parent_id')
                        ->label('New parent folder')
                        ->options(fn (): array => collect($this->folderOptions())
                            ->reject(fn (string $path, int|string $id): bool => (string) $id === (string) $this->folderId)
                            ->all())
                        ->nullable(),
                ])
                ->action(function (array $data): void {
                    $folder = $this->currentFolder();

                    if ($folder === null) {
                        return;
                    }

                    try {
                        $moved = app(FileManagerService::class)->moveFolder(
                            $this->user(),
                            $folder,
                            filled($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null,
                        );
                        $this->folderId = null;

                        Notification::make()->title('Folder moved to: ' . $moved->path)->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Cannot move folder')->body($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('deleteFolder')
                ->label('Delete folder')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->currentFolder() !== null)
                ->action(function (): void {
                    $folder = $this->currentFolder();

                    if ($folder === null) {
                        return;
                    }

                    try {
                        app(FileManagerService::class)->deleteFolder($this->user(), $folder);
                        $this->folderId = null;

                        Notification::make()->title('Folder deleted')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Cannot delete folder')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    /**
     * Human-readable subfolder indicator (T-E02 style).
     */
    public function assetUsageLabel(MediaAsset $asset): string
    {
        $count = app(MediaUsageTrackerService::class)->usageCount($asset);

        return $count === 0 ? 'Unused' : "Used in {$count} place(s)";
    }

    /**
     * @return array<int, string>
     */
    private function folderOptions(): array
    {
        return MediaFolder::query()
            ->orderBy('path')
            ->get()
            ->mapWithKeys(fn (MediaFolder $folder): array => [$folder->getKey() => $folder->path])
            ->all();
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
