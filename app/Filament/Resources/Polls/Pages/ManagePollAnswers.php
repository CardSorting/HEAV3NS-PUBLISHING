<?php

namespace App\Filament\Resources\Polls\Pages;

use App\Filament\Resources\PollAnswers\Forms\CreatePollAnswerForm;
use App\Filament\Resources\Polls\PollResource;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ManageRelatedRecords;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use BackedEnum;
use Illuminate\Support\Facades\Gate;

class ManagePollAnswers extends ManageRelatedRecords
{
    public static function canAccess(array $parameters = []): bool
    {
        $parent = $parameters['record'] ?? null;

        return $parent
            && (Gate::allows('view', $parent) || Gate::allows('update', $parent));
    }

    protected static string $resource = PollResource::class;

    protected static string $relationship = 'answers';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public function getTitle(): string | Htmlable
    {
        return __('admin.resources.poll.poll_answers.poll_choices');
    }

    public function getBreadcrumb(): string
    {
        return __('admin.resources.poll.poll_answers.choices');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.resources.poll.poll_answers.poll_choices');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1) // root grid; keeps inner Sections full-width
            ->components(
                CreatePollAnswerForm::schema((int) $this->getRecord()->getKey())
            );
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->schema([
                    TextEntry::make('answer')
                        ->label(__('admin.resources.poll.fields.answer')),

                    TextEntry::make('created_at')
                        ->dateTime()
                        ->label(__('admin.common.created_at')),

                    TextEntry::make('updated_at')
                        ->dateTime()
                        ->label(__('admin.common.updated_at')),
                ])
                ->columns(1), // (optional — 1 is the default)
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('admin.resources.poll.fields.id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('answer')
                    ->label(__('admin.resources.poll.fields.answer'))
                    ->limit(50)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('admin.resources.poll.poll_answers.actions.create'))
                    ->modalHeading(__('admin.resources.poll.poll_answers.actions.create')),

            ])
            ->actions([
                EditAction::make()
                    ->modalHeading(__('admin.resources.poll.poll_answers.actions.edit')),

                DeleteAction::make()
                    ->modalHeading(__('admin.resources.poll.poll_answers.actions.delete')),

            ])
            ->groupedBulkActions([
                DeleteBulkAction::make(),
            ])
            ->paginated([10, 25, 50]);
    }
}
