<?php

namespace App\Filament\Resources\ReleaseForms;

use App\Filament\Resources\ReleaseForms\Pages\CreateReleaseForm;
use App\Filament\Resources\ReleaseForms\Pages\EditReleaseForm;
use App\Filament\Resources\ReleaseForms\Pages\ListReleaseForms;
use App\Filament\Resources\ReleaseForms\Pages\ViewReleaseForm;
use App\Filament\Resources\Concerns\DisplaysRecordActions;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\ReleaseForm;
use App\Providers\AttachmentServiceProvider;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\SelectConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use UnitEnum;

class ReleaseFormResource extends Resource
{
    use DisplaysRecordActions;

    use ResolvesRecordUrl;

    protected static ?string $model = ReleaseForm::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-document-text';

    protected static UnitEnum|string|null $navigationGroup = 'Users';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('admin.resources.release_form.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.release_form.plural');
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('admin.resources.release_form.navigation_badge_tooltip');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return SchemaFacade::hasTable('release_forms');
    }

    public static function getNavigationBadge(): ?string
    {
        if (!SchemaFacade::hasTable('release_forms')) {
            return null;
        }

        $count = ReleaseForm::where('status', ReleaseForm::PENDING_STATUS)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.release_form.sections.release_form_details'))
                ->columnSpanFull()
                ->schema([
                    ViewComponent::make('filament.partials.file-preview-wrapper')
                        ->columnSpanFull()
                        ->hiddenOn('create')
                        ->viewData([
                            'record' => ReleaseForm::find(request()->route('record')),
                        ]),

                    Select::make('user_id')
                        ->label(__('admin.resources.release_form.fields.user_id'))
                        ->relationship('user', 'username')
                        ->searchable()
                        ->required()
                        ->preload(true),

                    TextInput::make('title')
                        ->label(__('admin.resources.release_form.fields.title'))
                        ->maxLength(191),

                    FileUpload::make('files')
                        ->label(__('admin.resources.release_form.fields.files'))
                        ->multiple()
                        ->storeFiles(false)
                        ->visibility(AttachmentServiceProvider::getAdminFileUploadVisibility())
                        ->maxSize(4096)
                        ->acceptedFileTypes(
                            AttachmentServiceProvider::extensionsToMimeTypes(
                                AttachmentServiceProvider::filterExtensions('manualPayments')
                            )
                        )
                        ->required()
                        ->columnSpanFull()
                        ->visibleOn('create'),

                    Select::make('status')
                        ->label(__('admin.resources.release_form.fields.status'))
                        ->required()
                        ->options([
                            ReleaseForm::PENDING_STATUS => __('admin.resources.release_form.status_labels.pending'),
                            ReleaseForm::APPROVED_STATUS => __('admin.resources.release_form.status_labels.approved'),
                            ReleaseForm::REJECTED_STATUS => __('admin.resources.release_form.status_labels.rejected'),
                        ])
                        ->default(ReleaseForm::PENDING_STATUS),

                    Select::make('reviewed_by')
                        ->label(__('admin.resources.release_form.fields.reviewed_by'))
                        ->relationship('reviewer', 'username')
                        ->searchable()
                        ->preload(true),

                    Textarea::make('notes')
                        ->label(__('admin.resources.release_form.fields.notes'))
                        ->columnSpanFull(),

                    Textarea::make('rejection_reason')
                        ->label(__('admin.resources.release_form.fields.rejection_reason'))
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.release_form.fields.user_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label(__('admin.resources.release_form.fields.title'))
                    ->searchable()
                    ->limit(40)
                    ->placeholder(__('admin.resources.release_form.label')),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.resources.release_form.fields.status'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => __('admin.resources.release_form.status_labels.'.$state))
                    ->color(fn ($state) => match ($state) {
                        ReleaseForm::APPROVED_STATUS => 'success',
                        ReleaseForm::REJECTED_STATUS => 'danger',
                        ReleaseForm::PENDING_STATUS => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('files')
                    ->label(__('admin.resources.release_form.fields.files'))
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) : 0)
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('reviewer.username')
                    ->label(__('admin.resources.release_form.fields.reviewed_by'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label(__('admin.resources.release_form.fields.reviewed_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                QueryBuilder::make()
                    ->constraints([
                        TextConstraint::make('user.username')->label(__('admin.resources.release_form.fields.user_id')),
                        TextConstraint::make('title')->label(__('admin.resources.release_form.fields.title')),
                        SelectConstraint::make('status')
                            ->label(__('admin.resources.release_form.fields.status'))
                            ->options([
                                ReleaseForm::PENDING_STATUS => __('admin.resources.release_form.status_labels.pending'),
                                ReleaseForm::APPROVED_STATUS => __('admin.resources.release_form.status_labels.approved'),
                                ReleaseForm::REJECTED_STATUS => __('admin.resources.release_form.status_labels.rejected'),
                            ]),
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

    public static function getRecordActions(bool $includeEditAction = true): array
    {
        $actions = [
            Action::make('approve')
                ->label(__('admin.resources.release_form.actions.approve'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record) => $record->status !== ReleaseForm::APPROVED_STATUS)
                ->action(fn (ReleaseForm $record) => $record->update([
                    'status' => ReleaseForm::APPROVED_STATUS,
                    'rejection_reason' => null,
                ])),
            Action::make('reject')
                ->label(__('admin.resources.release_form.actions.reject'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn ($record) => $record->status !== ReleaseForm::REJECTED_STATUS)
                ->form([
                    Textarea::make('rejection_reason')
                        ->label(__('admin.resources.release_form.fields.rejection_reason'))
                        ->required(),
                ])
                ->action(fn (ReleaseForm $record, array $data) => $record->update([
                    'status' => ReleaseForm::REJECTED_STATUS,
                    'rejection_reason' => $data['rejection_reason'],
                ])),
        ];

        if ($includeEditAction) {
            $actions[] = EditAction::make();
        }

        $actions[] = DeleteAction::make();

        return $actions;
    }

    public static function getRecordActionsGroup(bool $includeEditAction = true): ActionGroup
    {
        return ActionGroup::make(static::getRecordActions($includeEditAction))
            ->icon('heroicon-o-ellipsis-horizontal')
            ->iconSize('lg');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReleaseForms::route('/'),
            'create' => CreateReleaseForm::route('/create'),
            'edit' => EditReleaseForm::route('/{record}/edit'),
            'view' => ViewReleaseForm::route('/{record}'),
        ];
    }
}
