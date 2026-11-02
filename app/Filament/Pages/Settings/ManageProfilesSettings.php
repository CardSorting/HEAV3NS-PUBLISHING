<?php

namespace App\Filament\Pages\Settings;

use App\Model\CreatorEnrollment;
use App\Model\User;
use App\Providers\ProfileMonetizationServiceProvider;
use App\Providers\SettingsServiceProvider;
use App\Services\CreatorAccessService;
use App\Settings\ProfilesSettings;
use App\Settings\GeneralSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\SettingsPage;
use BackedEnum;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Minishlink\WebPush\VAPID;
use Filament\Schemas\Components\Utilities\Set;
use Throwable;

class ManageProfilesSettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $slug = 'settings/users';

    protected static string $settings = ProfilesSettings::class;

    protected static ?string $title = 'Users Settings';

    protected ?bool $hasDatabaseTransactions = true;

    protected bool $pauseUnapprovedScheduledPosts = false;

    protected ?string $pendingCreatorMode = null;

    protected bool $pendingRequireIdentity = false;

    protected int $pausedScheduledPostCount = 0;

    public function save(): void
    {
        if (!$this->canEdit()) {
            return;
        }

        if ($this->creatorModeChangeConsequences() !== []) {
            $this->mountAction('confirmCreatorModeChange');

            return;
        }

        parent::save();
    }

    public function confirmCreatorModeChangeAction(): Action
    {
        return Action::make('confirmCreatorModeChange')
            ->authorize(fn (): bool => $this->canEdit())
            ->requiresConfirmation()
            ->modalHeading(__('Change creator access?'))
            ->modalDescription(__('Review the effects before saving these changes.'))
            ->modalContent(fn () => view('filament.modals.confirmation-details', ['items' => $this->creatorModeChangeConsequences()]))
            ->modalSubmitActionLabel(__('Save changes'))
            ->action(fn () => parent::save());
    }

    protected function creatorModeChangeConsequences(): array
    {
        $previous = getSettingFilament('profiles.creator_access_mode', CreatorAccessService::MODE_EVERYONE);
        $next = $this->data['creator_access_mode'] ?? null;
        $access = app(CreatorAccessService::class);
        $previousRequiresIdentity = $access->requiresIdentityVerification($previous);
        $nextRequiresIdentity = $access->requiresIdentityVerification($next, (bool) ($this->data['require_id_before_publishing'] ?? getSettingFilament('profiles.require_id_before_publishing', false)));

        $addsCreatorRequirement = $previous === CreatorAccessService::MODE_EVERYONE
            && in_array($next, [CreatorAccessService::MODE_INSTANT, CreatorAccessService::MODE_APPROVAL, CreatorAccessService::MODE_CLOSED], true);
        $addsIdentityRequirement = !$previousRequiresIdentity && $nextRequiresIdentity;
        $messages = [];
        if ($addsCreatorRequirement || $addsIdentityRequirement) {
            $messages[] = match (true) {
                $addsCreatorRequirement && $addsIdentityRequirement => __('Creator approval and ID verification will be required to publish; affected scheduled posts return to review.'),
                $addsCreatorRequirement => __('Creator approval will be required to publish; affected scheduled posts return to review.'),
                default => __('ID verification will be required before publishing; affected scheduled posts return to review.'),
            };
        }
        if ($previous !== $next && $next === CreatorAccessService::MODE_INSTANT
            && CreatorEnrollment::query()->where('status', CreatorEnrollment::PENDING_STATUS)->exists()) {
            $messages[] = __('New applications activate instantly. Existing pending applications still need admin review.');
        }

        return $messages;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Profile Settings')
                ->persistTabInQueryString('tab')
                ->columnSpanFull()
                ->tabs([

                    Tabs\Tab::make('General')
                        ->schema([
                            Select::make('profile_monetization_mode')
                                ->options([
                                    ProfileMonetizationServiceProvider::MODE_MIXED => 'Mixed',
                                    ProfileMonetizationServiceProvider::MODE_PAID_ONLY => 'Paid only',
                                    ProfileMonetizationServiceProvider::MODE_FREE_ONLY => 'Free only',
                                ])
                                ->label('Profile mode')
                                ->required()
                                ->helperText('Controls whether users can choose paid profiles, free profiles, or both.')
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?string $state) {
                                    $forcedType = self::forcedProfileTypeForMode($state);

                                    if ($forcedType) {
                                        $set('default_profile_type_on_register', $forcedType);
                                    }
                                }),

                            Select::make('default_profile_type_on_register')
                                ->options(self::defaultProfileTypeOptions())
                                ->label('New user profile type')
                                ->required()
                                ->disabled(fn (Get $get) => self::forcedProfileTypeForMode($get('profile_monetization_mode')) !== null)
                                ->dehydrated()
                                ->helperText('Profile type assigned automatically to new users.'),

                            Toggle::make('allow_users_enabling_open_profiles')
                                ->label('Allow open profiles')
                                ->helperText('Allows users to set their profiles "open", making non-PPV content visible to everyone. Paid only mode overrides this.'),

                            Select::make('default_user_privacy_setting_on_register')
                                ->options([
                                    'public' => 'Public',
                                    'private' => 'Private',
                                ])
                                ->label('Default privacy setting')
                                ->required()
                                ->helperText('Determines if user profiles are public or private by default.'),

                            Select::make('default_users_to_follow')
                                ->label('Default users to follow')
                                ->multiple()
                                ->searchable()
                                // Preload a small default set (optional, e.g. top creators)
                                ->options(
                                    fn () => User::query()
                                        ->orderByDesc('id')   // or by followers_count, created_at, etc.
                                        ->limit(20)
                                        ->pluck('username', 'id')
                                        ->toArray()
                                )
                                // Used when searching in the dropdown
                                ->getSearchResultsUsing(
                                    fn (string $search) => User::query()
                                        ->where('username', 'like', "%{$search}%")
                                        ->orderBy('username')
                                        ->limit(20)
                                        ->pluck('username', 'id')
                                        ->toArray()
                                )
                                // Used to resolve labels for saved values when editing
                                ->getOptionLabelsUsing(
                                    fn (array $values) => User::query()
                                        ->whereIn('id', $values)
                                        ->pluck('username', 'id')
                                        ->toArray()
                                )
                                ->helperText('Select which users new accounts will follow by default.'),

                            TextInput::make('default_wallet_balance_on_register')
                                ->label('Initial wallet balance')
                                ->numeric()
                                ->prefix(fn () => SettingsServiceProvider::leftAlignedCurrencyPosition()
                                    ? SettingsServiceProvider::getWebsiteCurrencySymbol()
                                    : null)
                                ->suffix(fn () => !SettingsServiceProvider::leftAlignedCurrencyPosition()
                                    ? SettingsServiceProvider::getWebsiteCurrencySymbol()
                                    : null)
                                ->helperText('Virtual currency amount given to users upon sign-up.'),

                        ])
                        ->columns(2),

                    Tabs\Tab::make('Creator access')
                        ->schema([
                            Select::make('creator_access_mode')
                                ->label(__('Creator access mode'))
                                ->options([
                                    CreatorAccessService::MODE_CLOSED => __('Closed applications'),
                                    CreatorAccessService::MODE_EVERYONE => __('Everyone'),
                                    CreatorAccessService::MODE_INSTANT => __('Instant activation'),
                                    CreatorAccessService::MODE_APPROVAL => __('Admin approval'),
                                ])
                                ->required()
                                ->live()
                                ->columnSpanFull()
                                ->helperText(fn (Get $get): ?string => match ($get('creator_access_mode')) {
                                    CreatorAccessService::MODE_EVERYONE => __('Users can publish without a creator application.'),
                                    CreatorAccessService::MODE_INSTANT => __('Creator applications are approved automatically.'),
                                    CreatorAccessService::MODE_APPROVAL => __('Creator applications require administrator approval.'),
                                    CreatorAccessService::MODE_CLOSED => __('Creator applications are closed. Administrators can grant access.'),
                                    default => null,
                                }),
                            Toggle::make('require_id_before_publishing')
                                ->label(__('Require ID before publishing'))
                                ->helperText(__('Requires complete identity verification as well as creator access.'))
                                ->visible(fn (Get $get): bool => in_array($get('creator_access_mode'), [CreatorAccessService::MODE_INSTANT, CreatorAccessService::MODE_APPROVAL, CreatorAccessService::MODE_CLOSED], true))
                                ->rules(['boolean']),
                            Toggle::make('show_locked_creation_pages')
                                ->label(__('Allow creation page previews'))
                                ->helperText(__('Allows users to view creation pages before meeting publishing requirements.'))
                                ->visible(fn (Get $get): bool => in_array($get('creator_access_mode'), [CreatorAccessService::MODE_INSTANT, CreatorAccessService::MODE_APPROVAL], true)),
                            Toggle::make('require_creator_application_message')
                                ->label(__('Require application message'))
                                ->helperText(__('Applicants must describe their content before submitting for admin approval.'))
                                ->visible(fn (Get $get): bool => $get('creator_access_mode') === CreatorAccessService::MODE_APPROVAL)
                                ->rules(['boolean']),
                            RichEditor::make('creator_application_instructions')
                                ->label(__('Application instructions'))
                                ->helperText(__('Shown above the creator application form.'))
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike', 'link',
                                    'bulletList', 'orderedList',
                                ])
                                ->formatStateUsing(fn ($state) => is_string($state) ? $state : '')
                                ->dehydrateStateUsing(fn ($state) => is_string($state) ? $state : '')
                                ->visible(fn (Get $get): bool => in_array($get('creator_access_mode'), [CreatorAccessService::MODE_INSTANT, CreatorAccessService::MODE_APPROVAL], true))
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Tabs\Tab::make('Profiles')
                        ->schema([

                            Toggle::make('allow_profile_qr_code')
                                ->label('Allow profile QR code')
                                ->helperText("Displays a QR code button on profiles for easy sharing."),

                            Toggle::make('allow_gender_pronouns')
                                ->label('Allow gender pronouns')
                                ->helperText('Enable users to set gender pronouns on their profile.'),

                            Toggle::make('allow_hyperlinks')
                                ->label('Allow hyperlinks')
                                ->helperText('Enable links to be clickable in posts, messages and profile bios.'),

                            Toggle::make('disable_website_link_on_profile')
                                ->label('Disable website link')
                                ->helperText('Removes the external website link field from user profiles.'),

                            Toggle::make('allow_profile_bio_markdown')
                                ->label('Enable markdown in bio')
                                ->helperText('Allow users to use Markdown formatting in their profile bio.'),

                            Toggle::make('disable_profile_offers')
                                ->label('Disable profile offers')
                                ->helperText('Turns off the ability for users to set promotional profile offers.'),

                            Toggle::make('disable_profile_bio_excerpt')
                                ->label('Disable bio excerpt')
                                ->helperText('If enabled, bio previews/excerpts will not be shown.'),

                            Toggle::make('hide_profile_followers_count')
                                ->label('Disable follower count')
                                ->helperText('If enabled, follower and likes counts will be hidden on profile pages.'),

                            TextInput::make('max_profile_bio_length')
                                ->label('Max bio length')
                                ->numeric()
                                ->suffix('characters')
                                ->helperText('Set to 0 for no limit.'),

                        ])
                        ->columns(2),

                    Tabs\Tab::make('Visibility & Tracking')
                        ->schema([

                            Toggle::make('show_online_users_indicator')
                                ->label('Show online status')
                                ->helperText('Display a real-time online indicator on profiles. WebSockets must be set up.'),

                            Toggle::make('record_users_last_activity_time')
                                ->label('Track last activity timestamp')
                                ->helperText('Log the most recent activity time for each user.'),

                            Toggle::make('record_users_last_ip_address')
                                ->label('Track last IP address')
                                ->helperText('Store the last known IP address for audit or security.'),
                        ])
                        ->columns(2),

                    Tabs\Tab::make('Notifications')
                        ->schema([
                            Tabs::make('Notifications Settings')
                                ->persistTabInQueryString('cat')
                                ->columnSpanFull()
                                ->tabs([
                                    Tabs\Tab::make('User Controls')
                                        ->schema([

                                            Toggle::make('enable_toast_notification_setting')
                                                ->label('Enable toast notifications setting')
                                                ->helperText('Allows users to enable or disable in-app toast notifications from their notification settings.')
                                            ->columnSpanFull(),

                                            Toggle::make('enable_new_post_notification_setting')
                                                ->label('Enable post notifications setting')
                                                ->helperText('Allows users to control whether they receive new post notifications.')
                                                ->columnSpanFull()
                                                ->live(),

                                            Toggle::make('default_new_post_notification_setting')
                                                ->label('Default post notifications setting')
                                                ->helperText('Whether new post notifications are enabled by default for new users.')
                                                ->columnSpanFull(),

                                        ])
                                        ->columns(2),

                                    Tabs\Tab::make('Web Push')
                                        ->schema([
                                            Placeholder::make('webpush_info')
                                                ->hiddenLabel()
                                                ->content(fn () => view('filament.partials.webpush-info-box'))
                                                ->columnSpanFull()
                                                ->hidden(function (callable $get) {
                                                    return filled($get('webpush_contact_email'))
                                                        && filled($get('webpush_public_key'))
                                                        && filled($get('webpush_private_key'));
                                                }),

                                            Group::make([
                                                Action::make('generateWebPushKeys')
                                                    ->label('Generate VAPID keys')
                                                    ->icon('heroicon-o-key')
                                                    ->color('gray')
                                                    ->requiresConfirmation()
                                                    ->modalHeading('Regenerate VAPID keys?')
                                                    ->modalDescription('Replacing the current VAPID keys will invalidate existing push subscriptions. Users may need to subscribe again.')
                                                    ->action(function (Set $schemaSet, callable $get) {
                                                        try {
                                                            $keys = VAPID::createVapidKeys();

                                                            if (!filled($get('webpush_contact_email'))) {
                                                                $schemaSet('webpush_contact_email', config('mail.from.address') ?: 'admin@example.com');
                                                            }

                                                            $schemaSet('webpush_public_key', $keys['publicKey'] ?? '');
                                                            $schemaSet('webpush_private_key', $keys['privateKey'] ?? '');

                                                            Notification::make()
                                                                ->title('VAPID keys generated')
                                                                ->success()
                                                                ->send();
                                                        } catch (Throwable $e) {
                                                            report($e);

                                                            Notification::make()
                                                                ->title('Could not generate VAPID keys')
                                                                ->body('Key generation failed on this server. Please check your PHP OpenSSL setup or generate the keys manually and paste them here.')
                                                                ->danger()
                                                                ->send();
                                                        }
                                                    }),
                                            ])
                                                ->columnSpanFull(),

                                            Toggle::make('push_notifications_enabled')
                                                ->label('Enable web push notifications')
                                                ->helperText('Globally enables browser push notifications for users.')
                                                ->live(),

                                            TextInput::make('webpush_contact_email')
                                                ->label('Contact email')
                                                ->email()
                                                ->autocomplete(false)
                                                ->placeholder('admin@example.com')
                                                ->helperText('Used internally for Web Push sender identification.')
                                                ->required(fn (callable $get) => (bool) $get('push_notifications_enabled'))
                                                ->disabled(fn (callable $get) => !$get('push_notifications_enabled')),

                                            TextInput::make('webpush_public_key')
                                                ->label('Web Push public key')
                                                ->helperText('VAPID public key used by the browser subscription flow.')
                                                ->required(fn (callable $get) => (bool) $get('push_notifications_enabled'))
                                                ->disabled(fn (callable $get) => !$get('push_notifications_enabled'))
                                                ->columnSpanFull()
                                                ->autocomplete(false),

                                            TextInput::make('webpush_private_key')
                                                ->label('Web Push private key')
                                                ->password()
                                                ->revealable()
                                                ->helperText('VAPID private key used for sending push notifications.')
                                                ->required(fn (callable $get) => (bool) $get('push_notifications_enabled'))
                                                ->disabled(fn (callable $get) => !$get('push_notifications_enabled'))
                                                ->columnSpanFull()
                                                ->autocomplete(false),
                                        ])
                                        ->columns(2),
                                ]),
                        ])
                        ->columns(2),

                    Tabs\Tab::make('Social Links')
                        ->schema([
                            Toggle::make('social_links_enabled')
                                ->label('Enable social links on profiles')
                                ->helperText('Allows users to add social/profile links (Instagram, X, etc.) in Profile Settings.'),

                            Select::make('allowed_social_network_keys')
                                ->label('Allowed social networks')
                                ->multiple()
                                ->searchable()
                                ->options(
                                    fn () => collect(config('social_networks', []))
                                        ->mapWithKeys(fn (array $meta, string $key) => [
                                            $key => ($meta['label'] ?? $key),
                                        ])
                                        ->toArray()
                                )
                                ->helperText('Leave empty for no restrictions. Select values here to restrict what users can choose.')
                                ->hint('Empty = defaults')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),

                    Tabs\Tab::make('Social Auth')
                        ->columns(2)
                        ->schema([

                            Placeholder::make('social_login_info')
                                ->columnSpanFull()
                                ->label('')
                                ->hiddenLabel()
                                ->content(new HtmlString(view('filament.partials.social-login-info-box')->render())),

                            TextInput::make('social_auth_facebook_client_id')->label('Facebook client ID'),
                            TextInput::make('social_auth_facebook_secret')
                                ->label('Facebook client secret')
                                ->password()
                                ->revealable()
                                ->autocomplete(false),

                            TextInput::make('social_auth_twitter_client_id')->label('Twitter client ID'),
                            TextInput::make('social_auth_twitter_secret')
                                ->label('Twitter client secret')
                                ->password()
                                ->revealable()
                                ->autocomplete(false),

                            TextInput::make('social_auth_google_client_id')->label('Google client ID'),
                            TextInput::make('social_auth_google_secret')
                                ->label('Google client secret')
                                ->password()
                                ->revealable()
                                ->autocomplete(false),
                        ]),

                    Tabs\Tab::make('Spotify')
                        ->columns(2)
                        ->schema([

                            Placeholder::make('spotify_info')
                                ->columnSpanFull()
                                ->label('')
                                ->hiddenLabel()
                                ->content(new HtmlString(view('filament.partials.spotify-info-box')->render())),

                            Toggle::make('spotify_enabled')
                                ->label('Enable Spotify integration')
                                ->helperText('Enables Spotify connect, anthem and top artists widgets.')
                                ->columnSpanFull(),

                            TextInput::make('spotify_client_id')
                                ->label('Spotify client ID')
                                ->autocomplete(false),

                            TextInput::make('spotify_client_secret')
                                ->label('Spotify client secret')
                                ->password()
                                ->revealable()
                                ->autocomplete(false),

                            TextInput::make('spotify_top_artists_limit')
                                ->label('Top artists limit')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(20)
                                ->helperText('How many top artists to store/show (1–20).'),

                            Select::make('spotify_top_artists_ranges')
                                ->label('Top artists ranges')
                                ->multiple()
                                ->options([
                                    'short_term' => 'Short term',
                                    'medium_term' => 'Medium term',
                                    'long_term' => 'Long term',
                                ])
                                ->helperText('Select one or more time ranges used to build the top artists list.'),

                        ]),

                ]),
        ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        unset($data['accept_creator_applications']);

        return self::normalizeProfileTypeSettings($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Discard controls retired from older settings forms.
        unset($data['site'], $data['accept_creator_applications']);
        $profiles = app(ProfilesSettings::class);
        $profiles->refresh();
        $this->pendingCreatorMode = $data['creator_access_mode'] ?? null;
        if (!in_array($this->pendingCreatorMode, [CreatorAccessService::MODE_EVERYONE, CreatorAccessService::MODE_INSTANT, CreatorAccessService::MODE_APPROVAL, CreatorAccessService::MODE_CLOSED], true)) {
            throw ValidationException::withMessages(['creator_access_mode' => __('Choose a valid creator access mode.')]);
        }
        $applicationMode = in_array($this->pendingCreatorMode, [CreatorAccessService::MODE_INSTANT, CreatorAccessService::MODE_APPROVAL], true);
        if (!$applicationMode) {
            unset($data['creator_application_instructions'], $data['show_locked_creation_pages']);
        }
        if ($this->pendingCreatorMode === CreatorAccessService::MODE_EVERYONE) {
            unset($data['require_id_before_publishing']);
        }
        if ($this->pendingCreatorMode !== CreatorAccessService::MODE_APPROVAL) {
            unset($data['require_creator_application_message']);
        }
        $this->pendingRequireIdentity = (bool) ($data['require_id_before_publishing'] ?? $profiles->require_id_before_publishing);
        $this->pauseUnapprovedScheduledPosts = $this->pendingCreatorMode !== CreatorAccessService::MODE_EVERYONE;

        return self::normalizeProfileTypeSettings($data);
    }

    protected function beforeSave(): void
    {
        // Run before settings persistence in the page transaction. Retrying an
        // enforced selection must also reconcile schedules when cached state is stale.
        $this->pausedScheduledPostCount = $this->pauseUnapprovedScheduledPosts
            ? app(CreatorAccessService::class)->pauseUnapprovedScheduledPosts($this->pendingCreatorMode, $this->pendingRequireIdentity) : 0;
    }

    protected function afterSave(): void
    {
        $requiresIdentity = app(CreatorAccessService::class)->requiresIdentityVerification($this->pendingCreatorMode, $this->pendingRequireIdentity);
        if ($requiresIdentity) {
            $site = app(GeneralSettings::class);
            $site->refresh();
            $site->hide_identity_checks = false;
            $site->save();
        }

        if ($this->pausedScheduledPostCount > 0) {
            Notification::make()
                ->title(__('Scheduled posts returned to review'))
                ->body(__(':count future posts from users who do not meet publishing requirements were returned to review.', ['count' => $this->pausedScheduledPostCount]))
                ->warning()
                ->send();
        }
    }

    protected static function normalizeProfileTypeSettings(array $data): array
    {
        $forcedType = self::forcedProfileTypeForMode($data['profile_monetization_mode'] ?? null);

        if ($forcedType) {
            $data['default_profile_type_on_register'] = $forcedType;
        }

        return $data;
    }

    protected static function defaultProfileTypeOptions(): array
    {
        return [
            'paid' => 'Paid',
            'free' => 'Free',
            'open' => 'Open',
        ];
    }

    protected static function forcedProfileTypeForMode(?string $mode): ?string
    {
        return match ($mode) {
            ProfileMonetizationServiceProvider::MODE_PAID_ONLY => 'paid',
            ProfileMonetizationServiceProvider::MODE_FREE_ONLY => 'free',
            default => null,
        };
    }
}
