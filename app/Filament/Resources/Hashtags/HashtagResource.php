<?php

namespace App\Filament\Resources\Hashtags;

use App\Filament\Resources\Hashtags\Pages\CreateHashtag;
use App\Filament\Resources\Hashtags\Pages\EditHashtag;
use App\Filament\Resources\Hashtags\Pages\ListHashtags;
use App\Filament\Resources\Hashtags\Pages\ViewHashtag;
use App\Filament\Resources\Hashtags\Pages\ViewHashtagLinks;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\Hashtag;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\NumberConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

class HashtagResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = Hashtag::class;

    protected static ?int $navigationSort = 50;

    protected static UnitEnum|string|null $navigationGroup = 'Content';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    public static function getModelLabel(): string
    {
        return __('admin.resources.hashtag.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.hashtag.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.hashtag.sections.hashtag_info'))
                ->columnSpanFull()
                ->description(__('admin.resources.hashtag.sections.hashtag_info_descr'))
                ->schema([
                    TextInput::make('tag')
                        ->label(__('admin.resources.hashtag.fields.tag'))
                        ->helperText(__('admin.resources.hashtag.fields.tag_helper'))
                        ->required()
                        ->maxLength(64)
                        ->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn (?string $state) => Str::lower(trim((string) $state)))
                        ->formatStateUsing(fn (?string $state) => $state) // keep displayed as stored
                        ->rule('regex:/^[A-Za-z0-9_]{1,64}$/'),
                ])
                ->columns(1),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('tag')
                    ->label(__('admin.resources.hashtag.fields.tag'))
                    ->searchable()
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
                QueryBuilder::make()
                    ->constraints([
                        TextConstraint::make('tag')->label(__('admin.resources.hashtag.fields.tag')),
                        NumberConstraint::make('id')->label('ID'),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                        DateConstraint::make('updated_at')->label(__('admin.common.updated_at')),
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
            ->defaultSort('id', 'desc')
            ->paginated([10, 25, 50]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHashtags::route('/'),
            'create' => CreateHashtag::route('/create'),
            'edit' => EditHashtag::route('/{record}/edit'),
            'view' => ViewHashtag::route('/{record}'),
            'links' => ViewHashtagLinks::route('/{record}/links'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewHashtagLinks::class,
            // Pages\ViewHashtag::class,
            // Pages\EditHashtag::class,
        ]);
    }
}
