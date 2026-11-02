<?php

namespace App\Filament\Resources\ContentReviews\Pages;

use App\Filament\Resources\ContentReviews\ContentReviewResource;
use App\Model\ContentReview;
use App\Services\Moderation\PublicationReviewService;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\Locked;

/** @extends ViewRecord<ContentReview> */
class ViewContentReview extends ViewRecord
{
    protected static string $resource = ContentReviewResource::class;

    #[Locked]
    public string $reviewFingerprint = '';

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record = app(PublicationReviewService::class)->normalizeLegacy($this->getRecord());
        $this->reviewFingerprint = (string) $this->record->fingerprint;
    }

    protected function getHeaderActions(): array
    {
        return ContentReviewResource::reviewActions($this->reviewFingerprint);
    }
}
