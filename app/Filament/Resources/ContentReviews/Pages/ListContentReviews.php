<?php

namespace App\Filament\Resources\ContentReviews\Pages;

use App\Filament\Resources\ContentReviews\ContentReviewResource;
use App\Filament\Widgets\PublicationReviewUnavailableNoticeWidget;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListContentReviews extends ListRecords
{
    protected static string $resource = ContentReviewResource::class;

    protected function getHeaderWidgets(): array
    {
        return [PublicationReviewUnavailableNoticeWidget::class];
    }

    public function getTabs(): array
    {
        return [
            'pending' => Tab::make(__('Pending review'))->query(fn ($query) => $query->where('status', 'pending')),
            'attention' => Tab::make(__('Needs attention'))->query(fn ($query) => $query
                ->where('status', 'pending')
                ->where(fn ($query) => $query->whereNotNull('attention_reason')
                    ->orWhere(fn ($query) => $query->where('mode', '<>', 'manual')
                        ->whereNull('media_run_id')->whereNull('text_run_id')
                        ->where('created_at', '<', now()->subMinutes(max(5, (int) getSetting('moderation.stale_after_minutes', 15))))))),
            'approved' => Tab::make(__('Approved'))->query(fn ($query) => $query->where('status', 'approved')),
            'rejected' => Tab::make(__('Rejected'))->query(fn ($query) => $query->where('status', 'rejected')),
            'all' => Tab::make(__('All reviews')),
        ];
    }
}
