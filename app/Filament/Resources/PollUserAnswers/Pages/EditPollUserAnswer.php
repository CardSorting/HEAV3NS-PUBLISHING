<?php

namespace App\Filament\Resources\PollUserAnswers\Pages;

use App\Filament\Resources\PollUserAnswers\PollUserAnswerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPollUserAnswer extends EditRecord
{
    protected static string $resource = PollUserAnswerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
