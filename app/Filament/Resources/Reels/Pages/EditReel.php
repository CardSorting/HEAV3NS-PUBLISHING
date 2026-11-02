<?php

namespace App\Filament\Resources\Reels\Pages;

use App\Filament\Resources\Reels\ReelResource;
use App\Model\Reel;
use App\Services\Moderation\PublicationReviewService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** @extends EditRecord<Reel> */
class EditReel extends EditRecord
{
    protected static string $resource = ReelResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Reel {
            unset($data['status']);
            $reel = Reel::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $reel->fill($data)->save();
            app(PublicationReviewService::class)->submit($reel);

            return $reel->refresh();
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            ...ReelResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }
}
