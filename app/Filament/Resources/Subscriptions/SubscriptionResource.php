<?php

namespace App\Filament\Resources\Subscriptions;

use App\Filament\Resources\Subscriptions\Forms\CreateSubscriptionForm;
use App\Filament\Resources\Subscriptions\Pages\CreateSubscription;
use App\Filament\Resources\Subscriptions\Pages\EditSubscription;
use App\Filament\Resources\Subscriptions\Pages\ListSubscriptions;
use App\Filament\Resources\Subscriptions\Pages\ViewSubscription;
use App\Filament\Resources\Subscriptions\Pages\ViewSubscriptionTransactions;
use App\Filament\Resources\Subscriptions\Widgets\SubscriptionStats;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\Subscription;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\NumberConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\SelectConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use UnitEnum;

class SubscriptionResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = Subscription::class;

    protected static UnitEnum|string|null $navigationGroup = 'Subscriptions';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?int $navigationSort = 0;

    public static function getModelLabel(): string
    {
        return __('admin.resources.subscription.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.subscription.plural');
    }

    public static function getWidgets(): array
    {
        return [
            SubscriptionStats::class,
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->schema(CreateSubscriptionForm::schema()),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('subscriber.username')
                    ->label(__('admin.resources.subscription.fields.sender_user_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.username')
                    ->label(__('admin.resources.subscription.fields.recipient_user_id'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('admin.resources.subscription.fields.amount'))
                    ->badge()
                    ->money()
                    ->color('gray')
                    ->searchable()
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money(),
                    ]),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('admin.resources.subscription.fields.status'))
                    ->sortable()
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'completed' => 'success',
                        'suspended' => 'warning',
                        'canceled', 'failed' => 'danger',
                        'expired', 'pending' => 'gray',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn ($state) => __('admin.resources.subscription.status_labels.'.str_replace('-', '_', $state)))
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('admin.resources.subscription.fields.type'))
                    ->searchable()
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn ($state) => __('admin.resources.subscription.type_labels.'.str_replace('-', '_', $state)))
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('provider')
                    ->label(__('admin.resources.subscription.fields.provider'))
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn ($state) => ucfirst($state))
                    ->color('warning')
                    ->searchable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('admin.resources.subscription.fields.expires_at'))
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
                        SelectConstraint::make('status')
                            ->label(__('admin.resources.subscription.fields.status'))
                            ->options(CreateSubscriptionForm::getSubscriptionStatus()),

                        SelectConstraint::make('type')
                            ->label(__('admin.resources.subscription.fields.type'))
                            ->options(CreateSubscriptionForm::getSubscriptionTypes()),

                        TextConstraint::make('subscriber.username')->label(__('admin.resources.subscription.fields.sender_user_id')),
                        TextConstraint::make('creator.username')->label(__('admin.resources.subscription.fields.recipient_user_id')),
                        NumberConstraint::make('amount')->label(__('admin.resources.subscription.fields.amount'))
                            ->icon('heroicon-m-currency-dollar'),
                        TextConstraint::make('provider')->label(__('admin.resources.subscription.fields.provider')),
                        TextConstraint::make('paypal_agreement_id')->label(__('admin.resources.subscription.fields.paypal_agreement_id')),
                        TextConstraint::make('stripe_subscription_id')->label(__('admin.resources.subscription.fields.stripe_subscription_id')),
                        TextConstraint::make('paypal_plan_id')->label(__('admin.resources.subscription.fields.paypal_plan_id')),
                        TextConstraint::make('ccbill_subscription_id')->label(__('admin.resources.subscription.fields.ccbill_subscription_id')),
                        TextConstraint::make('verotel_sale_id')->label(__('admin.resources.subscription.fields.verotel_sale_id')),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                        DateConstraint::make('updated_at')->label(__('admin.common.updated_at')),
                        DateConstraint::make('expired_at')->label(__('admin.common.expiring_at')),
                        DateConstraint::make('canceled_at')->label(__('admin.common.canceled_at')),
                    ])
                    ->constraintPickerColumns(2),
            ], layout: Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters()
            ->actions([
//                Tables\Actions\EditAction::make(),
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
            'index' => ListSubscriptions::route('/'),
            'create' => CreateSubscription::route('/create'),
            'edit' => EditSubscription::route('/{record}/edit'),
            'view' => ViewSubscription::route('/{record}'),
            'transactions' => ViewSubscriptionTransactions::route('/{record}/payments'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewSubscriptionTransactions::class,
        ]);
    }
}
