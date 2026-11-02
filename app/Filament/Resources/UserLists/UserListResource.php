<?php

namespace App\Filament\Resources\UserLists;

use App\Filament\Resources\UserLists\Pages\CreateUserList;
use App\Filament\Resources\UserLists\Pages\EditUserList;
use App\Filament\Resources\UserLists\Pages\ListUserLists;
use App\Filament\Resources\UserLists\Pages\ViewUserList;
use App\Filament\Resources\UserLists\Pages\ViewUserListMembers;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\UserList;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use UnitEnum;

class UserListResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = UserList::class;

    protected static ?int $navigationSort = 13;

    protected static UnitEnum|string|null $navigationGroup = 'UserLists';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getModelLabel(): string
    {
        return __('admin.resources.user_list.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.user_list.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.user_list.sections.list_details'))
                ->columnSpanFull()
                ->description(__('admin.resources.user_list.sections.list_details_descr'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('admin.resources.user_list.fields.name'))
                        ->placeholder(__('admin.resources.user_list.placeholders.name'))
                        ->required()
                        ->maxLength(100),

                    Select::make('type')
                        ->label(__('admin.resources.user_list.fields.type'))
                        ->required()
                        ->options([
                            UserList::BLOCKED_TYPE => __('admin.resources.user_list.types.blocked'),
                            UserList::FOLLOWING_TYPE => __('admin.resources.user_list.types.following'),
                            UserList::FOLLOWERS_TYPE => __('admin.resources.user_list.types.followers'),
                            UserList::CUSTOM_TYPE => __('admin.resources.user_list.types.custom'),
                        ])
                        ->default(UserList::CUSTOM_TYPE),
                ])
                ->columns(2),

            Section::make(__('admin.resources.user_list.sections.owner'))
                ->columnSpanFull()
                ->description(__('admin.resources.user_list.sections.owner_descr'))
                ->schema([
                    Select::make('user_id')
                        ->label(__('admin.resources.user_list.fields.user_id'))
                        ->relationship('user', 'username')
                        ->searchable()
                        ->required()
                        ->preload(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.user_list.fields.user_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('admin.resources.user_list.fields.name'))
                    ->badge()
                    ->sortable()
                    ->searchable(),
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
                        TextConstraint::make('user.username')->label(__('admin.resources.user_list.fields.user_id')),
                        TextConstraint::make('type')->label(__('admin.resources.user_list.fields.type')),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserLists::route('/'),
            'create' => CreateUserList::route('/create'),
            'edit' => EditUserList::route('/{record}/edit'),
            'view' => ViewUserList::route('/{record}'),
            'members' => ViewUserListMembers::route('/{record}/members'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            // Pages\ViewUserList::class,
            // Pages\EditUserList::class,
            ViewUserListMembers::class,
        ]);
    }
}
