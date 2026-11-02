<?php

namespace App\Filament\Resources\Withdrawals;

use App\Filament\Resources\Withdrawals\Forms\CreateWithdrawalForm;
use App\Filament\Resources\Withdrawals\Pages\CreateWithdrawal;
use App\Filament\Resources\Withdrawals\Pages\EditWithdrawal;
use App\Filament\Resources\Withdrawals\Pages\ListWithdrawals;
use App\Filament\Resources\Withdrawals\Pages\ViewWithdrawal;
use App\Filament\Resources\Concerns\DisplaysRecordActions;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\Withdrawal;
use App\Providers\WithdrawalsServiceProvider;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\NumberConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\SelectConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Excel;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Columns\Column;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use UnitEnum;

class WithdrawalResource extends Resource
{
    use DisplaysRecordActions;

    use ResolvesRecordUrl;

    protected static ?string $model = Withdrawal::class;

    protected static UnitEnum|string|null $navigationGroup = 'Withdrawals';

    protected static ?int $navigationSort = 0;

    public static function getModelLabel(): string
    {
        return __('admin.resources.withdrawal.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.withdrawal.plural');
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('admin.resources.withdrawal.navigation_badge_tooltip');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Withdrawal::where('status', Withdrawal::REQUESTED_STATUS)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(CreateWithdrawalForm::schema())->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('admin.resources.withdrawal.fields.id'))
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('admin.resources.withdrawal.fields.username'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('admin.resources.withdrawal.fields.amount'))
                    ->badge()
                    ->numeric()
                    ->money(getSetting('payments.currency_code'))
                    ->color('gray')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money(),
                    ]),
                Tables\Columns\TextColumn::make('fee')
                    ->label(__('admin.resources.withdrawal.fields.fee'))
                    ->badge()
                    ->money(getSetting('payments.currency_code'))
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.resources.withdrawal.fields.status'))
                    ->badge()
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state) => __('admin.resources.withdrawal.status_labels.'.strtolower($state)))
                    ->color(fn ($state) => match ($state) {
                        'approved' => 'success',
                        'requested' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label(__('admin.resources.withdrawal.fields.payment_method'))
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('admin.common.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                QueryBuilder::make()
                    ->constraints([
                        SelectConstraint::make('status')
                            ->label(__('admin.resources.withdrawal.fields.status'))
                            ->options(CreateWithdrawalForm::getTranslatedStatuses()),

                        TextConstraint::make('user.username')->label(__('admin.resources.withdrawal.fields.username')),
                        NumberConstraint::make('amount')->label(__('admin.resources.withdrawal.fields.amount')),
                        NumberConstraint::make('fee')->label(__('admin.resources.withdrawal.fields.fee')),
                        TextConstraint::make('payment_method')->label(__('admin.resources.withdrawal.fields.payment_method')),
                        TextConstraint::make('payout_method_key')->label(__('admin.resources.withdrawal.fields.payout_method_key')),
                        TextConstraint::make('payment_identifier')->label(__('admin.resources.withdrawal.fields.payment_identifier')),
                        TextConstraint::make('stripe_payout_id')->label(__('admin.resources.withdrawal.fields.stripe_payout_id')),
                        TextConstraint::make('stripe_transfer_id')->label(__('admin.resources.withdrawal.fields.stripe_transfer_id')),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                        DateConstraint::make('updated_at')->label(__('admin.common.updated_at')),
                    ])
                    ->constraintPickerColumns(2),
            ], layout: Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters()
            ->actions([
//                Tables\Actions\EditAction::make(),
                ...static::getRecordActionsForDisplay(),
            ])
            ->toolbarActions([
                ExportBulkAction::make()->exports([
                    ExcelExport::make('csv_for_accounting')
                        ->label(__('admin.resources.withdrawal.export.csv'))
                        ->withWriterType(Excel::CSV)
                        ->withFilename(fn () => 'withdrawals-'.now()->format('Y-m-d'))
                        ->withColumns([
                            Column::make('id')->heading(__('admin.common.id')),
                            Column::make('user.username')->heading(__('admin.resources.withdrawal.fields.username')),
                            Column::make('user.name')->heading(__('admin.resources.user.fields.name')),

                            Column::make('amount')->heading(__('admin.resources.withdrawal.export.gross')),
                            Column::make('fee')->heading(__('admin.resources.withdrawal.fields.fee')),
                            Column::make('net_amount')->heading(__('admin.resources.withdrawal.export.net')),

                            Column::make('payment_method')->heading(__('admin.resources.withdrawal.export.method')),
                            Column::make('payment_identifier')->heading(__('admin.resources.withdrawal.export.identifier')),
                            Column::make('payout_account_label')->heading(__('admin.resources.withdrawal.export.saved_account')),
                            Column::make('payout_snapshot_summary')->heading(__('admin.resources.withdrawal.export.payout_details')),

                            Column::make('status')->formatStateUsing(fn ($state) => __('admin.resources.withdrawal.status_labels.'.strtolower((string) $state))),
                            Column::make('created_at')->heading(__('admin.common.created_at')),
                        ]),
                ]),

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
            Action::make('approve_withdrawal')
                ->label(__('admin.resources.withdrawal.actions.approve'))
                ->icon('heroicon-o-check-circle')
                ->action(function ($record, $livewire) {
                    $response = WithdrawalsServiceProvider::approve($record->id);

                    Notification::make()
                        ->title($response['message'] ?? $response['error'])
                        ->{ $response['success'] ? 'success' : 'danger' }()
                        ->send();
                })
                ->requiresConfirmation()
                ->color('success'),

            Action::make('reject_withdrawal')
                ->label(__('admin.resources.withdrawal.actions.reject'))
                ->icon('heroicon-o-x-circle')
                ->action(function ($record, $livewire) {
                    $response = WithdrawalsServiceProvider::reject($record->id);

                    Notification::make()
                        ->title($response['message'] ?? $response['error'])
                        ->{ $response['success'] ? 'success' : 'danger' }()
                        ->send();
                })
                ->requiresConfirmation()
                ->color('danger'),

            Action::make('profile_url')
                ->label(__('admin.resources.user.actions.profile_url'))
                ->icon('heroicon-o-globe-alt')
                ->url(fn ($record) => route('profile', ['username' => $record->user->username]))
                ->openUrlInNewTab()
                ->color('info'),

            DeleteAction::make(),
        ];
    }

    public static function getRecordActionsGroup(): ActionGroup
    {
        return ActionGroup::make(static::getRecordActions())
            ->icon('heroicon-o-ellipsis-horizontal')
            ->visible(fn () => Auth::user()?->hasRole('admin'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWithdrawals::route('/'),
            'create' => CreateWithdrawal::route('/create'),
            'edit' => EditWithdrawal::route('/{record}/edit'),
            'view' => ViewWithdrawal::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([]);
    }
}
