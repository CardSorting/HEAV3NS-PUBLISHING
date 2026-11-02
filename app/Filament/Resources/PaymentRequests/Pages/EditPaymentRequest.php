<?php

namespace App\Filament\Resources\PaymentRequests\Pages;

use App\Filament\Resources\PaymentRequests\PaymentRequestResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditPaymentRequest extends EditRecord
{
    protected ?bool $hasDatabaseTransactions = true;

    protected static string $resource = PaymentRequestResource::class;

    protected function getHeaderActions(): array
    {
        [$reviewActions, $otherActions] = collect(PaymentRequestResource::getRecordActions())
            ->partition(fn (Action $action): bool => in_array($action->getName(), ['approve', 'reject'], true));

        return [
            ...$reviewActions->map(fn (Action $action): Action => $action->button())->all(),
            PaymentRequestResource::getRecordActionsGroup()->actions($otherActions->all()),
        ];
    }
}
