<?php

namespace App\Filament\Resources\Withdrawals\Pages;

use App\Filament\Resources\Withdrawals\WithdrawalResource;
use App\Services\ManualPayments\ManualWithdrawalRequests;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateWithdrawal extends CreateRecord
{
    protected static string $resource = WithdrawalResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(ManualWithdrawalRequests::class)->create(
                (int) $data['user_id'],
                (int) $data['manual_method_id'],
                (int) $data['payout_account_id'],
                (float) $data['amount'],
                $data['submitted_fields'] ?? [],
            );
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn ($messages, $key) => ['data.'.$key => $messages])->all());
        }
    }
}
