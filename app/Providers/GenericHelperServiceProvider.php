<?php

namespace App\Providers;

use App\Support\ThemePreference;
use App\Filament\Resources\Streams\StreamResource;
use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Reels\ReelResource;
use App\Filament\Resources\Stories\StoryResource;
use App\Filament\Resources\UserMessages\UserMessageResource;
use App\Filament\Resources\Users\UserResource;
use App\Model\GlobalAnnouncement;
use App\Model\Post;
use App\Model\PublicPage;
use App\Model\Subscription;
use App\Model\User;
use App\Model\UserReport;
use App\Model\Wallet;
use App\Services\CreatorAccessService;
use App\Services\ManualPayments\ManualPaymentReceivingImages;
use Carbon\Carbon;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Jenssegers\Agent\Agent;
use Mews\Purifier\Facades\Purifier;
use Pusher\Pusher;
use Pusher\PusherException;
use Ramsey\Uuid\Uuid;

class GenericHelperServiceProvider extends ServiceProvider
{
    public static function isCreatorAccessEnforced(): bool
    {
        return app(CreatorAccessService::class)->enforced();
    }

    public static function needsCreatorApproval(): bool
    {
        $access = app(CreatorAccessService::class);

        return $access->requiresApplication() && !$access->isCreator(Auth::user());
    }

    public static function needsCreatorOnboarding(): bool
    {
        $access = app(CreatorAccessService::class);

        return $access->enforced() && !$access->isCreator(Auth::user()) && $access->canAccessOnboarding(Auth::user());
    }

    public static function canAccessCreatorOnboarding(): bool
    {
        return app(CreatorAccessService::class)->canAccessOnboarding(Auth::check() ? Auth::user() : null);
    }

    public static function isInstantCreatorActivation(): bool
    {
        return app(CreatorAccessService::class)->activatesInstantly();
    }

    public static function requiresPublishingIdentityVerification(): bool
    {
        return app(CreatorAccessService::class)->requiresIdentityVerification();
    }

    public static function areIdentityChecksEnabled(): bool
    {
        return !(bool) getSetting('site.hide_identity_checks', false)
            || self::requiresPublishingIdentityVerification();
    }

    public static function canPublishContent(): bool
    {
        return app(CreatorAccessService::class)->canPublish(Auth::user());
    }

    public static function getSettingsMenuItems(array $settings): array
    {
        if (isset($settings['creator']) && self::canPublishContent()) {
            unset($settings['creator']);
        }

        $items = [];
        foreach ($settings as $key => $setting) {
            $items[$key] = array_merge($setting, ['label' => ucfirst(__(str_replace('-', ' ', $key)))]);
        }

        return $items;
    }

    public static function canViewCreationPage(): bool
    {
        return app(CreatorAccessService::class)->canViewCreationPage(Auth::user());
    }

    public static function canComposePost(?Post $post = null): bool
    {
        $access = app(CreatorAccessService::class);

        return $post ? $access->canEditPost(Auth::user(), $post) : $access->canPublish(Auth::user());
    }

    public static function getCreatorRestrictionMessage(): string
    {
        return app(CreatorAccessService::class)->restrictionMessage(Auth::user());
    }

    public static function getCreatorOnboardingUrl(): string
    {
        return app(CreatorAccessService::class)->onboardingUrl(Auth::user());
    }

    public static function getCreatorOnboardingLabel(): string
    {
        return app(CreatorAccessService::class)->onboardingLabel(Auth::user());
    }

    public static function getHomeCreatorAction(): ?array
    {
        if (!self::canAccessCreatorOnboarding()) {
            return null;
        }
        if (!Auth::check()) {
            return ['url' => route('login'), 'label' => __('Become a creator')];
        }

        if (self::needsCreatorOnboarding() || !self::canViewCreationPage()) {
            return ['url' => self::getCreatorOnboardingUrl(), 'label' => self::getCreatorOnboardingLabel()];
        }

        return ['url' => route('posts.create'), 'label' => __('Create')];
    }

