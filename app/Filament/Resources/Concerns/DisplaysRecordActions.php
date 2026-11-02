<?php

namespace App\Filament\Resources\Concerns;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

trait DisplaysRecordActions
{
    public static function getRecordActionsForDisplay(...$arguments): array
    {
        $livewire = null;
        $record = null;

        if (($arguments[0] ?? null) instanceof Component) {
            $livewire = array_shift($arguments);
        }

        if (($arguments[0] ?? null) instanceof Model || is_array($arguments[0] ?? null)) {
            $record = array_shift($arguments);
        }

        $actions = static::getRecordActions(...$arguments);

        if ($record) {
            foreach ($actions as $action) {
                if ($action instanceof Action || $action instanceof ActionGroup) {
                    $action->record($record);

                    if ($livewire) {
                        $action->livewire($livewire);
                    }
                }
            }

            $visibleActions = array_values(array_filter(
                $actions,
                fn (Action | ActionGroup $action): bool => $action->isVisible(),
            ));

            if (count($visibleActions) === 1) {
                if ($visibleActions[0] instanceof Action) {
                    $visibleActions[0]->button();
                }

                return $visibleActions;
            }
        }

        if (count($actions) === 1) {
            return $actions;
        }

        $group = static::getRecordActionsGroup(...$arguments);

        if ($record) {
            $group->record($record);
        }

        if ($livewire) {
            $group->livewire($livewire);
        }

        return [$group];
    }
}
