<?php

namespace App\Filament\Resources\PostComments\Pages;

use App\Filament\Resources\PostComments\PostCommentResource;
use App\Filament\Support\GifContent;
use App\Model\PostComment;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPostComment extends ViewRecord
{
    protected static string $resource = PostCommentResource::class;

    protected function getActions(): array
    {
        return [EditAction::make()->mutateDataUsing(fn (array $data, Action $action, PostComment $record) => GifContent::prepareAction($data, $action, $record))];
    }
}
