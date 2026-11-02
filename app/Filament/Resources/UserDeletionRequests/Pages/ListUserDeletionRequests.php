<?php

namespace App\Filament\Resources\UserDeletionRequests\Pages;

use App\Filament\Resources\UserDeletionRequests\UserDeletionRequestResource;
use App\Model\UserDeletionRequest;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListUserDeletionRequests extends ListRecords
{
    protected static string $resource = UserDeletionRequestResource::class;

    public function getTabs(): array
    {
        return [
            null => Tab::make(__('admin.resources.user_deletion_request.tabs.all')),
            'pending' => Tab::make(__('admin.resources.user_deletion_request.tabs.pending'))->query(fn ($query) => $query->where('status', UserDeletionRequest::STATUS_PENDING)),
            'approved' => Tab::make(__('admin.resources.user_deletion_request.tabs.approved'))->query(fn ($query) => $query->where('status', UserDeletionRequest::STATUS_APPROVED)),
            'blocked' => Tab::make(__('admin.resources.user_deletion_request.tabs.blocked'))->query(fn ($query) => $query->where('status', UserDeletionRequest::STATUS_BLOCKED)),
            'completed' => Tab::make(__('admin.resources.user_deletion_request.tabs.completed'))->query(fn ($query) => $query->where('status', UserDeletionRequest::STATUS_COMPLETED)),
        ];
    }
}
