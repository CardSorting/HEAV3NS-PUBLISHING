<?php

namespace App\Filament\Resources\Streams\Pages;

use App\Filament\Resources\Streams\StreamResource;
use App\Model\Stream;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListStreams extends ListRecords
{
    protected static string $resource = StreamResource::class;

    public function getTabs(): array
    {
        return [
            null => Tab::make(__('admin.resources.stream.status_labels.all')),
            Stream::IN_PROGRESS_STATUS => Tab::make(__('admin.resources.stream.status_labels.in_progress'))
                ->query(fn ($query) => $query->where('status', Stream::IN_PROGRESS_STATUS)),
            Stream::ENDED_STATUS => Tab::make(__('admin.resources.stream.status_labels.ended'))
                ->query(fn ($query) => $query->where('status', Stream::ENDED_STATUS)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
