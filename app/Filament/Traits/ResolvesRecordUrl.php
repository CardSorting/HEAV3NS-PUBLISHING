<?php

namespace App\Filament\Traits;

use Illuminate\Database\Eloquent\Model;

trait ResolvesRecordUrl
{
    public static function resolveRecordUrl(Model $record): ?string
    {
        $user = auth()->user();

        // Prefer edit only when the resource itself allows editing this record.
        // Resources can further restrict editing (or expose a metadata-only
        // edit flow) beyond the generic policy permission.
        $canEdit = static::canEdit($record);

        if (array_key_exists('edit', static::getPages()) && $canEdit) {
            return static::getUrl('edit', ['record' => $record]);
        }

        if (array_key_exists('view', static::getPages()) && $user?->can('view', $record)) {
            return static::getUrl('view', ['record' => $record]);
        }

        // No allowed destination → disable row click
        return null;
    }
}
