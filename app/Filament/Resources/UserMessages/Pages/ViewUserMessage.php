<?php

namespace App\Filament\Resources\UserMessages\Pages;

use App\Filament\Resources\UserMessages\UserMessageResource;
use App\Filament\Support\GifContent;
use App\Model\UserMessage;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUserMessage extends ViewRecord
{
    protected static string $resource = UserMessageResource::class;

    protected function getActions(): array
    {
        return [EditAction::make()->mutateDataUsing(fn (array $data, Action $action, UserMessage $record) => GifContent::prepareAction($data, $action, $record))];
    }
}
