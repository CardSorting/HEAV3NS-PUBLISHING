<?php

namespace App\Filament\Resources\MediaModerationRuns\Pages;

use App\Filament\Resources\MediaModerationRuns\MediaModerationRunResource;
use App\Filament\Widgets\ModerationUnavailableNoticeWidget;
use Filament\Resources\Pages\ListRecords;

class ListMediaModerationRuns extends ListRecords
{
    protected static string $resource = MediaModerationRunResource::class;

    public string $scanType = 'media';

    protected function getHeaderWidgets(): array
    {
        return [ModerationUnavailableNoticeWidget::class];
    }

    protected function getHeaderActions(): array
    {
        return [MediaModerationRunResource::startScanAction()];
    }
}
