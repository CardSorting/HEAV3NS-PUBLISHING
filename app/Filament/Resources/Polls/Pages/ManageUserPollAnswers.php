<?php

namespace App\Filament\Resources\Polls\Pages;

use App\Filament\Resources\Polls\PollResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use BackedEnum;
use Illuminate\Support\Facades\Gate;

class ManageUserPollAnswers extends ManageRelatedRecords
{
    public static function canAccess(array $parameters = []): bool
    {
        $parent = $parameters['record'] ?? null;

        return $parent
            && (Gate::allows('view', $parent) || Gate::allows('update', $parent));
    }

    protected static string $resource = PollResource::class;

    protected static string $relationship = 'userAnswers';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-check';

    public function getTitle(): string | Htmlable
    {
        return __('admin.resources.poll.user_poll_answers.label');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.poll.user_poll_answers.label');
    }

    public function getBreadcrumb(): string
    {
        return __('admin.resources.poll.user_poll_answers.label');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')
                ->label(__('admin.resources.poll.user_poll_answers.fields.user_id'))
                ->relationship('user', 'username')
                ->required()
                ->searchable()
                ->preload(true),

            Select::make('answer_id')
                ->label(__('admin.resources.poll.user_poll_answers.fields.answer_id'))
                ->relationship(
                    name: 'answer',
                    titleAttribute: 'answer',
                    modifyQueryUsing: fn ($query) => $query->where(
                        'poll_id',
                        (int) $this->getOwnerRecord()->getKey(),
                    ),
                )
                ->required()
                ->searchable()
                ->preload(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.poll.user_poll_answers.fields.user_id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('answer.answer')
                    ->label(__('admin.resources.poll.user_poll_answers.fields.answer'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('admin.resources.poll.user_poll_answers.actions.create'))
                    ->modalHeading(__('admin.resources.poll.user_poll_answers.actions.create')),
            ])
            ->actions([
                EditAction::make()
                    ->modalHeading(__('admin.resources.poll.user_poll_answers.actions.edit')),
                DeleteAction::make()
                    ->modalHeading(__('admin.resources.poll.user_poll_answers.actions.delete')),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ])
            ->paginated([10, 25, 50]);
    }
}
