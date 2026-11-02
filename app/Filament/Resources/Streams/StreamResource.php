<?php

namespace App\Filament\Resources\Streams;

use App\Filament\Resources\Streams\Pages\CreateStream;
use App\Filament\Resources\Streams\Pages\EditStream;
use App\Filament\Resources\Streams\Pages\ListStreams;
use App\Filament\Resources\Streams\Pages\ViewStream;
use App\Filament\Resources\Concerns\DisplaysRecordActions;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\Stream;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\BooleanConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\NumberConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\SelectConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;

class StreamResource extends Resource
{
    use DisplaysRecordActions;

    use ResolvesRecordUrl;

    protected static ?string $model = Stream::class;

    public static function getModelLabel(): string
    {
        return __('admin.resources.stream.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.stream.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.stream.sections.stream_details'))
                ->columnSpanFull()
                ->description(__('admin.resources.stream.sections.stream_details_descr'))
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('admin.resources.stream.fields.name'))
                        ->maxLength(191)
                        ->required(),
                    Forms\Components\TextInput::make('slug')
                        ->label(__('admin.resources.stream.fields.slug'))
                        ->maxLength(191)
                        ->required(),
                    Forms\Components\TextInput::make('price')
                        ->label(__('admin.resources.stream.fields.price'))
                        ->numeric()
                        ->prefix('$')
                        ->default(0),
                    Select::make('user_id')
                        ->label(__('admin.resources.stream.fields.user_id'))
                        ->relationship('user', 'username')
                        ->searchable()
                        ->required()
                        ->placeholder('Select user')
                        ->preload(true),
                    Forms\Components\FileUpload::make('poster')
                        ->label(__('admin.resources.stream.fields.poster'))
                        ->image()
                        ->directory('stream-posters')
                        ->preserveFilenames(),
                    Select::make('status')
                        ->label(__('admin.resources.stream.fields.status'))
                        ->required()
                        ->options([
                            Stream::IN_PROGRESS_STATUS => __('admin.resources.stream.status_labels.in_progress'),
                            Stream::ENDED_STATUS => __('admin.resources.stream.status_labels.ended'),
                            Stream::DELETED_STATUS => __('admin.resources.stream.status_labels.deleted'),
                        ])
                        ->native(false),
                    Forms\Components\Toggle::make('requires_subscription')
                        ->label(__('admin.resources.stream.fields.requires_subscription')),
                    Forms\Components\Toggle::make('is_public')
                        ->label(__('admin.resources.stream.fields.is_public')),
                    Forms\Components\Toggle::make('sent_expiring_reminder')
                        ->label(__('admin.resources.stream.fields.sent_expiring_reminder')),
                ]),

            Section::make(__('admin.resources.stream.sections.stream_source'))
                ->columnSpanFull()
                ->description(__('admin.resources.stream.sections.stream_source_descr'))
                ->columns(2)
                ->schema([
                    Select::make('driver')
                        ->label(__('admin.resources.stream.fields.driver'))
                        ->required()
                        ->options([
                            1 => __('admin.resources.stream.driver_labels.1'),
                            2 => __('admin.resources.stream.driver_labels.2'),
                        ])
                        ->native(false)
                        ->default(1),
                    Forms\Components\TextInput::make('pushr_id')
                        ->label(__('admin.resources.stream.fields.pushr_id'))
                        ->numeric(),
                    Forms\Components\TextInput::make('rtmp_key')
                        ->label(__('admin.resources.stream.fields.rtmp_key'))
                        ->maxLength(191),
                    Forms\Components\TextInput::make('rtmp_server')
                        ->label(__('admin.resources.stream.fields.rtmp_server'))
                        ->maxLength(191),
                    Forms\Components\TextInput::make('hls_link')
                        ->label(__('admin.resources.stream.fields.hls_link'))
                        ->maxLength(191),
                    Forms\Components\TextInput::make('vod_link')
                        ->label(__('admin.resources.stream.fields.vod_link'))
                        ->maxLength(191),
                ]),

            Section::make(__('admin.resources.stream.sections.advanced_metadata'))
                ->columnSpanFull()
                ->columns(1)
                ->schema([
                    Forms\Components\Textarea::make('settings')
                        ->label(__('admin.resources.stream.fields.settings'))
                        ->columnSpanFull()
                        ->formatStateUsing(fn ($state) => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : $state)
                        ->dehydrateStateUsing(fn ($state) => is_string($state) ? json_decode($state, true) : $state),

                    Forms\Components\DateTimePicker::make('ended_at')
                        ->label(__('admin.resources.stream.fields.ended_at')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.stream.fields.user_id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.resources.stream.fields.name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('price')
                    ->label(__('admin.resources.stream.fields.price'))
                    ->numeric()
                    ->money(getSetting('payments.currency_code'))
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state) => __('admin.resources.stream.status_labels.'.str_replace('-', '_', $state)))
                    ->color(fn ($state) => match ($state) {
                        'in-progress' => 'info',
                        'ended' => 'gray',
                        'deleted' => 'danger',
                        default => 'secondary',
                    }),

                Tables\Columns\IconColumn::make('requires_subscription')
                    ->label(__('admin.resources.stream.fields.requires_subscription'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_public')
                    ->label(__('admin.resources.stream.fields.is_public'))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('rtmp_key')
                    ->label(__('admin.resources.stream.fields.rtmp_key'))
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('hls_link')
                    ->label(__('admin.resources.stream.fields.hls_link'))
                    ->url(fn ($record) => $record->hls_link)
                    ->openUrlInNewTab()
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('vod_link')
                    ->label(__('admin.resources.stream.fields.vod_link'))
                    ->url(fn ($record) => $record->vod_link)
                    ->openUrlInNewTab()
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('ended_at')
                    ->label(__('admin.resources.stream.fields.ended_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                QueryBuilder::make()
                    ->constraints([
                        TextConstraint::make('user.username')->label(__('admin.resources.stream.fields.user_id')),
                        TextConstraint::make('name')->label(__('admin.resources.stream.fields.name')),
                        NumberConstraint::make('price')->label(__('admin.resources.stream.fields.price')),
                        SelectConstraint::make('status')
                            ->label(__('admin.resources.stream.fields.status'))
                            ->options([
                                Stream::IN_PROGRESS_STATUS => __('admin.resources.stream.status_labels.in_progress'),
                                Stream::ENDED_STATUS => __('admin.resources.stream.status_labels.ended'),
                                Stream::DELETED_STATUS => __('admin.resources.stream.status_labels.deleted'),
                            ]),
                        BooleanConstraint::make('requires_subscription')->label(__('admin.resources.stream.fields.requires_subscription')),
                        BooleanConstraint::make('is_public')->label(__('admin.resources.stream.fields.is_public')),
                        TextConstraint::make('rtmp_key')->label(__('admin.resources.stream.fields.rtmp_key')),
                        TextConstraint::make('hls_link')->label(__('admin.resources.stream.fields.hls_link')),
                        TextConstraint::make('vod_link')->label(__('admin.resources.stream.fields.vod_link')),
                        DateConstraint::make('ended_at')->label(__('admin.resources.stream.fields.ended_at')),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                        DateConstraint::make('updated_at')->label(__('admin.common.updated_at')),
                    ]),
            ])
            ->actions([
                ...static::getRecordActionsForDisplay(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->recordUrl(fn ($record) => static::resolveRecordUrl($record))
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getRecordActions(): array
    {
        return [
            Action::make('profile_url')
                ->label('Stream URL')
                ->icon('heroicon-o-globe-alt')
                ->url(fn ($record) => route('public.stream.get', ['streamID' => $record->id, 'slug' => $record->slug]))
                ->openUrlInNewTab()
                ->color('info'),
            DeleteAction::make(),
        ];
    }

    public static function getRecordActionsGroup(): ActionGroup
    {
        return ActionGroup::make(static::getRecordActions())
            ->icon('heroicon-o-ellipsis-horizontal');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStreams::route('/'),
            'create' => CreateStream::route('/create'),
            'edit' => EditStream::route('/{record}/edit'),
            'view' => ViewStream::route('/{record}'),
        ];
    }
}
