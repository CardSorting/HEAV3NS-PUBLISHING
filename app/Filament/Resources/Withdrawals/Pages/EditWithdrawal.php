<?php

namespace App\Filament\Resources\Withdrawals\Pages;

use App\Filament\Resources\Withdrawals\WithdrawalResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditWithdrawal extends EditRecord
{
    protected ?bool $hasDatabaseTransactions = true;

    protected static string $resource = WithdrawalResource::class;

    protected function getHeaderActions(): array
    {
        [$reviewActions, $otherActions] = collect(WithdrawalResource::getRecordActions())
            ->partition(fn (Action $action): bool => in_array($action->getName(), ['approve_withdrawal', 'reject_withdrawal'], true));

        return [
            ...$reviewActions->map(fn (Action $action): Action => $action->button()
                ->hidden(fn (): bool => !Auth::user()?->hasRole('admin')))->all(),
            WithdrawalResource::getRecordActionsGroup()->actions($otherActions->all()),
        ];
    }
}
