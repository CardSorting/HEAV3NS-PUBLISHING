<?php

namespace App\Filament\Resources\TextModerationItems\Pages;

use App\Filament\Resources\MediaModerationItems\MediaModerationItemResource;
use App\Filament\Resources\TextModerationItems\TextModerationItemResource;
use App\Filament\Widgets\ModerationUnavailableNoticeWidget;
use App\Model\MediaModerationItem;
use App\Model\TextModerationItem;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListTextModerationItems extends ListRecords
{
    protected static string $resource = TextModerationItemResource::class;

    protected function getHeaderWidgets(): array
    {
        return [ModerationUnavailableNoticeWidget::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('mediaReview')
                ->label(__('Media findings'))
                ->icon('heroicon-o-photo')
                ->color('gray')
                ->badge(fn (): ?string => ($count = MediaModerationItem::query()
                    ->whereIn('decision', [MediaModerationItem::DECISION_REVIEW, MediaModerationItem::DECISION_VIOLATION])
                    ->where('admin_outcome', MediaModerationItem::OUTCOME_UNREVIEWED)
                    ->whereNull('content_deleted_at')
                    ->withAvailableSource()
                    ->count()) > 0 ? (string) $count : null)
                ->badgeColor('warning')
                ->url(MediaModerationItemResource::getUrl('index')),
        ];
    }

    public function getTabs(): array
    {
        return [
            'unreviewed' => Tab::make('Needs review')->query(fn ($query) => $query
                ->whereIn('decision', [TextModerationItem::DECISION_REVIEW, TextModerationItem::DECISION_VIOLATION])
                ->where('admin_outcome', TextModerationItem::OUTCOME_UNREVIEWED)
                ->whereNull('content_deleted_at')
                ->withAvailableSource()),
            'confirmed' => Tab::make('Confirmed')->query(fn ($query) => $query
                ->where('admin_outcome', TextModerationItem::OUTCOME_CONFIRMED)
                ->whereNull('content_deleted_at')
                ->withAvailableSource()),
            'likely_violations' => Tab::make('Likely violations')->query(fn ($query) => $query
                ->where('decision', TextModerationItem::DECISION_VIOLATION)
                ->where('admin_outcome', TextModerationItem::OUTCOME_UNREVIEWED)
                ->whereNull('content_deleted_at')
                ->withAvailableSource()),
            'review' => Tab::make('Review threshold')->query(fn ($query) => $query
                ->where('decision', TextModerationItem::DECISION_REVIEW)
                ->where('admin_outcome', TextModerationItem::OUTCOME_UNREVIEWED)
                ->whereNull('content_deleted_at')
                ->withAvailableSource()),
            'processing' => Tab::make('In progress')->query(fn ($query) => $query->whereIn('scan_status', [
                TextModerationItem::STATUS_QUEUED,
                TextModerationItem::STATUS_PROCESSING,
                TextModerationItem::STATUS_RETRYING,
            ])),
            'errors' => Tab::make('Scan issues')->query(fn ($query) => $query->whereIn('scan_status', [
                TextModerationItem::STATUS_FAILED,
                TextModerationItem::STATUS_QUOTA_PAUSED,
                TextModerationItem::STATUS_ACTION_REQUIRED,
                TextModerationItem::STATUS_SKIPPED,
            ])),
            'all' => Tab::make('All items'),
        ];
    }
}
