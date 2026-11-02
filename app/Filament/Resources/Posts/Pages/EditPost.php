<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\ContentReviews\ContentReviewResource;
use App\Filament\Resources\Posts\PostResource;
use App\Model\Post;
use App\Services\Moderation\PublicationReviewService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;

/** @extends EditRecord<Post> */
class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    #[Locked]
    public string $reviewFingerprint = '';

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($review = $this->getRecord()->contentReview) {
            $review = app(PublicationReviewService::class)->normalizeLegacy($review);
            $this->reviewFingerprint = (string) $review->fingerprint;
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Post {
            $post = Post::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();

            // Dehydration is a UI convenience; forged status changes must be ignored too.
            if ($post->contentReview !== null) {
                unset($data['status']);
            }

            $post->fill($data)->save();
            app(PublicationReviewService::class)->submit($post);
            $post->refresh();
            $this->reviewFingerprint = (string) ($post->contentReview->fingerprint ?? '');

            return $post;
        });
    }

    protected function afterSave(): void
    {
        $this->refreshFormData(['status']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve_content')
                ->label(__('Approve'))->color('success')->icon('heroicon-o-check')
                ->authorize('update')
                ->visible(fn (Post $record) => $record->contentReview?->status === 'pending')
                ->requiresConfirmation()
                ->action(function (Post $record): void {
                    Gate::authorize('update', $record);
                    ContentReviewResource::decide(fn () => app(PublicationReviewService::class)->approve($record->contentReview, auth()->user(), $this->reviewFingerprint));
                    $record->refresh();
                    $this->refreshFormData(['status']);
                    Notification::make()->title(__('Content approved'))->success()->send();
                }),
            Action::make('reject_content')
                ->label(__('Reject'))->color('danger')->icon('heroicon-o-x-mark')
                ->authorize('update')
                ->visible(fn (Post $record) => $record->contentReview?->status === 'pending')
                ->form([Textarea::make('reason')->label(__('Reason shown to creator'))->required()->maxLength(2000)])
                ->action(function (Post $record, array $data): void {
                    Gate::authorize('update', $record);
                    ContentReviewResource::decide(fn () => app(PublicationReviewService::class)->reject($record->contentReview, auth()->user(), $data['reason'], $this->reviewFingerprint));
                    $record->refresh();
                    $this->refreshFormData(['status']);
                    Notification::make()->title(__('Content rejected'))->success()->send();
                }),
            ...PostResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }
}
