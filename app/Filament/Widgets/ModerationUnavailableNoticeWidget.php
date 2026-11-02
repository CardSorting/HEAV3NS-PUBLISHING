<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Settings\ManageComplianceSettings;
use App\Services\Moderation\ModerationConfiguration;
use App\Settings\ModerationSettings;
use Filament\Widgets\Widget;

class ModerationUnavailableNoticeWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.moderation-unavailable-notice-widget';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return !app(ModerationConfiguration::class)->enabled();
    }

    protected function getViewData(): array
    {
        $isEnabled = app(ModerationSettings::class)->enabled;

        return [
            'title' => $isEnabled ? 'Moderation setup is incomplete' : 'Moderation is disabled',
            'message' => $isEnabled
                ? 'Add the provider credentials under Compliance before starting a new scan. Existing results remain available.'
                : 'New scans are unavailable. Existing scan history and review decisions remain available.',
            'settingsUrl' => ManageComplianceSettings::canAccess()
                ? ManageComplianceSettings::getMediaModerationUrl()
                : null,
        ];
    }
}
