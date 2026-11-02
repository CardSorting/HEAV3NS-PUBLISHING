<?php

namespace App\Http\Middleware;

use App;
use App\Model\User;
use App\Model\UserTax;
use App\PlatformSettings;
use App\Providers\InstallerServiceProvider;
use App\Providers\ListsHelperServiceProvider;
use App\Model\UserBadge;
use App\Model\UserStatus;
use App\Providers\WithdrawalsServiceProvider;
use App\Services\AgeCheck\AgeGate;
use Auth;
use Closure;
use JavaScript;
use Jenssegers\Agent\Agent;
use Session;
use Cookie;
use Route;

class JavascriptVariables
{
    public function handle($request, Closure $next)
    {
        $jsData = [
            'debug' => (bool) config('app.debug'),
            'baseUrl' => url(''),
            'theme' => App\Providers\GenericHelperServiceProvider::getSiteTheme(),
            'direction' => App\Providers\GenericHelperServiceProvider::getSiteDirection(),
        ];
        if (InstallerServiceProvider::checkIfInstalled()) {
            $ageGate = app(AgeGate::class);

            $jsData['ppMode'] = getSetting('payments.paypal_live_mode') != null && getSetting('payments.paypal_live_mode') ? 'live' : 'sandbox';
            $jsData['showCookiesBox'] = (bool) getSetting('compliance.enable_cookies_box');
            if (getSetting('payments.conversion_tracking_enabled') && $jsData['showCookiesBox']) {
                $jsData['conversionTracking'] = [
                    'enabled' => true,
                    'consentUrl' => route('conversion-tracking.consent'),
                    'pendingUrl' => route('conversion-tracking.pending'),
                    'acknowledgeUrl' => route('conversion-tracking.acknowledge'),
                    'clickIdParameter' => getSetting('payments.conversion_tracking_click_id_parameter'),
                    'testMode' => (bool) getSetting('payments.conversion_tracking_test_mode'),
                    'method' => getSetting('payments.conversion_tracking_method'),
                ];
            }
            $jsData['feedDisableRightClickOnMedia'] = getSetting('media.disable_media_right_click');
            $jsData['currency'] = App\Providers\SettingsServiceProvider::getAppCurrencyCode();
            $jsData['currencySymbol'] = App\Providers\SettingsServiceProvider::getWebsiteCurrencySymbol();
            $jsData['currencyPosition'] = App\Providers\SettingsServiceProvider::getWebsiteCurrencyPosition();
            $jsData['withdrawalsMinAmount'] = App\Providers\PaymentsServiceProvider::getWithdrawalMinimumAmount();
            $jsData['withdrawalsMaxAmount'] = App\Providers\PaymentsServiceProvider::getWithdrawalMaximumAmount();
            $jsData['depositMinAmount'] = App\Providers\PaymentsServiceProvider::getDepositMinimumAmount();
            $jsData['depositMaxAmount'] = App\Providers\PaymentsServiceProvider::getDepositMaximumAmount();
            $jsData['tipMinAmount'] = (int)getSetting('payments.min_tip_value');
            $jsData['tipMaxAmount'] = (int)getSetting('payments.max_tip_value');
            $jsData['min_ppv_post_price'] = getSetting('payments.min_ppv_post_price') ?? 1;
            $jsData['max_ppv_post_price'] = getSetting('payments.max_ppv_post_price') ?? 500;
            $jsData['min_ppv_message_price'] = getSetting('payments.min_ppv_message_price') ?? 1;
            $jsData['max_ppv_message_price'] = getSetting('payments.max_ppv_message_price') ?? 500;
            $jsData['stripeRecurringDisabled'] = getSetting('payments.stripe_recurring_disabled');
            $jsData['paypalRecurringDisabled'] = getSetting('payments.paypal_recurring_disabled');
            $jsData['ccBillRecurringDisabled'] = getSetting('payments.ccbill_recurring_disabled');
            $jsData['verotelRecurringDisabled'] = getSetting('payments.verotel_recurring_disabled');
            $jsData['localWalletRecurringDisabled'] = getSetting('payments.disable_local_wallet_for_subscriptions');
            $jsData['age_gate_driver'] = $ageGate->driver();
            $jsData['enable_age_verification_dialog'] = $ageGate->isBuiltIn();
            $jsData['allow_profile_bio_markdown'] = getSetting('profiles.allow_profile_bio_markdown');
            $jsData['ai_text_enabled'] = getSetting('ai.text_enabled');
            $jsData['ai_images_enabled'] = getSetting('ai.images_enabled');
            $jsData['tosPageSlug'] = App\Providers\GenericHelperServiceProvider::getTOSPage() ? App\Providers\GenericHelperServiceProvider::getTOSPage()->slug : null;
            $jsData['privacyPageSlug'] = App\Providers\GenericHelperServiceProvider::getPrivacyPage() ? App\Providers\GenericHelperServiceProvider::getPrivacyPage()->slug : null;
            $jsData['siteName'] = getSetting('site.name');
            $jsData['staffBadgeHtml'] = view('elements.icon', ['icon' => 'staff', 'centered' => true, 'classes' => '', 'variant' => 'small'])->render();
            $jsData['allow_hyperlinks'] = getSetting('profiles.allow_hyperlinks');
            $jsData['show_online_users_indicator'] = getSetting('profiles.show_online_users_indicator');
            $jsData['isTextOnlyPPVAllowed'] = getSetting('compliance.allow_text_only_ppv');
            $jsData['disableTextPreview'] = (bool)getSetting('feed.disable_posts_text_preview');
            $jsData['enable_post_description_excerpts'] = getSetting('feed.enable_post_description_excerpts');

            $jsData['enable_hashtags'] = getSetting('feed.enable_hashtags');
            $jsData['enable_mentions'] = getSetting('feed.enable_mentions');
            $jsData['enable_mention_suggestions'] = getSetting('feed.enable_mention_suggestions');

            $jsData['pwa_enabled'] = getSetting('site.pwa_enabled');
            $jsData['pwa_install_prompt_enabled'] = getSetting('site.pwa_install_prompt_enabled');
            $jsData['webPushPublicKey'] = getSetting('profiles.webpush_public_key');

            // Stories settings (frontend)
            $jsData['stories'] = [
                'enabled' => (bool) getSetting('stories.stories_enabled'),
                'allowHighlights'    => (bool) getSetting('stories.allow_highlights'),
            ];

            $jsData['routes'] = [
                'privacy' => route('pages.get', ['slug' => App\Providers\GenericHelperServiceProvider::getPrivacyPage()->slug]),
                'contact' => route('contact'),
            ];
        }
        JavaScript::put(['app' => $jsData]);

        $additionalJSVars = [];
        if (InstallerServiceProvider::checkIfInstalled()) {
            $user = Auth::user();

            if ($user instanceof User) {
                $additionalJSVars = [
                    'user' => [
                        'username' => $user->username,
                        'name' => $user->name,
                        'user_id' => $user->id,
                        'is_suspended' => $user->isBanned(),
                        'avatar' => $user->avatar,
                        'stripe_connect_verified' => $user->stripe_onboarding_verified,
                        'user_country_id' => $user->country_id,
                        'lists' => ListsHelperServiceProvider::getUserListTrimmed(),
                        'dac7_tax_required' => WithdrawalsServiceProvider::isTaxInfoRequiredForWithdrawals(),
                    ],
                    'socketsDriver' => getSetting('websockets.driver'),
                    'streamingDriver' => getSetting('streams.streaming_driver'),
                    'pusher' => [
                        'cluster' => getSetting('websockets.pusher_app_cluster'),
                        'key' => getSetting('websockets.pusher_app_key'),
                        'logging' => config('broadcasting.connections.pusher.logging', false),
                    ],
                    'soketi' => [
                        'key' => getSetting('websockets.soketi_app_key'),
                        'host' => getSetting('websockets.soketi_host_address'),
                        'port' => getSetting('websockets.soketi_host_port'),
                        'useTSL' => getSetting('websockets.soketi_use_TSL'),
                    ],
                ];
            }

            $additionalJSVars['appSettings'] = [
                'feed' => [
                    'allow_gallery_zoom' => (bool) getSetting('feed.allow_gallery_zoom'),
                ],
            ];

        }

        JavaScript::put($additionalJSVars);

        // Handling expired CSRF Tokens and Expired users sessions
        if (Session::has('sessionStatus') && Session::get('sessionStatus') == 'expired') {
            JavaScript::put(['app' => ['sessionStatus' => 'expired']]);
        }

        // Resetting profile last url (used for social media login redirects) - disabled on regular login/register pages
        if (Session::has('lastProfileUrl') && (Route::currentRouteName() == 'login' || Route::currentRouteName() == 'register')) {
            Session::forget('lastProfileUrl');
        }

        return $next($request);
    }
}
