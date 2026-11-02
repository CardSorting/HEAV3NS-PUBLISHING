<?php

namespace App\Filament\Support;

use App\Model\UserMessage;
use App\Services\GifService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class GifContent
{
    public static function reference(?Model $record): ?array
    {
        try {
            return $record?->exists ? app(GifService::class)->normalize($record->getRawOriginal('gif')) : null;
        } catch (ValidationException) {
            return null;
        }
    }

    public static function fields(string $surface): array
    {
        return [
            Placeholder::make('gif_preview')
                ->label(__('GIF'))
                ->content(fn (?Model $record) => static::preview($record, $surface))
                ->visible(fn (?Model $record) => static::reference($record) !== null)
                ->columnSpanFull(),
            Toggle::make('remove_gif')
                ->label(__('Remove GIF'))
                ->default(false)
                ->visible(fn (?Model $record) => static::reference($record) !== null)
                ->live(),
        ];
    }

    public static function entry(string $surface): TextEntry
    {
        return TextEntry::make('gif_preview')
            ->label(__('GIF'))
            ->state(fn (?Model $record) => static::reference($record) !== null ? __('GIF') : null)
            ->formatStateUsing(fn (?Model $record) => static::preview($record, $surface))
            ->visible(fn (?Model $record) => static::reference($record) !== null)
            ->columnSpanFull();
    }

    public static function preview(?Model $record, string $surface): HtmlString
    {
        $gif = static::reference($record);

        return new HtmlString($gif === null ? '' : view('elements.gif.reference', [
            'gif' => $gif, 'surface' => $surface, 'admin' => true,
        ])->render());
    }

    /** Admin editors may retain or remove an existing GIF, never create or replace one. */
    public static function prepare(array $data, ?Model $record = null, string $statePath = 'data'): array
    {
        $gif = filter_var($data['remove_gif'] ?? false, FILTER_VALIDATE_BOOLEAN) ? null : static::reference($record);
        unset($data['gif'], $data['remove_gif'], $data['gif_preview']);

        $message = array_key_exists('message', $data)
            ? $data['message'] = (string) $data['message']
            : (string) ($record === null ? '' : $record->getAttribute('message'));
        if (trim($message) === '' && $gif === null) {
            throw ValidationException::withMessages([$statePath.'.message' => __('Enter a message or keep the existing GIF.')]);
        }
        if ($gif !== null && $record instanceof UserMessage) {
            $hasAttachments = $record->relationLoaded('attachments')
                ? $record->getRelation('attachments')->isNotEmpty()
                : $record->attachments()->exists();
            if ((float) ($data['price'] ?? $record->price ?? 0) > 0 || $hasAttachments) {
                throw ValidationException::withMessages([$statePath.'.message' => __('GIFs cannot be combined with uploads or paid messages.')]);
            }
        }
        // Explicitly persist a removal; omission would otherwise leave the old reference intact.
        if ($record?->exists) {
            $data['gif'] = $gif;
        }

        return $data;
    }

    public static function prepareAction(array $data, Action $action, ?Model $record = null): array
    {
        return static::prepare($data, $record, 'mountedActions.'.$action->getNestingIndex().'.data');
    }
}
