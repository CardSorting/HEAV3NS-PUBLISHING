<?php

namespace App\Filament\Resources\MediaModerationItems\Pages;

use App\Filament\Resources\MediaModerationItems\MediaModerationItemResource;
use App\Filament\Resources\TextModerationItems\TextModerationItemResource;
use App\Filament\Widgets\ModerationUnavailableNoticeWidget;
use App\Model\MediaModerationItem;
use App\Model\TextModerationItem;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListMediaModerationItems extends ListRecords
{
    protected static string $resource = MediaModerationItemResource::class;

    protected function getHeaderWidgets(): array
    {
        return [ModerationUnavailableNoticeWidget::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('textReview')
                ->label(__('Text findings'))
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->badge(fn (): ?string => ($count = TextModerationItem::query()
                    ->whereIn('decision', [TextModerationItem::DECISION_REVIEW, TextModerationItem::DECISION_VIOLATION])
                    ->where('admin_outcome', TextModerationItem::OUTCOME_UNREVIEWED)
                    ->whereNull('content_deleted_at')
                    ->withAvailableSource()
                    ->count()) > 0 ? (string) $count : null)
                ->badgeColor('warning')
                ->url(TextModerationItemResource::getUrl('index')),
        ];
    }

    public function getTabs(): array
    {
        return [
            'unreviewed' => Tab::make('Needs review')
                ->query(fn ($query) => $query
                    ->whereIn('decision', [MediaModerationItem::DECISION_REVIEW, MediaModerationItem::DECISION_VIOLATION])
                    ->where('admin_outcome', MediaModerationItem::OUTCOME_UNREVIEWED)
                    ->whereNull('content_deleted_at')
                    ->withAvailableSource()),
            'confirmed' => Tab::make('Confirmed')
                ->query(fn ($query) => $query
                    ->where('admin_outcome', MediaModerationItem::OUTCOME_CONFIRMED)
                    ->whereNull('content_deleted_at')
                    ->withAvailableSource()),
            'processing' => Tab::make('In progress')
                ->query(fn ($query) => $query->whereIn('scan_status', [
                    MediaModerationItem::STATUS_QUEUED,
                    MediaModerationItem::STATUS_PROCESSING,
                    MediaModerationItem::STATUS_AWAITING_PROVIDER,
                    MediaModerationItem::STATUS_WAITING_PROVIDER_CAPACITY,
                    MediaModerationItem::STATUS_RETRYING,
                ])),
            'likely_violations' => Tab::make('Likely violations')
                ->query(fn ($query) => $query
                    ->where('decision', MediaModerationItem::DECISION_VIOLATION)
                    ->where('admin_outcome', MediaModerationItem::OUTCOME_UNREVIEWED)
                    ->whereNull('content_deleted_at')
                    ->withAvailableSource()),
            'review' => Tab::make('Review threshold')
                ->query(fn ($query) => $query
                    ->where('decision', MediaModerationItem::DECISION_REVIEW)
                    ->where('admin_outcome', MediaModerationItem::OUTCOME_UNREVIEWED)
                    ->whereNull('content_deleted_at')
                    ->withAvailableSource()),
            'errors' => Tab::make('Scan issues')
                ->query(fn ($query) => $query->whereIn('scan_status', [
                    MediaModerationItem::STATUS_FAILED,
                    MediaModerationItem::STATUS_QUOTA_PAUSED,
                    MediaModerationItem::STATUS_ACTION_REQUIRED,
                    MediaModerationItem::STATUS_SKIPPED,
                ])),
            'all' => Tab::make('All items'),
        ];
    }
}
