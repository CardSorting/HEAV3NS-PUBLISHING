<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Model\User;
use App\Model\UserBan;
use App\Services\UserBanService;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class UserBanDetails
{
    public static function make(): Section
    {
        return Section::make(__('Account suspension'))
            ->columnSpanFull()
            ->collapsible()
            ->compact()
            ->dense()
            ->columns(['default' => 1, 'md' => 2])
            ->visible(fn (?User $record): bool => self::canView($record) && $record->bans()->exists())
            ->schema([
                TextEntry::make('suspension_status')
                    ->label(__('Status'))
                    ->state(fn (User $record): string => $record->isBanned() ? __('Suspended') : __('Active'))
                    ->badge()
                    ->color(fn (User $record): string => $record->isBanned() ? 'danger' : 'success'),
                TextEntry::make('renewal_cancellation_progress')
                    ->label(__('Renewal cancellations'))
                    ->state(function (User $record): string {
                        if (!self::canView($record)) {
                            return '';
                        }

                        $progress = app(UserBanService::class)->cancellationProgress($record);

                        return __(':pending pending · :review need review · :completed completed', $progress);
                    }),
                RepeatableEntry::make('suspension_history')
                    ->label(__('Latest 20 suspensions'))
                    ->state(fn (User $record): array => self::history($record))
                    ->columnSpanFull()
                    ->dense()
                    ->columns(['default' => 1, 'md' => 3])
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                __('Suspended') => 'danger',
                                __('Expired') => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('created_at')->label(__('Suspended at'))->dateTime(),
                        TextEntry::make('banned_by')->label(__('Suspended by'))->wrap(),
                        TextEntry::make('reason')
                            ->label(__('Reason'))
                            ->wrap()
                            ->columnSpan(['default' => 1, 'md' => 2]),
                        TextEntry::make('expires_at')->label(__('Ban expires at'))->dateTime()->placeholder(__('Indefinite')),
                        TextEntry::make('policy_reference')
                            ->label(__('Policy reference'))
                            ->wrap()
                            ->visible(fn (Get $get): bool => filled($get('policy_reference'))),
                        TextEntry::make('lifted_at')
                            ->label(__('Lifted at'))
                            ->dateTime()
                            ->visible(fn (Get $get): bool => filled($get('lifted_at'))),
                        TextEntry::make('lifted_by')
                            ->label(__('Lifted by'))
                            ->wrap()
                            ->visible(fn (Get $get): bool => filled($get('lifted_at'))),
                    ]),
            ]);
    }

    private static function canView(?User $user): bool
    {
        return $user?->exists && (bool) auth()->user()?->can('Ban:User');
    }

    private static function history(User $user): array
    {
        if (!self::canView($user)) {
            return [];
        }

        return $user->bans()->with(['bannedBy:id,name', 'liftedBy:id,name'])->latest('id')->limit(20)->get()
            ->map(function (UserBan $ban): array {
                $bannedBy = $ban->getRelation('bannedBy');
                $liftedBy = $ban->getRelation('liftedBy');

                return [
                    'status' => match ($ban->status) {
                        'active' => __('Suspended'),
                        'expired' => __('Expired'),
                        default => __('Lifted'),
                    },
                    'created_at' => $ban->created_at,
                    'banned_by' => $bannedBy instanceof User ? $bannedBy->name : __('Deleted administrator'),
                    'reason' => $ban->reason,
                    'policy_reference' => $ban->policy_reference,
                    'expires_at' => $ban->expires_at,
                    'lifted_at' => $ban->lifted_at,
                    'lifted_by' => $ban->lifted_at ? ($liftedBy instanceof User ? $liftedBy->name : __('Deleted administrator')) : null,
                ];
            })->all();
    }
}
