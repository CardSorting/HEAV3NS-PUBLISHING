<?php

namespace App\Filament\Resources\PollAnswers\Forms;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class CreatePollAnswerForm
{
    public static function schema($pollId = null): array
    {
        return [
            Select::make('poll_id')
                ->relationship('poll', 'id')
                ->searchable()
                ->required()
                ->default($pollId ?? null),
            TextInput::make('answer')
                ->label('Choice')
                ->maxLength(191)
                ->required(),
        ];
    }
}
