<?php

namespace App\Filament\Pages\Settings;

use App\Settings\ModerationSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\SettingsPage;

class ManageModerationSettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $slug = 'settings/media-moderation';

    protected static string $settings = ModerationSettings::class;

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->redirect(
            ManageComplianceSettings::getMediaModerationUrl(),
            navigate: true,
        );
    }
}
