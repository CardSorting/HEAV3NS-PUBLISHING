<?php

namespace App\Filament\Resources\StreamMessages;

use App\Filament\Resources\StreamMessages\Pages\CreateStreamMessage;
use App\Filament\Resources\StreamMessages\Pages\EditStreamMessage;
use App\Filament\Resources\StreamMessages\Pages\ListStreamMessages;
use App\Filament\Resources\StreamMessages\Pages\ViewStreamMessage;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\StreamMessage;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use UnitEnum;

class StreamMessageResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = StreamMessage::class;

    protected static UnitEnum|string|null $navigationGroup = 'Streams';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return __('admin.resources.stream_message.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.stream_message.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
                Section::make(__('admin.resources.stream_message.sections.message_details'))
                    ->columnSpanFull()
                    ->description(__('admin.resources.stream_message.sections.message_details'))
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label(__('admin.resources.stream_message.fields.user_id'))
                            ->relationship('user', 'username')
                            ->searchable()
                            ->required()
                            ->helperText(__('admin.resources.stream_message.help.user_id'))
                            ->preload(true),

                        Forms\Components\Select::make('stream_id')
                            ->label(__('admin.resources.stream_message.fields.stream_id'))
                            ->relationship('stream', 'name')
                            ->searchable()
                            ->required()
                            ->helperText(__('admin.resources.stream_message.help.stream_id'))
                            ->preload(true),

                        Forms\Components\Textarea::make('message')
                            ->label(__('admin.resources.stream_message.fields.message'))
                            ->required()
                            ->autosize()
                            ->maxLength(2000)
                            ->columnSpanFull()
                            ->helperText(__('admin.resources.stream_message.help.message')),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.stream_message.fields.user_id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('stream.name')
                    ->label(__('admin.resources.stream_message.fields.stream_id'))
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('message')
                    ->label(__('admin.resources.stream_message.fields.message'))
                    ->wrap()
                    ->limit(50)
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.resources.stream_message.fields.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('admin.resources.stream_message.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->filters([
                QueryBuilder::make()
                    ->constraints([
                        TextConstraint::make('user.username')->label(__('admin.resources.stream_message.fields.user_id')),
                        TextConstraint::make('stream.name')->label(__('admin.resources.stream_message.fields.stream_id')),
                        DateConstraint::make('created_at')->label(__('admin.resources.stream_message.fields.created_at')),
                    ]),
            ])
            ->actions([
                DeleteAction::make(),
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

    public static function getPages(): array
    {
        return [
            'index' => ListStreamMessages::route('/'),
            'create' => CreateStreamMessage::route('/create'),
            'edit' => EditStreamMessage::route('/{record}/edit'),
            'view' => ViewStreamMessage::route('/{record}'),
        ];
    }
}
