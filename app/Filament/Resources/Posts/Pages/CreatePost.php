<?php

namespace App\Filament\Resources\Posts\Pages;

use App\Filament\Resources\Posts\PostResource;
use App\Model\Post;
use App\Services\Moderation\PublicationReviewService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/** @extends CreateRecord<Post> */
class CreatePost extends CreateRecord
{
    protected static string $resource = PostResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $post = parent::handleRecordCreation($data);
            app(PublicationReviewService::class)->submit($post);

            return $post->refresh();
        });
    }
}
