<?php

namespace App\Filament\Resources\Shared;

use App\Model\ManualPaymentDocument;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Schema;

class RequestDocuments
{
    public static function make(string $heading): Section
    {
        return Section::make($heading)
            ->visible(fn ($record) => $record && Schema::hasTable('manual_payment_documents') && $record->documents->isNotEmpty())
            ->dehydrated(false)
            ->columnSpanFull()
            ->schema([
                RepeatableEntry::make('request_documents')
                    ->hiddenLabel()
                    ->state(fn ($record) => $record->documents ?? collect())
                    ->table([
                        TableColumn::make(__('Document')),
                        TableColumn::make(__('Field')),
                        TableColumn::make(__('Size')),
                        TableColumn::make(__('Actions')),
                    ])
                    ->schema([
                        TextEntry::make('original_name')->label(__('Document'))->icon('heroicon-o-document-text')->wrap(),
                        TextEntry::make('field_label')->label(__('Field'))->wrap(),
                        TextEntry::make('size')->label(__('Size'))->formatStateUsing(fn ($state) => number_format($state / 1024).' KB'),
                        Group::make([
                            TextEntry::make('preview')
                                ->hiddenLabel()
                                ->state(fn (ManualPaymentDocument $record) => $record->canPreview() ? __('Preview') : null)
                                ->placeholder('—')
                                ->url(fn (ManualPaymentDocument $record) => $record->canPreview()
                                    ? route('manual-payment-documents.download', ['document' => $record, 'preview' => 1]) : null)
                                ->openUrlInNewTab()
                                ->icon(fn (ManualPaymentDocument $record) => $record->canPreview() ? 'heroicon-o-eye' : null)
                                ->color('primary'),
                            TextEntry::make('download')
                                ->hiddenLabel()
                                ->state(__('Download'))
                                ->url(fn (ManualPaymentDocument $record) => route('manual-payment-documents.download', $record))
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('primary'),
                        ])->columns(['default' => 2]),
                    ]),
            ]);
    }
}
