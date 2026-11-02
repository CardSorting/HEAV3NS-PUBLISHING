<?php

namespace App\Filament\Resources\Rewards;

use App\Filament\Resources\Rewards\Pages\ListReward;
use App\Filament\Resources\Rewards\Pages\ViewReward;
use App\Filament\Traits\ResolvesRecordUrl;
use App\Model\Reward;
use App\Model\User;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\QueryBuilder\Constraints\DateConstraint;
use Filament\Tables\Filters\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Symfony\Component\Console\Input\Input;
use UnitEnum;

class RewardResource extends Resource
{
    use ResolvesRecordUrl;

    protected static ?string $model = Reward::class;

    protected static UnitEnum|string|null $navigationGroup = 'Referrals';

    protected static ?int $navigationSort = 0;

    public static function getModelLabel(): string
    {
        return __('admin.resources.reward.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.reward.plural');
    }

    protected static function rewardTypeOptions(): array
    {
        return [
            Reward::FEE_PERCENTAGE_REWARD_TYPE => __('admin.resources.reward.label'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.resources.reward.sections.referral_info'))
                ->columnSpanFull()
                ->description(__('admin.resources.reward.sections.referral_info_descr'))
                ->columns(2)
                ->schema(components: [
                    Select::make('from_user_id')
                        ->label(__('admin.resources.reward.fields.from_user_id'))
                        ->relationship('fromUser', 'username')
                        ->searchable()
                        ->required()
                        ->preload(),

                    Select::make('to_user_id')
                        ->label(__('admin.resources.reward.fields.to_user_id'))
                        ->relationship('toUser', 'username')
                        ->searchable()
                        ->required()
                        ->preload(),

                    Select::make('referral_code_usage_id')
                        ->label(__('admin.resources.reward.fields.referral_code_usage_id'))
                        ->searchable()
                        ->required()
                        ->options(function () {
                            return User::query()
                                ->whereNotNull('referral_code')
                                ->pluck('referral_code', 'id')
                                ->toArray();
                        }),

                    TextInput::make('amount')
                        ->label(__('admin.resources.reward.fields.amount'))
                        ->numeric()
                        ->required(),

                    Select::make('transaction_id')
                        ->label(__('admin.resources.reward.fields.transaction_id'))
                        ->relationship('transaction', 'id')
                        ->searchable()
                        ->required()
                        ->preload(),

                    Select::make('reward_type')
                        ->label(__('admin.resources.reward.fields.reward_type'))
                        ->options(static::rewardTypeOptions())
                        ->required()
                        ->default(Reward::FEE_PERCENTAGE_REWARD_TYPE)
                        ->helperText(__('admin.resources.reward.help.reward_type')),

                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label(__('admin.common.id'))
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('transaction_id')
                    ->label(__('admin.resources.reward.fields.transaction_id'))
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('fromUser.username')
                    ->label(__('admin.resources.reward.fields.from_user_id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('toUser.username')
                    ->label(__('admin.resources.reward.fields.to_user_id'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('admin.resources.reward.fields.amount'))
                    ->numeric()
                    ->sortable()
                    ->color('gray')
                ->badge(),

                Tables\Columns\TextColumn::make('reward_type')
                    ->label(__('admin.resources.reward.fields.reward_type'))
                    ->formatStateUsing(fn ($state) => static::rewardTypeOptions()[$state] ?? $state)
                    ->badge()
                    ->color('gray')
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
                        TextConstraint::make('fromUser.username')->label(__('admin.resources.reward.fields.from_user_id')),
                        TextConstraint::make('toUser.username')->label(__('admin.resources.reward.fields.to_user_id')),
                        DateConstraint::make('created_at')->label(__('admin.common.created_at')),
                        DateConstraint::make('updated_at')->label(__('admin.common.updated_at')),
                    ])
                    ->constraintPickerColumns(2),
            ], layout: Tables\Enums\FiltersLayout::Dropdown)
            ->deferFilters()
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
            'index' => ListReward::route('/'),
//            'create' => Pages\CreateReward::route('/create'),
//            'edit' => Pages\EditReward::route('/{record}/edit'),
            'view' => ViewReward::route('/{record}'),
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
//            Pages\ViewReward::class,
//            Pages\EditReward::class,
        ]);
    }
}
