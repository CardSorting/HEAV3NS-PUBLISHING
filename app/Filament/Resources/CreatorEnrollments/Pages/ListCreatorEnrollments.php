<?php

namespace App\Filament\Resources\CreatorEnrollments\Pages;

use App\Filament\Resources\CreatorEnrollments\CreatorEnrollmentResource;
use App\Model\CreatorEnrollment;
use App\Model\User;
use App\Services\CreatorAccessService;
use App\Services\CreatorEnrollmentNotifications;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class ListCreatorEnrollments extends ListRecords
{
    protected static string $resource = CreatorEnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('New creator'))
                ->modalHeading(__('Grant creator access'))
                ->modalDescription(__('ID verification is managed separately.'))
                ->modalWidth(Width::Large)
                ->modalSubmitActionLabel(__('Grant access'))
                ->authorize(fn (): bool => Gate::allows('update', User::class))
                ->databaseTransaction()
                ->createAnother(false)
                ->schema([
                    Select::make('user_id')->label(__('User'))
                        ->relationship('user', 'username', fn (Builder $query): Builder => $query->whereDoesntHave('creatorEnrollment'))
                        ->searchable(['username', 'name'])
                        ->required()->rules(['integer'])->columnSpanFull(),
                ])
                ->using(fn (array $data): CreatorEnrollment => app(CreatorAccessService::class)->setStatus(
                    User::query()->findOrFail($data['user_id']),
                    auth()->user(),
                    0,
                    CreatorEnrollment::APPROVED_STATUS,
                    expectedEnrollmentId: 0,
                ))
                ->after(fn (CreatorEnrollment $record) => CreatorEnrollmentNotifications::reviewed($record))
                ->successNotificationTitle(__('Creator access granted'))
                ->successRedirectUrl(fn (CreatorEnrollment $record): string => CreatorEnrollmentResource::getUrl('view', ['record' => $record])),
        ];
    }

    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make(__('All'))];
        foreach (CreatorEnrollment::statusLabels() as $status => $label) {
            $tabs[$status] = Tab::make($label)->query(fn (Builder $query): Builder => $query->where('status', $status));
        }

        return $tabs;
    }
}
