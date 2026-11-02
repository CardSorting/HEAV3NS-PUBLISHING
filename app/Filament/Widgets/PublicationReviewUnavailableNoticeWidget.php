<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Settings\ManageComplianceSettings;
use App\Settings\ModerationSettings;

class PublicationReviewUnavailableNoticeWidget extends ModerationUnavailableNoticeWidget
{
    public static function canView(): bool
    {
        return !app(ModerationSettings::class)->publication_review_enabled;
    }

    protected function getViewData(): array
    {
        return [
            'title' => __('Publication review is disabled'),
            'message' => __('Existing submissions remain reviewable. Initial-post rules still apply.'),
            'settingsUrl' => ManageComplianceSettings::canAccess()
                ? ManageComplianceSettings::getUrl(['tab' => ManageComplianceSettings::PUBLICATION_REVIEW_TAB_ID])
                : null,
        ];
    }
}
