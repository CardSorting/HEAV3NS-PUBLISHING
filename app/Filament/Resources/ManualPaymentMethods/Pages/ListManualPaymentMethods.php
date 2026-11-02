<?php

namespace App\Filament\Resources\ManualPaymentMethods\Pages;

use App\Filament\Resources\ManualPaymentMethods\ManualPaymentMethodResource;
use App\Model\ManualPaymentMethod;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListManualPaymentMethods extends ListRecords
{
    protected static string $resource = ManualPaymentMethodResource::class;

    public function getTabs(): array
    {
        return [
            'deposit' => Tab::make(__('Deposits'))->query(fn ($query) => $query->where('direction', ManualPaymentMethod::DEPOSIT)),
            'withdrawal' => Tab::make(__('Withdrawals'))->query(fn ($query) => $query->where('direction', ManualPaymentMethod::WITHDRAWAL)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->url(fn () => ManualPaymentMethodResource::getUrl('create', [
            'direction' => $this->activeTab === 'withdrawal' ? 'withdrawal' : 'deposit',
        ]))];
    }
}
