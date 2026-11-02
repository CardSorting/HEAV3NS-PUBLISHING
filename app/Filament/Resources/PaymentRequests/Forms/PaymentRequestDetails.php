<?php

namespace App\Filament\Resources\PaymentRequests\Forms;

use App\Filament\Resources\Shared\RequestDocuments;
use App\Model\PaymentRequest;
use App\Services\ManualPayments\ManualPaymentReceivingImages;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;

class PaymentRequestDetails
{
    public static function schema(): array
    {
        return [
            Section::make(__('Submitted payment details'))
                ->visible(fn (?PaymentRequest $record) => !empty($record->details_snapshot))
                ->dehydrated(false)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('manual_method_name')
                        ->label(__('Payment method'))
                        ->state(fn (?PaymentRequest $record) => $record?->details_snapshot['method_name'] ?? null)
                        ->placeholder('—'),
                    TextEntry::make('manual_method_instructions')
                        ->label(__('Instructions'))
                        ->state(fn (?PaymentRequest $record) => $record?->details_snapshot['instructions'] ?? null)
                        ->visible(fn (?PaymentRequest $record) => filled($record?->details_snapshot['instructions'] ?? null))
                        ->formatStateUsing(fn ($state) => new HtmlString(nl2br(e($state))))
                        ->wrap()
                        ->columnSpanFull(),
                    Section::make(__('Site receiving details'))
                        ->contained(false)
                        ->visible(fn (?PaymentRequest $record) => !empty($record?->details_snapshot['receiving_details']))
                        ->columns(['default' => 1, 'md' => 2])
                        ->schema(fn (?PaymentRequest $record) => self::snapshotFields($record, 'receiving_details')),
                    Section::make(__('Customer details'))
                        ->contained(false)
                        ->visible(fn (?PaymentRequest $record) => !empty($record?->details_snapshot['request_fields']))
                        ->columns(['default' => 1, 'md' => 2])
                        ->schema(fn (?PaymentRequest $record) => self::snapshotFields($record, 'request_fields')),
                ]),
            RequestDocuments::make(__('Uploaded documents')),
        ];
    }

    protected static function snapshotFields(?PaymentRequest $record, string $section): array
    {
        $entries = [];
        foreach ($record?->details_snapshot[$section] ?? [] as $index => $item) {
            $value = (string) $item['value'];
            if ($section === 'receiving_details' && ($item['type'] ?? 'text') === 'image') {
                if ($value === '' || ManualPaymentReceivingImages::metadata($value) === null || !$record?->exists) {
                    continue;
                }
                $entries[] = ImageEntry::make('manual_'.$section.'_'.$index)
                    ->label($item['label'])
                    ->state(ManualPaymentReceivingImages::url($value, null, (int) $record->getKey()))
                    ->alt($item['label'])
                    ->imageWidth('min(240px, 100%)')->imageHeight('auto')
                    ->columnSpanFull();
                continue;
            }
            $entries[] = TextEntry::make('manual_'.$section.'_'.$index)
                ->label($item['label'])
                ->state($value)
                ->formatStateUsing(fn ($state) => new HtmlString(nl2br(e($state))))
                ->wrap()
                ->columnSpan(mb_strlen($value) > 48 || str_contains($value, "\n") ? 'full' : 1);
        }

        return $entries;
    }
}
