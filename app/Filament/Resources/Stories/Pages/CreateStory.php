<?php

namespace App\Filament\Resources\Stories\Pages;

use App\Filament\Resources\Stories\StoryResource;
use App\Model\Story;
use App\Services\Moderation\PublicationReviewService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** @extends CreateRecord<Story> */
class CreateStory extends CreateRecord
{
    protected static string $resource = StoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            unset($data['status'], $data['published_at']);
            $story = parent::handleRecordCreation($data);
            app(PublicationReviewService::class)->submit($story);

            return $story->refresh();
        });
    }
}
