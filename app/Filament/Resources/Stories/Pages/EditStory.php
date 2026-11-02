<?php

namespace App\Filament\Resources\Stories\Pages;

use App\Filament\Resources\Stories\StoryResource;
use App\Model\Story;
use App\Services\Moderation\PublicationReviewService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** @extends EditRecord<Story> */
class EditStory extends EditRecord
{
    protected static string $resource = StoryResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Story {
            unset($data['status'], $data['published_at']);
            $story = Story::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $story->fill($data)->save();
            app(PublicationReviewService::class)->submit($story);

            return $story->refresh();
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            ...StoryResource::getRecordActionsForDisplay($this, $this->getRecord()),
        ];
    }
}
