<?php

namespace App\Filament\Resources\Attachments\Concerns;

use App\Model\Attachment;
use App\Model\Post;
use App\Model\Reel;
use App\Model\Story;
use App\Model\UserMessage;
use App\Filament\Resources\Attachments\Forms\CreateAttachmentForm;
use App\Services\Media\AttachmentPlacementService;
use App\Services\Moderation\PublicationReviewService;
use App\Providers\AttachmentServiceProvider;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

trait ManagesAttachmentPlacements
{
    /**
     * @return Post|Reel|Story|UserMessage
     */
    protected function attachmentParent(): Post|Reel|Story|UserMessage
    {
        $parent = $this->getRecord();

        if (!$parent instanceof Post && !$parent instanceof Reel && !$parent instanceof Story && !$parent instanceof UserMessage) {
            throw new \LogicException('Attachment placement management requires a supported content record.');
        }

        return $parent;
    }

    protected function manageMediaAction(): Action
    {
        return Action::make('manage_media')
            ->label(__('Manage media'))
            ->icon('heroicon-o-paper-clip')
            ->authorize(fn (): bool => Gate::allows('update', $this->getRecord()))
            ->fillForm(fn (): array => ['attachment_ids' => $this->currentAttachmentIds()])
            ->schema([
                Select::make('attachment_ids')
                    ->label(__('Media from the owner’s Vault'))
                    ->multiple()
                    ->searchable()
                    ->helperText(__('Changing this selection affects only this content. Files remain in the Vault.'))
                    ->options(fn (): array => $this->selectableMedia()->latest('attachments.created_at')->limit(50)
                        ->get()->mapWithKeys(fn (Attachment $attachment): array => [$attachment->id => $this->mediaLabel($attachment)])->all())
                    ->getSearchResultsUsing(fn (string $search): array => $this->selectableMedia()
                        ->where(fn (Builder $query) => $query->where('filename', 'like', '%'.$search.'%')->orWhere('id', $search))
                        ->limit(50)->get()->mapWithKeys(fn (Attachment $attachment): array => [$attachment->id => $this->mediaLabel($attachment)])->all())
                    ->getOptionLabelsUsing(fn (array $values): array => $this->selectableMedia()->whereIn('id', $values)
                        ->get()->mapWithKeys(fn (Attachment $attachment): array => [$attachment->id => $this->mediaLabel($attachment)])->all()),
            ])
            ->action(function (array $data): void {
                $this->syncSelectedMedia($data['attachment_ids'] ?? []);
            });
    }

    protected function addAttachmentAction(): Action
    {
        return Action::make('add_attachment')
            ->label(__('Add attachment'))
            ->icon('heroicon-o-arrow-up-tray')
            ->authorize(fn (): bool => Gate::allows('update', $this->getRecord()))
            ->schema([
                FileUpload::make('file')
                    ->label(__('Attachment'))
                    ->storeFiles(false)
                    ->required()
                    ->maxSize(AttachmentServiceProvider::getUploadMaxFilesize())
                    ->acceptedFileTypes(CreateAttachmentForm::mediaMimeTypes()),
            ])
            ->action(function (array $data): void {
                Gate::authorize('update', $this->getRecord());
                $file = $data['file'] ?? null;
                if (!$file) {
                    return;
                }

                CreateAttachmentForm::validateMediaUpload($file);

                $attachment = AttachmentServiceProvider::createAttachment(
                    $file,
                    'assets/attachments',
                    true,
                    true,
                    true,
                    $this->mediaOwnerId(),
                    true,
                );

                $this->syncSelectedMedia([$attachment->getKey(), ...$this->currentAttachmentIds()]);
            });
    }

    protected function detachMediaAction(): Action
    {
        return Action::make('detach_media')
            ->label(__('Detach'))
            ->icon('heroicon-o-link-slash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('Remove this media from this content only. The Vault file and other uses are retained.'))
            ->authorize(fn (): bool => Gate::allows('update', $this->getRecord()))
            ->action(function (Attachment $record): void {
                $this->syncSelectedMedia(array_values(array_filter(
                    $this->currentAttachmentIds(),
                    fn (string $id): bool => $id !== (string) $record->id,
                )));
            });
    }

    protected function syncSelectedMedia(array $ids): void
    {
        Gate::authorize('update', $this->getRecord());
        DB::transaction(function () use ($ids): void {
            $parent = $this->attachmentParent();
            app(AttachmentPlacementService::class)->sync($parent, $ids, $this->mediaOwnerId());

            if ($parent instanceof Post || $parent instanceof Reel || $parent instanceof Story) {
                app(PublicationReviewService::class)->submit($parent);
            }
        });
        $this->getRecord()->unsetRelation('attachments');
        $this->resetTable();
    }

    protected function currentAttachmentIds(): array
    {
        return $this->attachmentParent()->attachments()->pluck('attachments.id')->map(fn ($id): string => (string) $id)->all();
    }

    protected function mediaOwnerId(): int
    {
        $parent = $this->attachmentParent();

        return (int) ($parent instanceof UserMessage ? $parent->sender_id : $parent->user_id);
    }

    /** @return Builder<Attachment> */
    protected function selectableMedia(): Builder
    {
        return Attachment::query()->where('user_id', $this->mediaOwnerId())
            ->whereNotNull('vaulted_at')->whereNull('sound_id')
            ->where(fn (Builder $query) => $query->whereNull('vault_archived_at')->orWhereIn('id', $this->currentAttachmentIds()));
    }

    protected function mediaLabel(Attachment $attachment): string
    {
        return strtoupper((string) $attachment->type).' · '.(string) ($attachment->original_name ?: basename((string) $attachment->filename));
    }
}
