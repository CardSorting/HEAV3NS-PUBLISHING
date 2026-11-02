<?php

namespace App\Filament\Resources\UserReports\Pages;

use App\Filament\Resources\UserReports\UserReportResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUserReport extends ViewRecord
{
    protected static string $resource = UserReportResource::class;

    protected function getActions(): array
    {
        $record = $this->getRecord();
        $recordActions = UserReportResource::getRecordActions();

        foreach ($recordActions as $action) {
            if ($action instanceof Action || $action instanceof ActionGroup) {
                $action
                    ->record($record)
                    ->livewire($this);
            }
        }

        $primaryActions = [];
        $secondaryActions = [];

        foreach ($recordActions as $action) {
            if (in_array($action->getName(), ['view_admin', 'delete_reported_content', 'ban_user', 'unban_user'], true)) {
                $primaryActions[] = $action;

                continue;
            }

            $secondaryActions[] = $action;
        }

        return [
            ...$primaryActions,
            EditAction::make(),
            ActionGroup::make($secondaryActions)
                ->label('More actions')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->color('gray')
                ->tooltip('More actions')
                ->dropdownPlacement('bottom-end')
                ->iconButton()
                ->record($record)
                ->livewire($this),
        ];
    }
}