    public static function getCreationAction(string $route, string $label, string $style = 'primary'): ?array
    {
        $canView = self::canViewCreationPage();
        if (!$canView && !self::canAccessCreatorOnboarding()) {
            return null;
        }

        return [
            'url' => $canView ? route($route) : self::getCreatorOnboardingUrl(),
            'label' => $canView ? $label : self::getCreatorOnboardingLabel(),
            'icon' => 'add-outline',
            'style' => $style,
        ];
    }

    /** @return array<int, array{label: string, type: string, value: string, url?: string}> */
    public static function getManualPaymentReceivingDetails(array $rows, ?int $methodId = null): array
    {
        $details = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['label'] ?? null) || !is_string($row['value'] ?? null)) {
                continue;
            }
            $label = trim($row['label']);
            $value = trim($row['value']);
            $type = $row['type'] ?? 'text';
            if ($label === '' || $value === '' || !in_array($type, ['text', 'image'], true)) {
                continue;
            }
            $detail = ['label' => $label, 'type' => $type, 'value' => $value];
            if ($type === 'image') {
                if (ManualPaymentReceivingImages::metadata($value) === null) {
                    continue;
                }
                $detail['url'] = ManualPaymentReceivingImages::url($value, $methodId);
            }
            $details[] = $detail;
        }

        return $details;
    }

    /** Keep a single uploader's minimum with its limits; shared minimums apply to the whole request. */
    public static function getManualDepositReceiptHints(array $method): array
    {
        $minimum = (int) ($method['submission_settings']['minimum_attachments'] ?? 0);
        $hint = $minimum > 0 ? trans_choice('{1} At least :count receipt required.|[2,*] At least :count receipts required.', $minimum, ['count' => $minimum]) : '';
        $singleUploader = count(array_filter($method['request_fields'] ?? [], fn ($field) => ($field['type'] ?? 'text') === 'file')) === 1;

        return ['upload' => $singleUploader ? $hint : '', 'request' => $singleUploader ? '' : $hint];
    }

    /**
     * Check if user meets all ID verification steps.
     *
     * @return bool
     */
    public static function isUserVerified($user = null)
    {
        $userInstance = Auth::user();
        if($user != null){
            $userInstance = $user;
        }
        if($userInstance == null){
            return false;
        }
        if (
            ($userInstance->verification && $userInstance->verification->status == 'verified') &&
            $userInstance->birthdate &&
            $userInstance->email_verified_at
        ) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $contactUserID - Contacted users
     * @param $userID - User sending the message
     * @return bool
     */
    public static function hasUserBlocked(int $blockerId, int $blockedId): bool
    {
        // Does blockerId's "blocked" list contain blockedId?
        return DB::table('user_list_members as ulm')
            ->where('ulm.user_id', $blockedId)
            ->whereExists(function ($q) use ($blockerId) {
                $q->select(DB::raw(1))
                    ->from('user_lists as l')
                    ->whereColumn('l.id', 'ulm.list_id')
                    ->where('l.user_id', $blockerId)
                    ->where('l.type', 'blocked');
            })
            ->exists();
    }

    /**
     * Creates a default wallet for a user.
     * @param $user
     */
    public static function createUserWallet($user)
    {
        try {
            $userWallet = Wallet::query()->where('user_id', $user->id)->first();
            if ($userWallet == null) {
                // generate unique id for wallet
                do {
                    $id = Uuid::uuid4()->getHex();
                } while (Wallet::query()->where('id', $id)->first() != null);

                $balance = 0.0;
                if(getSetting('profiles.default_wallet_balance_on_register') && getSetting('profiles.default_wallet_balance_on_register') != 0){
                    $balance = getSetting('profiles.default_wallet_balance_on_register');
                }
                Wallet::create([
                    'id' => $id,
                    'user_id' => $user->id,
                    'total' => $balance,
                ]);
            }
        } catch (\Exception $exception) {
            Log::error('User wallet creation error: '.$exception->getMessage());
        }
    }

    /**
     * Static function that handles remote storage drivers.
     *
     * @param $value
     * @return string
     */
    public static function getStorageAvatarPath($value) {

        if($value && $value !== '/img/default-avatar.jpg'){
            return self::getFilePathByActiveStorageDriver($value);
        }else{
            return str_replace('storage/', '', asset('/img/default-avatar.jpg'));
        }
    }

    public static function getFilePathByActiveStorageDriver($value): string {
        if (getSetting('storage.driver') == 's3') {
            if (getSetting('storage.aws_cdn_enabled') && getSetting('storage.aws_cdn_presigned_urls_enabled')) {
                $fileUrl = AttachmentServiceProvider::signAPrivateDistributionPolicy(
                    'https://'.getSetting('storage.cdn_domain_name').'/'.$value
                );
            } elseif (getSetting('storage.aws_cdn_enabled')) {
                $fileUrl = 'https://'.getSetting('storage.cdn_domain_name').'/'.$value;
            } else {
                $fileUrl = 'https://'.getSetting('storage.aws_bucket_name').'.s3.'.getSetting('storage.aws_region').'.amazonaws.com/'.$value;
            }
            return $fileUrl;
        }
        elseif(getSetting('storage.driver') == 'wasabi' || getSetting('storage.driver') == 'do_spaces' || getSetting('storage.driver') == 'r2' || getSetting('storage.driver') == 'b2'){
            return Storage::url($value);
        }
        elseif(getSetting('storage.driver') == 'minio'){
            return rtrim(getSetting('storage.minio_endpoint'), '/').'/'.getSetting('storage.minio_bucket_name').'/'.$value;
        }
        elseif(getSetting('storage.driver') == 'pushr'){
            return rtrim(getSetting('storage.pushr_cdn_hostname'), '/').'/'.$value;
        }
        else{
            return Storage::disk('public')->url($value);
        }
    }

    /**
     * Static function that handles remote storage drivers.
     *
     * @param $value
     * @return string
     */
    public static function getStorageCoverPath($value) {
        if($value){
            return self::getFilePathByActiveStorageDriver($value);
        }else{
            return asset('/img/default-cover.png');
        }
    }

    /**
     * Helper to detect mobile usage.
     * @return bool
     */
    public static function isMobileDevice() {
        $agent = new Agent();
        return $agent->isMobile();
    }

    /**
     * Returns true if email enforce is not enabled or if is set to true and user is verified.
     * @return bool
     */
    public static function isEmailEnforcedAndValidated() {
        return (Auth::check() && Auth::user()->email_verified_at) || (Auth::check() && !getSetting('site.enforce_email_validation'));
    }

    public static function parseProfileMarkdownBio($bio) {
        if(getSetting('profiles.allow_profile_bio_markdown')){
            $parsedOutput = Purifier::clean(Markdown::convert($bio)->getContent());
            return $parsedOutput;
        }
        return $bio;
    }

    public static function parseSafeHTML($text) {
        return  Purifier::clean((str_replace("\n", "<br>", strip_tags($text))));
    }

    /**
     * Fetches list of all public pages to be show in footer.
     * @return mixed
     */
    public static function getFooterPublicPages() {
        $pages = [];
        if (InstallerServiceProvider::checkIfInstalled()) {
            $pages = PublicPage::where('shown_in_footer', 1)->orderBy('page_order')->get();
        }
        return $pages;
    }

    public static function getFooterCopyright(bool $compact = false): string
    {
        $text = trim((string) getSetting('site.footer_copyright_text'));
        $year = date('Y');
        $siteName = (string) getSetting('site.name');

        if ($text !== '') {
            return strtr($text, ['{year}' => $year, '{site_name}' => $siteName]);
        }

        return '© '.$year.' '.$siteName.($compact ? '' : '. '.__('All rights reserved.'));
    }

    /**
     * Get Privacy page.
     * @return mixed
     */
    public static function getPrivacyPage() {
        try{
            return PublicPage::where('is_privacy', 1)->first();
        }
        catch (\Exception $e){
            return PublicPage::first();
        }
    }

    /**
     * Get TOS page.
     * @return mixed
     */
    public static function getTOSPage() {
        try{
            return PublicPage::where('is_tos', 1)->first();
        }
        catch (\Exception $e){
            return PublicPage::first();
        }
    }

    /*
    * Get Privacy page.
    * @return mixed
    */
    public static function getModelAgreementPage() {
        try{
            return PublicPage::where('slug', 'creator-agreement')->first();
        }
        catch (\Exception $e){
            return null;
        }
    }

    /**
     * Verifies if admin added a minimum posts limit for creators to earn money.
     * @param $user
     * @return bool
     */
    public static function creatorCanEarnMoney($user) {
        if(intval(getSetting("compliance.minimum_posts_until_creator")) > 0 && count($user->posts) < intval(getSetting('compliance.minimum_posts_until_creator'))){
            return false;
        }
        if(getSetting('compliance.monthly_posts_before_inactive') && !$user->is_active_creator){
            return false;
        }
        return true;
    }

    /**
     * Returns the preferred user local
     * TODO: This is only used in the payments module | Maybe delete it and use LocaleProvider based alternative.
     * @return \Illuminate\Config\Repository|mixed|null
     */
    public static function getPreferredLanguage() {
        // Defaults
        if (!Session::has('locale')) {
            if (InstallerServiceProvider::checkIfInstalled()) {
                return getSetting('site.default_site_language');
            } else {
                return Config::get('app.locale');
            }
        }
        // If user has locale setting, use that one
        if (isset(Auth::user()->settings['locale'])) {
            return Auth::user()->settings['locale'];
        }
        return getSetting('site.default_site_language');
    }

    /**
     * Fetches the default OGMeta image to be used (except for profile).
     * @return \Illuminate\Config\Repository|mixed|string|null
     */
    public static function getOGMetaImage() {
        if(getSetting('site.default_og_image')){
            return getSetting('site.default_og_image');
        }
        return asset('img/logo-black.png');
    }

    /**
     * Gets the administrator-configured site direction.
     */
    public static function getSiteDirection(): string
    {
        if (!InstallerServiceProvider::checkIfInstalled()) {
            return config('app.site.default_site_direction') === 'rtl' ? 'rtl' : 'ltr';
        }

        return getSetting('site.default_site_direction') === 'rtl' ? 'rtl' : 'ltr';
    }

    public static function getSiteTheme(): string
    {
        return ThemePreference::resolvedTheme();
    }

    public static function isDarkMode(): bool
    {
        return ThemePreference::isDark();
    }

    public static function isRtl(): bool
    {
        return self::getSiteDirection() === 'rtl';
    }

    public static function getThemeCssSuffix(): string
    {
        return self::isRtl() ? '.rtl' : '';
    }

    public static function getVersionedAsset(string $path): string
    {
        $publicPath = public_path($path);
        $version = is_file($publicPath) ? filemtime($publicPath) : config('app.version');

        return asset($path).'?v='.$version;
    }

    public static function getFontStylesheet(): string
    {
        $source = InstallerServiceProvider::checkIfInstalled()
            ? getSetting('site.font_source', 'google')
            : 'local';

        return match ($source) {
            'local' => self::getVersionedAsset('css/fonts/open-sans.css'),
            'bunny' => 'https://fonts.bunny.net/css?family=open-sans:300,400,500,600,700&display=swap',
            default => 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;500;600;700&display=swap',
        };
    }

    /** @return array<string, string> Stylesheets in base-then-brand order. */
    public static function getThemeStylesheets(): array
    {
        $stylesheets = [
            'app-bootstrap' => self::getVersionedAsset('css/theme/bootstrap'.self::getThemeCssSuffix().'.css'),
        ];
        $productTheme = (string) config('theme.stylesheet', 'css/theme/theme.css');

        if (is_file(public_path($productTheme))) {
            $stylesheets['app-theme'] = self::getVersionedAsset($productTheme);
        }

        return $stylesheets;
    }

    public static function getThemeConfig(): array
    {
        return [
            'preference' => ThemePreference::selectedPreference(),
            'allowSwitch' => (bool) getSetting('site.allow_theme_switch'),
            'labels' => ['light' => __('Light'), 'dark' => __('Dark'), 'auto' => __('Auto (system)')],
            'toggleLabels' => ['light' => __('Light mode'), 'dark' => __('Dark mode'), 'auto' => __('Auto (system)')],
        ];
    }

    public static function getThemeSwitchLabel(bool $compact = false): string
    {
        $config = self::getThemeConfig();

        return $config[$compact ? 'labels' : 'toggleLabels'][ThemePreference::nextPreference()];
    }

    public static function getNavbarThemeClass(): string
    {
        return self::isDarkMode() ? 'navbar-dark' : 'navbar-light';
    }

    public static function getNavbarBackgroundClass(): string
    {
        return self::isDarkMode() ? 'bg-dark' : 'bg-white';
    }

    public static function getCurrentThemeLogo(): string
    {
        return asset(self::isDarkMode() ? getSetting('site.dark_logo') : getSetting('site.light_logo'));
    }

    public static function getCreationMenuOptions(): array
    {
        if (!Auth::check() || (!self::canViewCreationPage() && !self::canAccessCreatorOnboarding())) {
            return [];
        }

        $createOptions = [[
            'label' => __('Post'),
            'url' => route('posts.create'),
            'icon' => 'add-outline',
        ]];

        if (getSetting('stories.stories_enabled')) {
            $createOptions[] = [
                'label' => __('Story'),
                'url' => route('stories.create'),
                'icon' => 'time-outline',
            ];
        }

        if (getSetting('reels.reels_enabled')) {
            $createOptions[] = [
                'label' => __('Reel'),
                'url' => route('reels.create'),
                'icon' => 'film-outline',
            ];
        }

        if (!self::canViewCreationPage()) {
            return [[
                'label' => self::getCreatorOnboardingLabel(),
                'url' => self::getCreatorOnboardingUrl(),
                'icon' => 'person-add-outline',
            ]];
        }

        return $createOptions;
    }

    public static function getMobileNavbarCreateMenu(): array
    {
        $mobileCreateRoutes = ['posts.create', 'stories.create', 'reels.create', 'my.streams.get'];

        if (!Auth::check()) {
            return [
                'mobileCreateOptions' => [],
                'mobilePrimaryCreateOption' => null,
                'mobileStreamOption' => null,
                'mobileCreateActionCount' => 0,
                'mobileSingleCreateOption' => null,
                'mobileCreateRoutes' => $mobileCreateRoutes,
            ];
        }

        $canViewCreationPage = self::canViewCreationPage();
        $mobileCreateOptions = self::getCreationMenuOptions();

        $mobileStreamOption = null;
        $streamCreationVisible = getSetting('streams.streaming_driver') !== 'none';

        if ($canViewCreationPage && $streamCreationVisible) {
            $mobileUserInProgressStream = StreamsServiceProvider::getUserInProgressStream();
            $mobileStreamOption = [
                'label' => $mobileUserInProgressStream ? __('On air') : __('Go live'),
                'url' => route('my.streams.get').($mobileUserInProgressStream ? '' : '?action=create'),
                'icon' => 'play-circle-outline',
            ];
        }

        $mobilePrimaryCreateOption = $mobileCreateOptions[0] ?? null;
        $mobileCreateActionCount = count($mobileCreateOptions) + ($mobileStreamOption ? 1 : 0);

        return [
            'mobileCreateOptions' => $mobileCreateOptions,
            'mobilePrimaryCreateOption' => $mobilePrimaryCreateOption,
            'mobileStreamOption' => $mobileStreamOption,
            'mobileCreateActionCount' => $mobileCreateActionCount,
            'mobileSingleCreateOption' => $mobilePrimaryCreateOption ?: $mobileStreamOption,
            'mobileCreateRoutes' => $mobileCreateRoutes,
        ];
    }

    public static function getLatestGlobalMessage() {
        try {
            if (!Schema::hasTable('global_announcements')) {
                return null;
            }

            $messages = GlobalAnnouncement::all();
            $skippedIDs = [];

            foreach($messages as $message){
                if(request()->cookie('dismissed_banner_'.$message->id)){
                    $skippedIDs[] = $message->id;
                }
            }

            $userIsVerified = Auth::check() && self::isUserVerified();

            $message = GlobalAnnouncement::orderBy('created_at', 'desc')
                ->where('is_published', 1)
                ->when(!$userIsVerified, fn ($q) => $q->where('id_verified_only', false))
                ->whereNotIn('id', $skippedIDs)
                ->first();

            return $message;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function isUserOnline($userId, string $channelName = 'presence-global'): bool
    {
        $conn = config('broadcasting.connections.pusher');   // already swapped to soketi when needed
        $key = data_get($conn, 'key');
        $secret = data_get($conn, 'secret');
        $appId = data_get($conn, 'app_id');
        $opts = (array) data_get($conn, 'options', []);

        // Guard: only require key/secret/app_id (cluster is NOT required for Soketi)
        if (!$key || !$secret || !$appId) {
            return false;
        }

        // Build client options for either backend
        $clientOptions = [];
        foreach (['cluster', 'host', 'port', 'scheme', 'timeout'] as $k) {
            if (array_key_exists($k, $opts)) {
                $clientOptions[$k] = $opts[$k];
            }
        }
        if (isset($clientOptions['port'])) {
            $clientOptions['port'] = (int) $clientOptions['port']; // SDK expects int
        }

        // Prefer explicit useTLS; otherwise derive from scheme
        $clientOptions['useTLS'] = array_key_exists('useTLS', $opts)
            ? (bool) $opts['useTLS']
            : (($opts['scheme'] ?? 'http') === 'https');

        // Pusher PHP SDK (same for Soketi)
        try {
            $pusher = new Pusher($key, $secret, $appId, $clientOptions);
        } catch (PusherException $e) {
            Log::warning('Presence lookup failed', ['error' => $e->getMessage()]);
            return false;
        }

        // Ensure proper presence prefix
        if (strpos($channelName, 'presence-') !== 0) {
            $channelName = 'presence-'.ltrim($channelName, '-');
        }

        try {
            $resp = $pusher->get('/channels/'.$channelName.'/users');
            foreach ((array) ($resp->users ?? []) as $member) {
                if ((string) ($member->id ?? '') === (string) $userId) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Presence lookup failed', ['error' => $e->getMessage()]);
        }

        return false;
    }

    public static function getReportLinks(UserReport $report): array
    {
        if ($report->deletedContentReference()) {
            return [
                'admin' => null,
                'public' => null,
            ];
        }

        try {
            if ($report->stream_id && $report->reportedStream) {
                return [
                    'admin' => StreamResource::getUrl('edit', ['record' => $report->stream_id]),
                    'public' => route('public.stream.get', [
                        'streamID' => $report->reportedStream->id,
                        'slug' => $report->reportedStream->slug,
                    ]),
                ];
            }

            if ($report->message_id) {
                return [
                    'admin' => UserMessageResource::getUrl('edit', ['record' => $report->message_id]),
                    'public' => null,
                ];
            }

            if ($report->post_id && $report->reportedUser) {
                return [
                    'admin' => PostResource::getUrl('edit', ['record' => $report->post_id]),
                    'public' => route('posts.get', [
                        'post_id' => $report->post_id,
                        'username' => $report->reportedUser->username,
                    ]),
                ];
            }

            if ($report->story_id && $report->reportedStory) {
                return [
                    'admin' => StoryResource::getUrl('edit', ['record' => $report->story_id]),
                    'public' => null,
                ];
            }

            if ($report->reel_comment_id && $report->reportedReelComment) {
                return [
                    'admin' => null,
                    'public' => route('reels.get', ['reel_id' => $report->reportedReelComment->reel_id]),
                ];
            }

            if ($report->reel_id && $report->reportedReel) {
                return [
                    'admin' => ReelResource::getUrl('edit', ['record' => $report->reel_id]),
                    'public' => route('reels.get', ['reel_id' => $report->reel_id]),
                ];
            }

            if ($report->reportedUser) {
                return [
                    'admin' => UserResource::getUrl('edit', ['record' => $report->user_id]),
                    'public' => route('profile', ['username' => $report->reportedUser->username]),
                ];
            }
        } catch (\Throwable $e) {
            // Optionally log the error
        }

        return [
            'admin' => null,
            'public' => null,
        ];
    }

    public static function resolveReportType(UserReport $report): string
    {
        if ($report->stream_id) {
            return 'Stream';
        }

        if ($report->message_id) {
            return 'Message';
        }

        if ($report->post_id) {
            return 'Post';
        }

        if ($report->story_id) {
            return 'Story';
        }

        if ($report->reel_comment_id) {
            return 'Reel comment';
        }

        if ($report->reel_id) {
            return 'Reel';
        }

        return 'User';
    }

    public static function getTotalLikesForUser($userID): int {
        $count = DB::table('reactions as r')
            ->join('posts as p', 'p.id', '=', 'r.post_id')
            ->where('p.user_id', $userID)
            ->where('p.status', Post::APPROVED_STATUS)
            ->count();
        return $count;
    }

    public static function getProfileSocialLinkItems(User $user): array
    {
        if (!getSetting('profiles.social_links_enabled')) {
            return [];
        }

        $catalog = config('social_networks', []);

        $enabledKeys = getSetting('profiles.allowed_social_network_keys', []);
        if (empty($enabledKeys)) {
            $enabledKeys = array_keys($catalog);
        }

        $allowed = array_intersect_key($catalog, array_flip($enabledKeys));

        $connections = data_get($user, 'settings.connections', []);
        if (!is_array($connections)) {
            return [];
        }

        $items = [];

        foreach ($connections as $key => $conn) {
            $key = trim((string) $key);

            $url = trim((string) data_get($conn, 'url', ''));
            $visible = (bool) data_get($conn, 'visible', true);

            if (!$visible || $url === '') {
                continue;
            }

            if (!isset($allowed[$key])) {
                continue;
            }

            // Only allow http/https links
            if (!preg_match('#^https?://#i', $url)) {
                continue;
            }

            $meta = $allowed[$key];

            $items[] = [
                'key' => $key,
                'label' => $meta['label'] ?? $key,
                'url' => $url,
                'icon' => $meta['icon'] ?? ['type' => 'ion', 'name' => 'link-outline'],
            ];
        }

        return $items;
    }

    public static function getProfileSocialFormData(): array
    {
        $catalog = config('social_networks', []);

        $enabledKeys = getSetting('profiles.allowed_social_network_keys', []);
        if (empty($enabledKeys)) {
            $enabledKeys = array_keys($catalog);
        }

        $allowed = array_intersect_key($catalog, array_flip($enabledKeys));

        $connections = data_get(Auth::user(), 'settings.connections', []);
        if (!is_array($connections)) {
            $connections = [];
        }

        $rows = [];

        foreach ($connections as $key => $conn) {
            $rows[] = [
                'platform' => $key,
                'value' => $conn['url'] ?? '',
                'visible' => $conn['visible'] ?? true,
            ];
        }

        return [
            'allowedPlatforms' => $allowed,
            'rows' => old('social_links', $rows),
        ];
    }

    /**
     * Turns the mysql collection into a selectize-2 list compatible array format.
     *
     * @param $q
     * @param $id
     * @return array
     */
    public static function selectizeList($id, $doRoleCheck = true)
    {
        $values = ['users' => []];

        if (Auth::user()->role_id == 1 && $doRoleCheck) {
            $users = User::select('id', 'username', 'name', 'avatar')
                ->where('id', '<>', Auth::user()->id)
                ->get();

            foreach ($users as $user) {
                $values['users'][$user->id]['id'] = $user->id;
                $values['users'][$user->id]['username'] = $user->username;
                $values['users'][$user->id]['name'] = $user->name;
                $values['users'][$user->id]['avatar'] = $user->avatar;
                $values['users'][$user->id]['label'] =
                    '<div><img class="searchAvatar" src="uploads/users/avatars/'.$user->avatar.'" alt=""><span class="name">'.$user->name.'</span></div>';
            }
        } else {
            $subbedUsers = Subscription::with(['creator', 'subscriber'])
            ->where(function ($query) use ($id) {
                $query->where('sender_user_id', $id)
                    ->orWhere('recipient_user_id', $id);
            })
                ->whereIn('status', [Subscription::ACTIVE_STATUS, Subscription::CANCELED_STATUS])
                ->where('expires_at', '>', Carbon::now()->toDateTimeString())
                ->get();

            foreach ($subbedUsers as $sub) {
                $userData = $sub->creator->id === $id ? $sub->subscriber : $sub->creator;
                if (!$userData) continue;

                $values['users'][$userData->id]['id'] = $userData->id;
                $values['users'][$userData->id]['username'] = $userData->username;
                $values['users'][$userData->id]['name'] = $userData->name;
                $values['users'][$userData->id]['avatar'] = $userData->avatar;
                $values['users'][$userData->id]['label'] =
                    '<div><img class="searchAvatar" src="uploads/users/avatars/'.$userData->avatar.'" alt=""><span class="name">'.$userData->name.'</span></div>';
            }

            $freeFollowIDs = PostsHelperServiceProvider::getFreeFollowingProfiles(Auth::user()->id);
            $freeFollowUsers = User::select('id', 'username', 'name', 'avatar')
            ->whereIn('id', $freeFollowIDs)
                ->get();

            foreach ($freeFollowUsers as $user) {
                $values['users'][$user->id]['id'] = $user->id;
                $values['users'][$user->id]['username'] = $user->username;
                $values['users'][$user->id]['name'] = $user->name;
                $values['users'][$user->id]['avatar'] = $user->avatar;
                $values['users'][$user->id]['label'] =
                    '<div><img class="searchAvatar" src="uploads/users/avatars/'.$user->avatar.'" alt=""><span class="name">'.$user->name.'</span></div>';
            }

            if (ProfileMonetizationServiceProvider::userHasFreeProfile(Auth::user())) {
                $list = ListsHelperServiceProvider::getUserFollowersList();
                /** @var \Illuminate\Support\Collection<int, User> $followers */
                $followers = $list->getRelationValue('members');
                foreach ($followers as $user) {
                    $values['users'][$user->id]['id'] = $user->id;
                    $values['users'][$user->id]['username'] = $user->username;
                    $values['users'][$user->id]['name'] = $user->name;
                    $values['users'][$user->id]['avatar'] = $user->avatar;
                    $values['users'][$user->id]['label'] =
                        '<div><img class="searchAvatar" src="uploads/users/avatars/'.$user->avatar.'" alt=""><span class="name">'.$user->name.'</span></div>';
                }
            }
        }

        // blocked list (also: your current code can crash if list missing)
        $blockedList = Auth::user()->lists->firstWhere('type', 'blocked');
        $blockedUsers = $blockedList ? ListsHelperServiceProvider::getListMembers($blockedList->id) : [];

        $values['users'] = array_filter($values['users'], function ($contact) use ($blockedUsers) {
            return !in_array($contact['id'], $blockedUsers);
        });

        return $values['users'];
    }

    public static function shouldRenderExploreMenu()
    {
        $vis = getSetting('site.explore_menu_visibility') ?? 'both';
        return match ($vis) {
            'none'  => false,
            'auth'  => Auth::check(),
            'guest' => !Auth::check(),
            'both'  => true,
            default => true,
        };
    }

    public static function shouldRenderExplorePeopleFollowButton(User $user): bool
    {
        if (!getSetting('site.explore_follow_button_enabled', true)) {
            return false;
        }

        return !Auth::check() || Auth::id() !== $user->id;
    }

    public static function getExplorePeopleFollowButtonState(User $user): array
    {
        static $stateCache = [];

        $cacheKey = (Auth::id() ?: 'guest').':'.$user->id;
        if (isset($stateCache[$cacheKey])) {
            return $stateCache[$cacheKey];
        }

        $followTitle = __('Follow');
        $isFollowing = self::shouldRenderExplorePeopleFollowButton($user)
            && Auth::check()
            && ListsHelperServiceProvider::loggedUserIsFollowingUser($user->id);
        $action = $isFollowing ? 'unfollow' : 'follow';

        $stateCache[$cacheKey] = [
            'action' => $action,
            'title' => $isFollowing ? __('Unfollow') : $followTitle,
            'follow_title' => $followTitle,
            'unfollow_title' => __('Unfollow'),
        ];

        return $stateCache[$cacheKey];
    }

    public static function getGlobalAdditionalJS() {

        $assets = [];
        if(getSetting('site.pwa_enabled')){
            $assets[] = '/js/PWABanner.js';
        }
        if(getSetting('profiles.push_notifications_enabled')){
            $assets[] = '/js/WebPushManager.js';

        }
        if(getSetting('compliance.enable_cookies_box')){
            $assets[] = '/libs/vanilla-cookieconsent/dist/cookieconsent.umd.js';
        }
        if(getSetting('payments.conversion_tracking_enabled') && getSetting('compliance.enable_cookies_box')){
            $assets[] = '/js/conversion-tracking.js';
        }
        return $assets;
    }
}
