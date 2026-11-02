<?php

namespace App\Filament\Resources\UserReports\Pages;

use App\Filament\Resources\UserReports\UserReportResource;
use App\Model\UserReport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListUserReports extends ListRecords
{
    protected static string $resource = UserReportResource::class;

    public function getTabs(): array
    {
        return [
            null => Tab::make(__('admin.resources.user_report.tabs.all')),
            'received' => Tab::make(__('admin.resources.user_report.tabs.received'))
                ->query(fn ($query) => $query->where('status', UserReport::RECEIVED_STATUS)),
            'seen' => Tab::make(__('admin.resources.user_report.tabs.seen'))
                ->query(fn ($query) => $query->where('status', UserReport::SEEN_STATUS)),
            'solved' => Tab::make(__('admin.resources.user_report.tabs.solved'))
                ->query(fn ($query) => $query->where('status', UserReport::SOLVED_STATUS)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
