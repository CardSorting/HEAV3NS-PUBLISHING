<?php

namespace App\Filament\Resources\ReleaseForms\Pages;

use App\Filament\Resources\ReleaseForms\ReleaseFormResource;
use App\Model\ReleaseForm;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListReleaseForms extends ListRecords
{
    protected static string $resource = ReleaseFormResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            null => Tab::make(__('admin.resources.release_form.tabs.all')),
            'pending' => Tab::make(__('admin.resources.release_form.tabs.pending'))->query(fn ($query) => $query->where('status', ReleaseForm::PENDING_STATUS)),
            'approved' => Tab::make(__('admin.resources.release_form.tabs.approved'))->query(fn ($query) => $query->where('status', ReleaseForm::APPROVED_STATUS)),
            'rejected' => Tab::make(__('admin.resources.release_form.tabs.rejected'))->query(fn ($query) => $query->where('status', ReleaseForm::REJECTED_STATUS)),
        ];
    }
}
