<?php

namespace App\Filament\Resources\FeaturedUsers;

use App\Filament\Resources\FeaturedUsers\Pages\CreateFeaturedUser;
use App\Filament\Resources\FeaturedUsers\Pages\EditFeaturedUser;
use App\Filament\Resources\FeaturedUsers\Pages\ListFeaturedUsers;
use App\Filament\Resources\FeaturedUsers\Pages\ViewFeaturedUser;
use App\Filament\Resources\Concerns\DisplaysRecordActions;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\FeaturedUser;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use UnitEnum;

class FeaturedUserResource extends Resource
{
    use DisplaysRecordActions;

    use ResolvesRecordUrl;

    protected static ?string $model = FeaturedUser::class;

    protected static ?int $navigationSort = 17;

    protected static string|UnitEnum|null $navigationGroup = 'FeaturedUsers';

    public static function getModelLabel(): string
    {
        return __('admin.resources.featured_user.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.featured_user.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.featured_user.sections.main'))
                ->columnSpanFull()
                ->description(__('admin.resources.featured_user.sections.main_descr'))
                ->schema([
                    Select::make('user_id')
                        ->label(__('admin.resources.featured_user.fields.user_id'))
                        ->relationship('user', 'username')
                        ->searchable()
                        ->required()
                        ->placeholder(__('admin.resources.featured_user.fields.user_id'))
                        ->preload(true),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.featured_user.fields.username'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('admin.resources.featured_user.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                QueryBuilder::make()
                    ->constraints([
                        TextConstraint::make('username')->label(__('admin.resources.featured_user.fields.username')),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                    ])
                    ->constraintPickerColumns(2),
            ], layout: Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters()
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
                ->label(__('admin.resources.user.actions.profile_url'))
                ->icon('heroicon-o-globe-alt')
                ->url(fn ($record) => $record->user?->username ? route('profile', ['username' => $record->user->username]) : null)
                ->openUrlInNewTab()
                ->color('info')
                ->visible(fn ($record) => filled($record->user?->username)),
            DeleteAction::make(),
        ];
    }

    public static function getRecordActionsGroup(): ActionGroup
    {
        return ActionGroup::make(static::getRecordActions())
            ->icon('heroicon-o-ellipsis-horizontal')
            ->iconSize('lg');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeaturedUsers::route('/'),
            'create' => CreateFeaturedUser::route('/create'),
            'edit' => EditFeaturedUser::route('/{record}/edit'),
            'view' => ViewFeaturedUser::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([]);
    }
}
