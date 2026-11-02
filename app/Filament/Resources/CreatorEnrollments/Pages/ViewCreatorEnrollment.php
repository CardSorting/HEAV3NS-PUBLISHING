<?php

namespace App\Filament\Resources\CreatorEnrollments\Pages;

use App\Filament\Resources\CreatorEnrollments\CreatorEnrollmentResource;
use App\Filament\Resources\Users\Actions\UserCreatorActions;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Alignment;

class ViewCreatorEnrollment extends ViewRecord
{
    protected static string $resource = CreatorEnrollmentResource::class;

    protected ?Alignment $headerActionsAlignment = Alignment::End;

    protected function getHeaderActions(): array
    {
        [$reviewActions, $otherActions] = collect(UserCreatorActions::make())
            ->reject(fn (Action $action): bool => $action->getName() === 'review_identity')
            ->partition(fn (Action $action): bool => in_array($action->getName(), ['approve_creator', 'reject_creator'], true));

        return [
            ...$reviewActions->map(fn (Action $action): Action => $action
                ->modalHeading($action->getLabel())
                ->label($action->getName() === 'approve_creator' ? __('Approve') : __('Reject'))
                ->button())->all(),
            ActionGroup::make([...$otherActions->all(), UserCreatorActions::deleteEnrollment()])
                ->label(__('More'))
                ->icon('heroicon-o-ellipsis-horizontal')
                ->color('gray')
                ->button()
                ->dropdownPlacement('bottom-end'),
        ];
    }
}
