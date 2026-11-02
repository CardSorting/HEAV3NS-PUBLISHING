<?php

namespace App\Filament\Resources\PollUserAnswers;

use App\Filament\Resources\PollUserAnswerResource\Pages;
use App\Filament\Resources\PollUserAnswerResource\RelationManagers;
use App\Filament\Resources\PollUserAnswers\Pages\CreatePollUserAnswer;
use App\Filament\Resources\PollUserAnswers\Pages\EditPollUserAnswer;
use App\Filament\Resources\PollUserAnswers\Pages\ListPollUserAnswers;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\PollUserAnswer;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use BackedEnum;

class PollUserAnswerResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = PollUserAnswer::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = 'User answers';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Forms\Components\TextInput::make('poll_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('user_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('answer_id')
                    ->required()
                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('poll_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('answer_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->recordUrl(fn ($record) => static::resolveRecordUrl($record))
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPollUserAnswers::route('/'),
            'create' => CreatePollUserAnswer::route('/create'),
            'edit' => EditPollUserAnswer::route('/{record}/edit'),
        ];
    }
}
