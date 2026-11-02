<?php

namespace App\Filament\Resources\PostComments\Pages;

use App\Filament\Resources\PostComments\PostCommentResource;
use App\Filament\Support\GifContent;
use Filament\Resources\Pages\CreateRecord;

class CreatePostComment extends CreateRecord
{
    protected static string $resource = PostCommentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return GifContent::prepare($data);
    }
}
