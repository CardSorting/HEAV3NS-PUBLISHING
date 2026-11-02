<?php

namespace App\Filament\Resources\PollUserAnswers\Pages;

use App\Filament\Resources\PollUserAnswers\PollUserAnswerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ViewPollUserAnswers extends ListRecords
{
    protected static string $resource = PollUserAnswerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
