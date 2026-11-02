<?php

namespace App\Filament\Resources\PostComments\Pages;

use App\Filament\Resources\PostComments\PostCommentResource;
use App\Filament\Support\GifContent;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPostComment extends EditRecord
{
    protected static string $resource = PostCommentResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return GifContent::prepare($data, $this->getRecord());
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
