<?php

namespace App\Filament\Resources\GlobalAnnouncements;

use App\Filament\Resources\GlobalAnnouncements\Pages\CreateGlobalAnnouncement;
use App\Filament\Resources\GlobalAnnouncements\Pages\EditGlobalAnnouncement;
use App\Filament\Resources\GlobalAnnouncements\Pages\ListGlobalAnnouncements;
use App\Filament\Resources\GlobalAnnouncements\Pages\ViewGlobalAnnouncement;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\GlobalAnnouncement;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\BooleanConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use UnitEnum;

class GlobalAnnouncementResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = GlobalAnnouncement::class;

    protected static ?string $modelLabel = 'Announcement';

    protected static ?int $navigationSort = 23;

    protected static string|UnitEnum|null $navigationGroup = 'Announcements';

    public static function getModelLabel(): string
    {
        return __('admin.resources.global_announcement.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.global_announcement.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([

                Section::make(__('admin.resources.global_announcement.sections.content'))
                    ->description(__('admin.resources.global_announcement.sections.content_descr'))
                    ->schema([

                        RichEditor::make('content')
                            ->label(__('admin.resources.global_announcement.fields.content'))
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'h3', 'bold', 'italic', 'underline', 'strike', 'link', 'bulletList', 'orderedList', 'blockquote', 'codeBlock',
                            ])
                        ->required(),

                        Select::make('size')
                            ->label(__('admin.resources.global_announcement.fields.size'))
                            ->required()
                            ->options([
                                GlobalAnnouncement::REGULAR_SIZE => __('admin.resources.global_announcement.size_labels.regular'),
                                GlobalAnnouncement::SMALL_SIZE => __('admin.resources.global_announcement.size_labels.small'),
                            ]),

                        DateTimePicker::make('expiring_at')
                            ->label(__('admin.resources.global_announcement.fields.expiring_at')),
                    ])
                    ->columnSpan(2),

                Section::make(__('admin.resources.global_announcement.sections.visibility'))
                    ->description(__('admin.resources.global_announcement.sections.visibility_descr'))
                    ->schema([
                        Toggle::make('is_published')
                            ->label(__('admin.resources.global_announcement.fields.is_published'))
                        ->helperText(__('admin.resources.global_announcement.helpers.is_published')),
                        Toggle::make('is_dismissible')
                            ->label(__('admin.resources.global_announcement.fields.is_dismissible'))
                        ->helperText(__('admin.resources.global_announcement.helpers.is_dismissible'))
                        ->default(true),
                        Toggle::make('is_sticky')
                            ->label(__('admin.resources.global_announcement.fields.is_sticky'))
                        ->helperText(__('admin.resources.global_announcement.helpers.is_sticky'))
                        ->default(true),
                        Toggle::make('is_global')
                            ->label(__('admin.resources.global_announcement.fields.is_global'))
                        ->helperText(__('admin.resources.global_announcement.helpers.is_global'))
                        ->default(true),
                        Toggle::make('id_verified_only')
                            ->label(__('admin.resources.global_announcement.fields.id_verified_only'))
                        ->helperText(__('admin.resources.global_announcement.helpers.id_verified_only')),
                    ])
                    ->columnSpan(1),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('content')
                    ->label(__('admin.resources.global_announcement.fields.content'))
                    ->formatStateUsing(fn (string $state): string => strip_tags($state))
                    ->limit(50),
                Tables\Columns\IconColumn::make('is_published')
                    ->label(__('admin.resources.global_announcement.fields.is_published'))
                    ->boolean()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_global')
                    ->label(__('admin.resources.global_announcement.fields.is_global'))
                    ->boolean()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('id_verified_only')
                    ->label(__('admin.resources.global_announcement.fields.id_verified_only'))
                    ->boolean()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('size')
                    ->label(__('admin.resources.global_announcement.fields.size'))
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => __('admin.resources.global_announcement.size_labels.'.$state)),
                Tables\Columns\TextColumn::make('expiring_at')
                    ->label(__('admin.resources.global_announcement.fields.expiring_at'))
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
                        TextConstraint::make('content')->label(__('admin.resources.global_announcement.fields.content')),
                        TextConstraint::make('size')->label(__('admin.resources.global_announcement.fields.size')),
                        BooleanConstraint::make('is_published')->label(__('admin.resources.global_announcement.fields.is_published')),
                        BooleanConstraint::make('is_dismissible')->label(__('admin.resources.global_announcement.fields.is_dismissible')),
                        BooleanConstraint::make('is_sticky')->label(__('admin.resources.global_announcement.fields.is_sticky')),
                        BooleanConstraint::make('is_global')->label(__('admin.resources.global_announcement.fields.is_global')),
                        BooleanConstraint::make('id_verified_only')->label(__('admin.resources.global_announcement.fields.id_verified_only')),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                        DateConstraint::make('expiring_at')->label(__('admin.common.expiring_at')),
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
            'index' => ListGlobalAnnouncements::route('/'),
            'create' => CreateGlobalAnnouncement::route('/create'),
            'edit' => EditGlobalAnnouncement::route('/{record}/edit'),
            'view' => ViewGlobalAnnouncement::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
//            Pages\ViewGlobalAnnouncement::class,
//            Pages\EditGlobalAnnouncement::class,
        ]);
    }
}
