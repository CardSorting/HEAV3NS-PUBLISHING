<?php

namespace App\Filament\Resources\UserMessages;

use App\Filament\Resources\UserMessages\Pages\CreateUserMessage;
use App\Filament\Resources\UserMessages\Pages\EditUserMessage;
use App\Filament\Resources\UserMessages\Pages\ListUserMessages;
use App\Filament\Resources\UserMessages\Pages\ViewUserMessage;
use App\Filament\Resources\UserMessages\Pages\ViewUserMessageAttachments;
use App\Filament\Resources\UserMessages\Pages\ViewUserMessageTransactions;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Filament\Support\GifContent;
use App\Model\UserMessage;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\BooleanConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\NumberConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use UnitEnum;

class UserMessageResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = UserMessage::class;

    protected static ?int $navigationSort = 11;

    protected static UnitEnum|string|null $navigationGroup = 'UserMessages';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getModelLabel(): string
    {
        return __('admin.resources.user_message.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.user_message.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.user_message.sections.user_message_details'))
                ->columnSpanFull()
                ->description(__('admin.resources.user_message.sections.user_message_details_descr'))
                ->schema([
                    Select::make('sender_id')
                        ->label(__('admin.resources.user_message.fields.sender_id'))
                        ->relationship('sender', 'username')
                        ->searchable()
                        ->required()
                        ->preload(true),

                    Select::make('receiver_id')
                        ->label(__('admin.resources.user_message.fields.receiver_id'))
                        ->relationship('receiver', 'username')
                        ->searchable()
                        ->required()
                        ->preload(true),

                    Select::make('story_id')
                        ->label(__('admin.resources.user_message.fields.story_id'))
                        ->relationship('story', 'id') // use id as display by default
                        ->searchable()
                        ->preload(true)
                        ->nullable()
                    ->columnSpanFull(),

                    Textarea::make('message')
                        ->label(__('admin.resources.user_message.fields.message'))
                        ->rows(4)
                        ->columnSpanFull()
                        ->required(fn (Get $get, ?UserMessage $record) => GifContent::reference($record) === null || $get('remove_gif')),

                    ...GifContent::fields('messenger'),

                    TextInput::make('price')
                        ->label(__('admin.resources.user_message.fields.price'))
                        ->numeric()
                        ->prefix('$')
                        ->nullable(),

                    TextInput::make('replyTo')
                        ->label(__('admin.resources.user_message.fields.replyTo'))
                        ->numeric()
                        ->nullable()
                        ->default(0)
                        ->required(),

                    Toggle::make('isSeen')
                        ->label(__('admin.resources.user_message.fields.isSeen'))
                        ->inline(false)
                        ->required(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sender.username')
                    ->label(__('admin.resources.user_message.fields.sender_id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('receiver.username')
                    ->label(__('admin.resources.user_message.fields.receiver_id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('story_id')
                    ->label(__('admin.resources.user_message.fields.story_id'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('message')
                    ->label(__('admin.resources.user_message.fields.message'))
                    ->formatStateUsing(fn ($state, UserMessage $record) => trim((string) $state) === '' && GifContent::reference($record) !== null ? __('GIF') : $state)
                    ->searchable()
                    ->limit(50)
                    ->sortable(),

                Tables\Columns\IconColumn::make('isSeen')
                    ->label(__('admin.resources.user_message.fields.isSeen'))
                    ->boolean(),

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
                        TextConstraint::make('id')->label('Id'),
                        TextConstraint::make('sender.username')->label(__('admin.resources.user_message.fields.sender_id')),
                        TextConstraint::make('receiver.username')->label(__('admin.resources.user_message.fields.receiver_id')),

                        NumberConstraint::make('story_id')->label(__('admin.resources.user_message.fields.story_id')),

                        NumberConstraint::make('price')->label(__('admin.resources.user_message.fields.price')),
                        NumberConstraint::make('replyTo')->label(__('admin.resources.user_message.fields.replyTo')),
                        BooleanConstraint::make('isSeen')->label(__('admin.resources.user_message.fields.isSeen')),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                    ])
                    ->constraintPickerColumns(2),
            ], layout: Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters()
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserMessages::route('/'),
            'create' => CreateUserMessage::route('/create'),
            'edit' => EditUserMessage::route('/{record}/edit'),
            'view' => ViewUserMessage::route('/{record}'),
            'payments' => ViewUserMessageTransactions::route('/{record}/payments'),
            'attachments' => ViewUserMessageAttachments::route('/{record}/attachments'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewUserMessageTransactions::class,
            ViewUserMessageAttachments::class,
        ]);
    }
}
