<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\SystemHealth;
use App\Filament\Pages\Settings\ManageAdminSettings;
use App\Filament\Pages\Settings\ManageAiSettings;
use App\Filament\Pages\Settings\ManageColorsSettings;
use App\Filament\Pages\Settings\ManageComplianceSettings;
use App\Filament\Pages\Settings\ManageEmailsSettings;
use App\Filament\Pages\Settings\ManageFeedSettings;
use App\Filament\Pages\Settings\ManageGeneralSettings;
use App\Filament\Pages\Settings\ManageLicenseSettings;
use App\Filament\Pages\Settings\ManageMediaSettings;
use App\Filament\Pages\Settings\ManagePaymentsSettings;
use App\Filament\Pages\Settings\ManageProfilesSettings;
use App\Filament\Pages\Settings\ManageReelsSettings;
use App\Filament\Pages\Settings\ManageSecuritySettings;
use App\Filament\Pages\Settings\ManageStorageSettings;
use App\Filament\Pages\Settings\ManageRuntimeSettings;
use App\Filament\Pages\Settings\ManageStoriesSettings;
use App\Filament\Pages\Settings\ManageStreamsSettings;
use App\Filament\Pages\Settings\ManageWebsocketsSettings;
use App\Filament\Plugins\CustomHeaderLabelPlugin;
use App\Filament\Resources\Attachments\AttachmentResource;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\ContentReviews\ContentReviewResource;
use App\Filament\Resources\ConversionDeliveries\ConversionDeliveryResource;
use App\Filament\Resources\Countries\CountryResource;
use App\Filament\Resources\CreatorEnrollments\CreatorEnrollmentResource;
use App\Filament\Resources\FeaturedUsers\FeaturedUserResource;
use App\Filament\Resources\GlobalAnnouncements\GlobalAnnouncementResource;
use App\Filament\Resources\Hashtags\HashtagResource;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\ManualPaymentMethods\ManualPaymentMethodResource;
use App\Filament\Resources\MediaModerationItems\MediaModerationItemResource;
use App\Filament\Resources\MediaModerationRuns\MediaModerationRunResource;
use App\Filament\Resources\Notifications\NotificationResource;
use App\Filament\Resources\PaymentRequests\PaymentRequestResource;
use App\Filament\Resources\Polls\PollResource;
use App\Filament\Resources\PostComments\PostCommentResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\PublicPages\PublicPageResource;
use App\Filament\Resources\Reactions\ReactionResource;
use App\Filament\Resources\Reels\ReelResource;
use App\Filament\Resources\ReleaseForms\ReleaseFormResource;
use App\Filament\Resources\Rewards\RewardResource;
use App\Filament\Resources\Roles\Roles\RoleResource;
use App\Filament\Resources\Sounds\SoundResource;
use App\Filament\Resources\Stories\StoryResource;
use App\Filament\Resources\StreamMessages\StreamMessageResource;
use App\Filament\Resources\Streams\StreamResource;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Filament\Resources\Taxes\TaxResource;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Filament\Resources\UserBookmarks\UserBookmarkResource;
use App\Filament\Resources\UserDeletionRequests\UserDeletionRequestResource;
use App\Filament\Resources\UserLists\UserListResource;
use App\Filament\Resources\UserMessages\UserMessageResource;
use App\Filament\Resources\UserReports\UserReportResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\UserTaxes\UserTaxResource;
use App\Filament\Resources\UserVerifies\UserVerifyResource;
use App\Filament\Resources\Wallets\WalletResource;
use App\Filament\Resources\Withdrawals\WithdrawalResource;
use App\Providers\AdminHelperProvider;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\InstallerServiceProvider;
use App\Providers\SettingsServiceProvider;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Assets\Js;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Saade\FilamentLaravelLog\FilamentLaravelLogPlugin;
use App\Http\Middleware\LocaleSetter;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {

        // If the site is not installed, provision a fake admin
        // TODO: Review if really necessary
        if (!InstallerServiceProvider::checkIfInstalled()) {
            return Panel::make()->id('admin');
        }

        // Local storage public url re-init, panel registration is done too early
        SettingsServiceProvider::setupLocalStorage();

        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->colors([
                'primary' => Color::Pink,
            ])
            ->authGuard('web')
            ->brandName(getSetting('admin.title') ? getSetting('admin.title') : '')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->favicon(getSetting('site.favicon'))
            ->brandLogo(fn () => view('filament.partials.logo'))
            ->darkModeBrandLogo(fn () => view('filament.partials.logo-dark'))
            ->brandLogoHeight('2.5rem')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                \App\Http\Middleware\EnsureAccountIsNotSuspended::class,
                LocaleSetter::class,
            ], isPersistent: true)
            ->plugins([
                FilamentShieldPlugin::make(),
                FilamentLaravelLogPlugin::make()
                    ->authorize(
                        fn () => Filament::auth()->check()
                        && Filament::auth()->user()->can('View:ViewLog')
                    ),
                new CustomHeaderLabelPlugin(),
            ])
            ->assets($this->navigationAssets())
            ->globalSearch(false)
            ->spa()
            // Downloads and account switches require a full browser navigation.
            ->spaUrlExceptions(fn () => [
                url('/manual-payment-documents/*'),
                url('/admin/users/*/impersonate'),
            ]);

        $this->panelNavigation($panel);

        return $panel;

    }

    /** @return list<Js> */
    protected function navigationAssets(): array
    {
        if (!getSetting('site.pwa_enabled')) {
            return [];
        }

        return [
            Js::make('admin-navigation', GenericHelperServiceProvider::getVersionedAsset('js/admin-navigation.js'))
                ->defer()
                ->extraAttributes(['data-app-url' => e(url('/'))]),
        ];
    }

    public function panelNavigation($panel) {

        $panel
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                return $builder
                    // 1. Top-level items (outside any group)
                    ->items(array_filter([
                        NavigationItem::make(__('admin.navigation.dashboard'))
                            ->icon('heroicon-o-home')
                            ->isActiveWhen(fn () => request()->routeIs('filament.admin.pages.dashboard'))
                            ->url(fn () => Dashboard::getUrl()),
                        AdminHelperProvider::settingsNavItem(__('System Health'), 'heroicon-o-heart', SystemHealth::class),
                    ]))

                    // 2. Grouped navigation
                    ->groups([
                        NavigationGroup::make()
                            ->icon('heroicon-o-users') // top-level category icon
                            ->label(__('admin.navigation.groups.users'))
                            ->items(
                                collect([
                                ...AdminHelperProvider::resourceNavIfCan(UserResource::class),
                                ...collect(AdminHelperProvider::resourceNavIfCan(RoleResource::class))
                                    ->map(fn ($item) => $item->badge(null))
                                    ->all(),
                                ...AdminHelperProvider::resourceNavIfCan(UserVerifyResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(CreatorEnrollmentResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(WalletResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(NotificationResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(UserMessageResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(UserListResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(UserBookmarkResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(FeaturedUserResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(UserTaxResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(ReleaseFormResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(UserDeletionRequestResource::class),
                            ])
                                // Striping any icons ; Required for filament shield v4 atm
                                ->map(fn (NavigationItem $item) => $item->icon(null)->activeIcon(null))
                                ->all()
                            ),

                        NavigationGroup::make()
                            ->label(__('admin.navigation.groups.finances'))
                            ->icon('heroicon-o-banknotes')
                            ->items([
                                ...AdminHelperProvider::resourceNavIfCan(TransactionResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(SubscriptionResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(WithdrawalResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(PaymentRequestResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(ManualPaymentMethodResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(ConversionDeliveryResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(RewardResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(InvoiceResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(TaxResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(CountryResource::class),
                            ])->collapsed(),

                        NavigationGroup::make()
                            ->label(__('admin.navigation.groups.content'))
                            ->icon('heroicon-o-rectangle-stack')
                            ->items([
                                ...AdminHelperProvider::resourceNavIfCan(PostResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(StoryResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(ReelResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(StreamResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(StreamMessageResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(PostCommentResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(AttachmentResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(PollResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(SoundResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(HashtagResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(ReactionResource::class),
                            ])->collapsed(),

                        NavigationGroup::make()
                            ->icon('heroicon-o-shield-check')
                            ->label(__('admin.navigation.groups.moderation'))
                            ->items(
                                collect([
                                    ...AdminHelperProvider::resourceNavIfCan(ContentReviewResource::class),
                                    ...AdminHelperProvider::resourceNavIfCan(UserReportResource::class),
                                    ...AdminHelperProvider::resourceNavIfCan(MediaModerationItemResource::class),
                                    ...AdminHelperProvider::resourceNavIfCan(MediaModerationRunResource::class),
                                ])
                                    ->map(fn (NavigationItem $item) => $item->icon(null)->activeIcon(null))
                                    ->all()
                            ),

                        NavigationGroup::make()
                            ->label(__('admin.navigation.groups.site'))
                            ->icon('heroicon-o-document')
                            ->items([
                                ...AdminHelperProvider::resourceNavIfCan(PublicPageResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(ContactMessageResource::class),
                                ...AdminHelperProvider::resourceNavIfCan(GlobalAnnouncementResource::class),
                            ])->collapsed(),

                        NavigationGroup::make()
                            ->label(__('admin.navigation.groups.settings'))
                            ->icon('heroicon-o-cog-6-tooth')
                            ->items(array_filter([
                                AdminHelperProvider::settingsNavItem(__('admin.settings.general'), '', ManageGeneralSettings::class),
                                AdminHelperProvider::settingsNavItem(__('admin.settings.users'), '', ManageProfilesSettings::class),
                                AdminHelperProvider::settingsNavItem(__('admin.settings.media'), '', ManageMediaSettings::class),
                                AdminHelperProvider::settingsNavItem(__('admin.settings.feed'), '', ManageFeedSettings::class),
                                AdminHelperProvider::settingsNavItem(__('admin.settings.stories'), '', ManageStoriesSettings::class),
                                AdminHelperProvider::settingsNavItem(__('admin.settings.reels'), '', ManageReelsSettings::class),
                                AdminHelperProvider::settingsNavItem(__('admin.settings.streams'), '', ManageStreamsSettings::class),
                                AdminHelperProvider::settingsNavItem(__('admin.settings.storage'), '', ManageStorageSettings::class),
                                AdminHelperProvider::settingsNavItem(__('admin.settings.payments'), '', ManagePaymentsSettings::class),
                                AdminHelperProvider::settingsNavItem(__('admin.settings.websockets'), '', ManageWebsocketsSettings::class),
                                AdminHelperProvider::settingsNavItem(__('admin.settings.emails'), '', ManageEmailsSettings::class),
                                AdminHelperProvider::settingsNavItem(__('admin.settings.runtime'), '', ManageRuntimeSettings::class),
                                AdminHelperProvider::settingsNavItem(__('admin.settings.security'), '', ManageSecuritySettings::class),
                                AdminHelperProvider::settingsNavItem(__('admin.settings.compliance'), '', ManageComplianceSettings::class),
                                AdminHelperProvider::settingsNavItem(__('admin.settings.ai'), '', ManageAiSettings::class),
                                AdminHelperProvider::settingsNavItem(__('admin.settings.admin'), '', ManageAdminSettings::class),
                                AdminHelperProvider::settingsNavItem(__('admin.settings.theme'), '', ManageColorsSettings::class),
                                AdminHelperProvider::settingsNavItem(__('admin.settings.license'), '', ManageLicenseSettings::class),
                            ]))
                            ->collapsed(),
                    ]);
            });
        return $panel;
    }
}
