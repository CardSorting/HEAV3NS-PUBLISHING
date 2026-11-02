<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Filament\Resources\Transactions\TransactionResource;
use App\Model\Transaction;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderWidgets(): array
    {
        return TransactionResource::getWidgets();
    }

    public function getTabs(): array
    {
        return [
            null => Tab::make(__('admin.resources.transaction.tabs.all')),
            'pending' => Tab::make()->label(__('admin.resources.transaction.tabs.pending'))->query(fn ($query) => $query->where('status', Transaction::PENDING_STATUS)),
            'approved' => Tab::make()->label(__('admin.resources.transaction.tabs.approved'))->query(fn ($query) => $query->where('status', Transaction::APPROVED_STATUS)),
            'declined' => Tab::make()->label(__('admin.resources.transaction.tabs.declined'))->query(fn ($query) => $query->where('status', Transaction::DECLINED_STATUS)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
