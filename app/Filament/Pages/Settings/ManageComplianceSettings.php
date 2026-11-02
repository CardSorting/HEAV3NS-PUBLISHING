<?php

namespace App\Filament\Pages\Settings;

use App\Providers\SettingsServiceProvider;
use App\Providers\GenericHelperServiceProvider;
use App\Services\CreatorAccessService;
use App\Services\AgeCheck\CountryDetector;
use App\Settings\ComplianceSettings;
use App\Settings\GeneralSettings;
use App\Settings\ModerationSettings;
use App\Settings\ProfilesSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class ManageComplianceSettings extends SettingsPage
{
    use HasPageShield;

    public const MEDIA_MODERATION_TAB_ID = 'media-moderation::data::tab';
    public const PUBLICATION_REVIEW_TAB_ID = 'publication-review::data::tab';

    /** @var array<string, mixed>|null */
    protected ?array $pendingModerationSettings = null;

    protected ?bool $pendingIdentityChecksEnabled = null;

    protected ?bool $hasDatabaseTransactions = true;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $slug = 'settings/compliance';

    protected static string $settings = ComplianceSettings::class;

    protected static ?string $title = 'Compliance Settings';

    protected function beforeFill(): void
    {
        $this->ensureAgeGateSettingsExist();
        $this->ensureAccountDeletionSettingsExist();
    }

    protected function beforeSave(): void
    {
        $this->ensureAgeGateSettingsExist();
        $this->ensureAccountDeletionSettingsExist();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['enable_id_checks'] = GenericHelperServiceProvider::areIdentityChecksEnabled();

        if ((bool) ($data['age_gate_enabled'] ?? false)) {
            $data['age_gate_driver'] = ($data['age_gate_mode'] ?? 'checker') === 'oauth'
                ? 'ageverif_oauth'
                : 'ageverif_checker';
        } elseif ((bool) ($data['enable_age_verification_dialog'] ?? false)) {
            $data['age_gate_driver'] = 'built_in';
        } elseif (!in_array($data['age_gate_driver'] ?? null, ['built_in', 'ageverif_checker', 'ageverif_oauth'], true)) {
            $data['age_gate_driver'] = 'none';
        }

        $data['moderation'] = app(ModerationSettings::class)->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingIdentityChecksEnabled = app(CreatorAccessService::class)->requiresIdentityVerification() ? true : null;
        if (array_key_exists('enable_id_checks', $data)) {
            $validated = Validator::make(['data' => ['enable_id_checks' => $data['enable_id_checks']]], [
                'data.enable_id_checks' => ['required', 'boolean'],
            ])->validate();
            $this->pendingIdentityChecksEnabled = (bool) $validated['data']['enable_id_checks'];
        }
        unset($data['enable_id_checks'], $data['site'], $data['enforce_user_identity_checks'], $data['hide_identity_checks']);
        $moderation = is_array($data['moderation'] ?? null)
            ? $data['moderation']
            : [];

        unset($data['moderation']);

        $moderation = array_replace(
            app(ModerationSettings::class)->toArray(),
            $moderation,
        );

        $moderation = $this->validatePublicationReviewSettings($moderation);

        $review = max(0, min(1, (float) ($moderation['review_threshold'] ?? 0.65)));
        $moderation['review_threshold'] = $review;
        $moderation['violation_threshold'] = max(
            $review,
            min(1, (float) ($moderation['violation_threshold'] ?? 0.90)),
        );
        $textReview = max(0, min(1, (float) ($moderation['text_review_threshold'] ?? 0.65)));
        $moderation['text_review_threshold'] = $textReview;
        $moderation['text_violation_threshold'] = max(
            $textReview,
            min(1, (float) ($moderation['text_violation_threshold'] ?? 0.90)),
        );
        $moderation['text_models'] = array_values(array_intersect(
            ['general', 'self-harm'],
            array_map('strval', (array) ($moderation['text_models'] ?? [])),
        )) ?: ['general'];
        $moderation['text_languages'] = array_values(array_intersect(
            ['en', 'fr', 'it', 'pt', 'es', 'ru', 'tr'],
            array_map('strval', (array) ($moderation['text_languages'] ?? [])),
        )) ?: ['en'];
        $moderation['text_flag_classes'] = array_values(array_intersect(
            ['sexual', 'discriminatory', 'insulting', 'violent', 'toxic', 'self-harm'],
            array_map('strval', (array) ($moderation['text_flag_classes'] ?? [])),
        )) ?: ['sexual', 'discriminatory', 'insulting', 'violent', 'toxic'];
        $moderation['video_processing_mode'] = in_array($moderation['video_processing_mode'] ?? null, [
            ModerationSettings::VIDEO_PROCESSING_AUTO,
            ModerationSettings::VIDEO_PROCESSING_SYNC,
            ModerationSettings::VIDEO_PROCESSING_ASYNC,
        ], true) ? $moderation['video_processing_mode'] : ModerationSettings::VIDEO_PROCESSING_AUTO;
        $videoInterval = (float) ($moderation['video_interval_seconds'] ?? 0);
        $moderation['video_interval_seconds'] = in_array($videoInterval, [0.0, 0.5, 1.0, 2.0, 3.0, 4.0, 5.0], true)
            ? $videoInterval
            : 0.0;
        $moderation['max_concurrent_video_jobs'] = max(
            1,
            min(50, (int) ($moderation['max_concurrent_video_jobs'] ?? 1)),
        );
        $maximumVideoDuration = $moderation['video_processing_mode'] === ModerationSettings::VIDEO_PROCESSING_SYNC
            ? 59
            : 3600;
        $moderation['max_video_duration_seconds'] = max(
            1,
            min($maximumVideoDuration, (int) ($moderation['max_video_duration_seconds'] ?? 59)),
        );

        $this->pendingModerationSettings = $moderation;

        $driver = $data['age_gate_driver'] ?? 'none';

        $data['enable_age_verification_dialog'] = $driver === 'built_in';
        $data['age_gate_enabled'] = in_array($driver, ['ageverif_checker', 'ageverif_oauth'], true);
        $data['age_gate_mode'] = $driver === 'ageverif_oauth' ? 'oauth' : 'checker';

        if ($driver === 'ageverif_oauth' && ($data['age_gate_country_detection_driver'] ?? 'none') === 'none') {
            $data['age_gate_countries_mode'] = 'everyone';
            $data['age_gate_countries'] = [];
            $data['age_gate_require_unknown_country'] = true;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->pendingIdentityChecksEnabled !== null) {
            app(ProfilesSettings::class)->refresh();
            $enabled = $this->pendingIdentityChecksEnabled || app(CreatorAccessService::class)->requiresIdentityVerification();
            app(GeneralSettings::class)->refresh()->fill(['hide_identity_checks' => !$enabled])->save();
            $this->data['enable_id_checks'] = $enabled;
        }

        if ($this->pendingModerationSettings === null) {
            return;
        }

        $moderationSettings = app(ModerationSettings::class);
        $moderationSettings->fill($this->pendingModerationSettings);
        $moderationSettings->save();
    }

    protected function validatePublicationReviewSettings(array $moderation): array
    {
        $mode = $moderation['publication_review_mode'] ?? 'manual';
        $areas = $moderation['publication_review_areas'] ?? ['posts', 'reels', 'stories'];

        if (!in_array($mode, ['manual', 'assisted', 'automatic'], true)) {
            throw ValidationException::withMessages([
                'data.moderation.publication_review_mode' => __('Select a valid review mode.'),
            ]);
        }

        if (!is_array($areas) || array_filter($areas, fn ($area) => !is_string($area)) !== []
            || array_diff($areas, ['posts', 'reels', 'stories']) !== []
            || (!empty($moderation['publication_review_enabled']) && $areas === [])) {
            throw ValidationException::withMessages([
                'data.moderation.publication_review_areas' => __('Select at least one valid content area.'),
            ]);
        }

        if (!empty($moderation['publication_review_enabled']) && $mode !== 'manual'
            && (empty($moderation['enabled'])
                || ($moderation['provider'] ?? null) !== 'sightengine'
                || trim((string) ($moderation['sightengine_api_user'] ?? '')) === ''
                || trim((string) ($moderation['sightengine_api_secret'] ?? '')) === '')) {
            throw ValidationException::withMessages([
                'data.moderation.publication_review_mode' => __('Enable AI scanning and configure Sightengine credentials before using AI review.'),
            ]);
        }

        $moderation['publication_review_enabled'] = (bool) ($moderation['publication_review_enabled'] ?? false);
        $moderation['publication_review_mode'] = $mode;
        $moderation['publication_review_areas'] = array_values(array_unique($areas));

        return $moderation;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Compliance Settings')
                ->persistTabInQueryString('tab')
                ->columnSpanFull()
                ->tabs([

                    Tab::make('General')
                        ->columns(2)
                        ->schema([
                            Toggle::make('enable_cookies_box')
                                ->label('Enable cookies box')
                                ->helperText("Cookies consent dialog box to be used for GDPR.")
                                ->columnSpanFull(),
                            Toggle::make('allow_user_email_changes')
                                ->label('Allow user email changes')
                                ->helperText('Lets users request a verified account email change from their account settings.')
                                ->columnSpanFull(),
                        ]),

                    Tab::make('ID Checks')
                        ->columns(2)
                        ->schema([
                            Toggle::make('enable_id_checks')
                                ->label(__('Enable ID checks'))
                                ->helperText(fn (): string => app(CreatorAccessService::class)->requiresIdentityVerification()
                                    ? __('Required by Creator access settings.')
                                    : __('Allows users to verify their identity and receive a verified badge.'))
                                ->disabled(fn (): bool => app(CreatorAccessService::class)->requiresIdentityVerification())
                                ->rules(['boolean']),

                            Toggle::make('delete_verification_assets_after_review')
                                ->label('Delete verification assets after review')
                                ->helperText('Automatically deletes uploaded ID files after approval or rejection. Verification status is preserved.'),

                            Toggle::make('enforce_tos_check_on_id_verify')
                                ->label('TOS agreement on ID verify')
                                ->helperText(__('Requires acceptance of the Terms of Use on the ID verification page. The Creator Agreement is accepted in the creator application.')),

                            Toggle::make('enforce_media_agreement_on_id_verify')->label('Media agreement on ID verify')
                                ->helperText("If enabled, a media-agreement checkbox will be shown on ID-verify page. CCBill compliance requirement."),

                            RichEditor::make('id_verify_custom_message_box')
                                ->label('Custom ID verify message')
                                ->helperText('Shows additional info on the ID verification page, near the manual upload form.')
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike', 'link',
                                    'bulletList', 'orderedList',
                                ])
                                ->formatStateUsing(fn ($state) => is_string($state) ? $state : '')
                                ->dehydrateStateUsing(fn ($state) => is_string($state) ? $state : '')
                                ->columnSpanFull(),

                        ]),

                    Tab::make('Age Gate')
                        ->columns(2)
                        ->schema([
                            Select::make('age_gate_driver')
                                ->label('Site entry age gate')
                                ->options([
                                    'none' => 'Disabled',
                                    'built_in' => 'Built-in consent dialog',
                                    'ageverif_checker' => 'AgeVerif client-side checker',
                                    'ageverif_oauth' => 'AgeVerif backend OAuth',
                                ])
                                ->default('none')
                                ->required()
                                ->live()
                                ->helperText('Choose who handles the site entry age gate. Backend OAuth lets this site decide before rendering; client-side checker lets AgeVerif handle the browser check.')
                                ->columnSpanFull(),

                            Placeholder::make('age_gate_ageverif_callback_info')
                                ->label('')
                                ->hiddenLabel()
                                ->visible(fn ($get) => $get('age_gate_driver') === 'ageverif_oauth')
                                ->content(new HtmlString(view('filament.partials.ageverif-callback-info-box')->render()))
                                ->columnSpanFull(),

                            TextInput::make('age_verification_cancel_url')
                                ->label('Cancel redirect URL')
                                ->helperText('Where visitors are sent when they leave or decline the built-in consent dialog.')
                                ->visible(fn ($get) => $get('age_gate_driver') === 'built_in')
                                ->columnSpanFull(),

                            TextInput::make('age_gate_ageverif_public_key')
                                ->label('AgeVerif public key')
                                ->helperText('Used by the AgeVerif Checker script.')
                                ->visible(fn ($get) => $get('age_gate_driver') === 'ageverif_checker')
                                ->columnSpanFull(),

                            TextInput::make('age_gate_ageverif_oauth_client_id')
                                ->label('AgeVerif OAuth client ID')
                                ->visible(fn ($get) => $get('age_gate_driver') === 'ageverif_oauth'),

                            TextInput::make('age_gate_ageverif_oauth_client_secret')
                                ->label('AgeVerif OAuth client secret')
                                ->visible(fn ($get) => $get('age_gate_driver') === 'ageverif_oauth')
                                ->password()
                                ->revealable()
                                ->autocomplete('new-password'),

                            Select::make('age_gate_ageverif_challenges')
                                ->label('Allowed AgeVerif challenges')
                                ->helperText('Optional. Leave empty to use all challenges enabled in AgeVerif.')
                                ->placeholder('Use all enabled challenges')
                                ->options([
                                    'selfie' => 'Selfie',
                                    'email_age' => 'Email age',
                                    'credit_card' => 'Credit card',
                                    'ticket' => 'Ticket',
                                    'anonymage' => 'Anonymage',
                                    'pleenk' => 'Pleenk',
                                    'paypal' => 'PayPal',
                                    'agego' => 'AgeGo',
                                    'agekey' => 'AgeKey',
                                ])
                                ->multiple()
                                ->native(false)
                                ->dehydrateStateUsing(fn ($state) => is_array($state) ? array_values(array_filter(array_map(fn ($challenge) => trim((string) $challenge), $state))) : [])
                                ->formatStateUsing(fn ($state) => is_string($state) && $state !== '' ? array_values(array_filter(array_map('trim', explode(',', $state)))) : (is_array($state) ? $state : []))
                                ->visible(fn ($get) => in_array($get('age_gate_driver'), ['ageverif_checker', 'ageverif_oauth'], true))
                                ->columnSpanFull(),

                            Select::make('age_gate_country_detection_driver')
                                ->label('Server-side country detection')
                                ->options([
                                    'none' => 'None - require everyone',
                                    'cloudflare' => 'Cloudflare CF-IPCountry header',
                                    'abstract' => 'AbstractAPI IP Geolocation',
                                ])
                                ->default('none')
                                ->required()
                                ->live()
                                ->helperText('Used only by backend OAuth mode. AgeVerif portal geolocation applies only to the client-side checker driver.')
                                ->visible(fn ($get) => $get('age_gate_driver') === 'ageverif_oauth')
                                ->columnSpanFull(),

                            TextInput::make('age_gate_abstract_api_key')
                                ->label('AbstractAPI IP Geolocation key')
                                ->visible(fn ($get) => $get('age_gate_driver') === 'ageverif_oauth' && $get('age_gate_country_detection_driver') === 'abstract')
                                ->required(fn ($get) => $get('age_gate_driver') === 'ageverif_oauth' && $get('age_gate_country_detection_driver') === 'abstract')
                                ->password()
                                ->revealable()
                                ->autocomplete('new-password')
                                ->columnSpanFull(),

                            Select::make('age_gate_countries_mode')
                                ->label('Require verification for')
                                ->options([
                                    'everyone' => 'Everyone',
                                    'selected' => 'Selected countries',
                                ])
                                ->default('everyone')
                                ->required()
                                ->live()
                                ->visible(fn ($get) => $get('age_gate_driver') === 'ageverif_oauth' && $get('age_gate_country_detection_driver') !== 'none'),

                            TagsInput::make('age_gate_countries')
                                ->label('Countries requiring verification')
                                ->helperText('Only used by backend OAuth mode. Use ISO 3166-1 alpha-2 country codes, for example: GB, FR, DE.')
                                ->placeholder('GB')
                                ->separator(',')
                                ->suggestions(['GB', 'FR', 'DE', 'IT', 'ES', 'US', 'CA', 'AU'])
                                ->visible(fn ($get) => $get('age_gate_driver') === 'ageverif_oauth' && $get('age_gate_country_detection_driver') !== 'none' && $get('age_gate_countries_mode') === 'selected')
                                ->dehydrateStateUsing(fn ($state) => is_array($state) ? array_values(array_filter(array_map(fn ($country) => strtoupper(trim((string) $country)), $state))) : [])
                                ->rule('array')
                                ->nestedRecursiveRules([
                                    'regex:/^[A-Z]{2}$/',
                                ]),

                            Toggle::make('age_gate_require_unknown_country')
                                ->label('Require verification when country cannot be detected')
                                ->helperText('If enabled, failed or unavailable server-side country detection falls back to requiring verification.')
                                ->visible(fn ($get) => $get('age_gate_driver') === 'ageverif_oauth' && $get('age_gate_country_detection_driver') !== 'none'),

                            Placeholder::make('age_gate_country_detection_debug')
                                ->label('Country detection debug')
                                ->content(fn ($get) => new HtmlString($this->countryDetectionDebugHtml($get)))
                                ->visible(fn ($get) => config('app.debug') && $get('age_gate_driver') === 'ageverif_oauth')
                                ->columnSpanFull(),

                            TextInput::make('age_gate_minimum_age')
                                ->label('Minimum age')
                                ->numeric()
                                ->minValue(18)
                                ->default(18)
                                ->suffix('years')
                                ->required()
                                ->visible(fn ($get) => $get('age_gate_driver') === 'ageverif_oauth'),

                            TextInput::make('age_gate_cookie_lifetime_days')
                                ->label('Access cookie lifetime')
                                ->numeric()
                                ->minValue(1)
                                ->default(90)
                                ->suffix('days')
                                ->required()
                                ->visible(fn ($get) => $get('age_gate_driver') === 'ageverif_oauth'),

                        ]),

                    Tab::make('Post & Creator Limits')
                        ->columns(2)
                        ->schema([
                            TextInput::make('minimum_posts_until_creator')
                                ->numeric()
                                ->label('Posts before monetization')
                                ->helperText("The minimum number of posts for users to be able to earn money. Users won`t be able to receive money until they reach this limit (value = 0 means no limit).")
                            ->columnSpanFull(),
                            TextInput::make('minimum_posts_deletion_limit')
                                ->numeric()
                                ->label('Minimum deletion limit')
                                ->helperText("The minimum posts deletion limit for creators. Enforce them to have a minimum number of posts on their accounts (value = 0 means no limit)."),
                            TextInput::make('monthly_posts_before_inactive')
                                ->numeric()
                                ->label('Monthly post requirement')
                                ->helperText("The minimum monthly posts number a creator must publish before having his account marked as inactive. If value = 0, no inactivity rule will be applied."),
                            Toggle::make('disable_creators_ppv_delete')
                                ->label('Prevent deletion of purchased PPV')
                                ->helperText("If enabled, creators won't be able to delete paid PPV content (paid posts/messages) if already paid by a customer."),
                            Toggle::make('allow_text_only_ppv')
                                ->label('Allow text-only PPV')
                                ->helperText("If enabled, creators will be allowed to sell text-only PPV messages & posts (no media requirements)."),
                        ]),

                    Tab::make('Release Forms')
                        ->columns(2)
                        ->schema([
                            Toggle::make('enable_release_forms')
                                ->label('Enable release forms')
                                ->helperText('Adds a Release forms tab to user settings so creators can upload release documents for admin review.')
                                ->live()
                                ->columnSpanFull(),

                            Toggle::make('release_forms_verified_users_only')
                                ->label('Only for ID-verified users')
                                ->helperText('If enabled, only users with an approved identity check can see and submit release forms.')
                                ->columnSpanFull(),

                            RichEditor::make('release_forms_custom_message_box')
                                ->label('Custom release forms message')
                                ->helperText('Shows additional info on the Release forms page. Useful for sample form links and upload instructions.')
                                ->toolbarButtons([
                                    'bold', 'italic', 'underline', 'strike', 'link',
                                    'bulletList', 'orderedList',
                                ])
                                ->formatStateUsing(fn ($state) => is_string($state) ? $state : '')
                                ->dehydrateStateUsing(fn ($state) => is_string($state) ? $state : '')
                                ->columnSpanFull(),
                        ]),

                    Tab::make('DAC7')
                        ->columns(2)
                        ->schema([
                            Toggle::make('tax_info_dac7_enabled')
                                ->label('Enable DAC7')
                                ->helperText('Enables the tax information tab for collecting required EU/UK tax details from users.'),
                            Toggle::make('tax_info_dac7_withdrawals_enforced')
                                ->label('Enforce for withdrawals')
                                ->helperText('Blocks withdrawals for users who haven’t completed their DAC7 tax information.'),
                            TextInput::make('tax_info_dac7_earnings_limit_before_enforced')
                                ->numeric()
                                ->label('Earnings limit before enforcement')
                                ->prefix(fn () => SettingsServiceProvider::leftAlignedCurrencyPosition()
                                    ? SettingsServiceProvider::getWebsiteCurrencySymbol()
                                    : null)
                                ->suffix(fn () => !SettingsServiceProvider::leftAlignedCurrencyPosition()
                                    ? SettingsServiceProvider::getWebsiteCurrencySymbol()
                                    : null)
                                ->helperText('Year-to-date gross earnings threshold. Set to 0 to enforce from the first transaction.')
                                ->columnSpanFull(),

                        ]),

                    Tab::make('Account Deletion')
                        ->columns(2)
                        ->schema([
                            Toggle::make('account_deletion_enabled')
                                ->label('Enable account deletion requests')
                                ->helperText('Adds a user-facing account deletion request flow under account settings.')
                                ->live()
                                ->columnSpanFull(),

                            Select::make('account_deletion_mode')
                                ->label('Deletion workflow')
                                ->options([
                                    'manual_review' => 'Admin approval only',
                                    'cooldown_auto' => 'Automatic after cooldown',
                                    'manual_review_with_cooldown' => 'Admin approval plus cooldown',
                                ])
                                ->default('manual_review_with_cooldown')
                                ->required()
                                ->helperText('Controls whether requests require admin review, a cooling-off period, or both.'),

                            TextInput::make('account_deletion_cooldown_days')
                                ->label('Cooldown period')
                                ->numeric()
                                ->minValue(0)
                                ->default(14)
                                ->suffix('days')
                                ->required()
                                ->helperText('Used by workflows that include a cooling-off period.'),

                            Toggle::make('account_deletion_allow_user_cancel')
                                ->label('Allow users to cancel')
                                ->helperText('Lets users cancel an active request before it is completed.'),

                            Toggle::make('account_deletion_enforce_wallet_blocker')
                                ->label('Block wallet/payment risk')
                                ->helperText('Prevents deletion while the user has wallet balance, pending withdrawals, deposit requests, or pending transactions.'),

                            Toggle::make('account_deletion_enforce_subscription_blocker')
                                ->label('Block subscription risk')
                                ->helperText('Prevents deletion while the user has active or pending subscriptions as subscriber or creator.'),

                            Toggle::make('account_deletion_enforce_moderation_blocker')
                                ->label('Block moderation risk')
                                ->helperText('Prevents deletion while unresolved reports exist against the user or their content.'),
                        ]),

                    $this->publicationReviewSettingsTab(),
                    $this->mediaModerationSettingsTab(),

                ]),
        ]);
    }

    protected function publicationReviewSettingsTab(): Tab
    {
        return Tab::make(__('Publication review'))
            ->id(self::PUBLICATION_REVIEW_TAB_ID)
            ->columns(2)
            ->schema([
                Toggle::make('moderation.publication_review_enabled')
                    ->label(__('Enable publication review'))
                    ->helperText(__('Hold new or edited content until it is approved. Existing pending content remains held when this is disabled.'))
                    ->default(false)
                    ->live()
                    ->columnSpanFull(),
                CheckboxList::make('moderation.publication_review_areas')
                    ->label(__('Content areas'))
                    ->options(['posts' => __('Posts'), 'reels' => __('Reels'), 'stories' => __('Stories')])
                    ->columns(3)
                    ->live()
                    ->required(fn (Get $get) => (bool) $get('moderation.publication_review_enabled'))
                    ->visible(fn (Get $get) => (bool) $get('moderation.publication_review_enabled')),
                Select::make('moderation.publication_review_mode')
                    ->label(__('Review mode'))
                    ->options([
                        'manual' => __('Manual review'),
                        'assisted' => __('AI-assisted review'),
                        'automatic' => __('Automatic screening'),
                    ])
                    ->default('manual')
                    ->live()
                    ->required()
                    ->helperText(fn (Get $get) => match ($get('moderation.publication_review_mode')) {
                        'assisted' => __('Uses AI scanning settings and shared budgets. An admin decides.'),
                        'automatic' => __('Uses AI scanning settings and shared budgets. All checks must pass.'),
                        default => __('Admin approval only. No AI provider needed.'),
                    })
                    ->visible(fn (Get $get) => (bool) $get('moderation.publication_review_enabled')),
                TextInput::make('admin_approved_posts_limit')
                    ->numeric()
                    ->label(__('Initial posts requiring approval'))
                    ->disabled(fn (Get $get) => (bool) $get('moderation.publication_review_enabled')
                        && in_array('posts', (array) $get('moderation.publication_review_areas'), true))
                    ->helperText(fn (Get $get) => (bool) $get('moderation.publication_review_enabled')
                        && in_array('posts', (array) $get('moderation.publication_review_areas'), true)
                            ? __('Publication review covers every post. Your initial-post limit is preserved.')
                            : __('Require approval until a creator has this many approved posts. Set to 0 to disable.'))
                    ->columnSpanFull(),
            ]);
    }

    protected function mediaModerationSettingsTab(): Tab
    {
        return Tab::make(__('AI scanning'))
            ->id(self::MEDIA_MODERATION_TAB_ID)
            ->schema([
                Group::make([
                    Tabs::make('Moderation')
                        ->persistTabInQueryString('moderation')
                        ->contained(false)
                        ->scrollable()
                        ->columnSpanFull()
                        ->tabs([
                            Tab::make('General')
                                ->columns(2)
                                ->schema([
                                    Toggle::make('enabled')
                                        ->label(__('Enable AI scanning'))
                                        ->helperText(__('Required for AI-assisted review, automatic screening, and admin-started scans. Manual approval works without it.'))
                                        ->columnSpanFull(),

                                    Select::make('provider')
                                        ->label('Provider')
                                        ->options(['sightengine' => 'Sightengine'])
                                        ->default('sightengine')
                                        ->required(),

                                    Select::make('sightengine_model')
                                        ->label('Moderation model')
                                        ->options(['nudity-2.1' => 'Nudity detection 2.1'])
                                        ->default('nudity-2.1')
                                        ->required(),

                                    TextInput::make('sightengine_api_user')
                                        ->label('Sightengine API user')
                                        ->password()
                                        ->revealable()
                                        ->autocomplete('new-password'),

                                    TextInput::make('sightengine_api_secret')
                                        ->label('Sightengine API secret')
                                        ->password()
                                        ->revealable()
                                        ->autocomplete('new-password'),

                                ]),

                            Tab::make('Media policy')
                                ->columns(2)
                                ->schema([
                                    CheckboxList::make('flag_classes')
                                        ->label('Signals to review')
                                        ->options([
                                            'sexual_activity' => 'Explicit activity',
                                            'sexual_display' => 'Explicit display',
                                            'erotica' => 'Erotic imagery',
                                        ])
                                        ->columns(3)
                                        ->required()
                                        ->columnSpanFull(),

                                    TextInput::make('review_threshold')
                                        ->label('Send to review at')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(1)
                                        ->step(0.01)
                                        ->required()
                                        ->helperText('Files at or above this confidence score are marked for review.'),

                                    TextInput::make('violation_threshold')
                                        ->label('Mark likely violation at')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(1)
                                        ->step(0.01)
                                        ->required()
                                        ->helperText('Must be equal to or higher than the review threshold.'),

                                    TextInput::make('request_timeout_seconds')
                                        ->label('Request timeout')
                                        ->numeric()
                                        ->minValue(15)
                                        ->maxValue(210)
                                        ->suffix('seconds')
                                        ->required(),
                                ]),

                            Tab::make('Text')
                                ->columns(2)
                                ->schema([
                                    Toggle::make('text_enabled')
                                        ->label(__('Enable text scanning'))
                                        ->helperText(__('Used by admin-started scans and AI publication review. Unchecked text requires manual approval.'))
                                        ->columnSpanFull(),

                                    CheckboxList::make('text_models')
                                        ->label('Text models')
                                        ->options([
                                            'general' => 'General moderation',
                                            'self-harm' => 'Self-harm detection',
                                        ])
                                        ->default(['general'])
                                        ->columns(2)
                                        ->required()
                                        ->columnSpanFull(),

                                    CheckboxList::make('text_languages')
                                        ->label('Languages')
                                        ->options([
                                            'en' => 'English',
                                            'fr' => 'French',
                                            'it' => 'Italian',
                                            'pt' => 'Portuguese',
                                            'es' => 'Spanish',
                                            'ru' => 'Russian',
                                            'tr' => 'Turkish',
                                        ])
                                        ->default(['en'])
                                        ->columns(3)
                                        ->required()
                                        ->columnSpanFull(),

                                    CheckboxList::make('text_flag_classes')
                                        ->label('Signals to review')
                                        ->helperText('Self-harm results require the separate self-harm model, which adds provider operations.')
                                        ->options([
                                            'sexual' => 'Sexual content',
                                            'discriminatory' => 'Discriminatory content',
                                            'insulting' => 'Insulting content',
                                            'violent' => 'Violent content',
                                            'toxic' => 'Toxic content',
                                            'self-harm' => 'Self-harm',
                                        ])
                                        ->default(['sexual', 'discriminatory', 'insulting', 'violent', 'toxic'])
                                        ->columns(3)
                                        ->required()
                                        ->columnSpanFull(),

                                    TextInput::make('text_review_threshold')
                                        ->label('Send to review at')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(1)
                                        ->step(0.01)
                                        ->required(),

                                    TextInput::make('text_violation_threshold')
                                        ->label('Mark likely violation at')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(1)
                                        ->step(0.01)
                                        ->required(),
                                ]),

                            Tab::make('Video')
                                ->columns(2)
                                ->schema([
                                    Toggle::make('video_enabled')
                                        ->label(__('Enable video scanning'))
                                        ->helperText('Video checks use multiple operations. Leave disabled while testing images if you want to preserve trial quota.')
                                        ->columnSpanFull(),

                                    Select::make('video_processing_mode')
                                        ->label('Video processing')
                                        ->options([
                                            ModerationSettings::VIDEO_PROCESSING_AUTO => 'Automatic (recommended)',
                                            ModerationSettings::VIDEO_PROCESSING_SYNC => 'Synchronous only',
                                            ModerationSettings::VIDEO_PROCESSING_ASYNC => 'Asynchronous only',
                                        ])
                                        ->default(ModerationSettings::VIDEO_PROCESSING_AUTO)
                                        ->helperText('Automatic returns results directly for videos under 60 seconds and uses signed callbacks for longer videos. Async requires this site to be publicly reachable over HTTPS.')
                                        ->live()
                                        ->required(),

                                    Select::make('video_interval_seconds')
                                        ->label('Sampling interval')
                                        ->options([
                                            '0' => 'Provider default (~2 seconds) — recommended',
                                            '0.5' => 'Every 0.5 seconds — Starter / Pro',
                                            '1' => 'Every second — Starter / Pro',
                                            '2' => 'Every 2 seconds — Starter / Pro',
                                            '3' => 'Every 3 seconds — Enterprise',
                                            '4' => 'Every 4 seconds — Enterprise',
                                            '5' => 'Every 5 seconds — Enterprise',
                                        ])
                                        ->default('0')
                                        ->helperText('Sightengine Starter and Pro currently support intervals from 0.5 to 2 seconds. Slower custom intervals require an appropriate provider plan.')
                                        ->required(),

                                    TextInput::make('max_concurrent_video_jobs')
                                        ->label('Maximum concurrent video jobs')
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(50)
                                        ->default(1)
                                        ->helperText(fn (Get $get) => $get('video_processing_mode') === ModerationSettings::VIDEO_PROCESSING_SYNC
                                            ? 'Not used by synchronous video processing.'
                                            : 'Sightengine Starter supports 1 simultaneous video job; Pro supports 5. Additional videos wait locally and continue automatically.')
                                        ->required(),

                                    TextInput::make('max_video_duration_seconds')
                                        ->label('Maximum video duration')
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(fn (Get $get) => $get('video_processing_mode') === ModerationSettings::VIDEO_PROCESSING_SYNC ? 59 : 3600)
                                        ->suffix('seconds')
                                        ->helperText(fn (Get $get) => $get('video_processing_mode') === ModerationSettings::VIDEO_PROCESSING_SYNC
                                            ? 'Synchronous processing supports videos under 60 seconds.'
                                            : 'The budget-friendly default is 59 seconds. This mode can support longer videos up to 60 minutes.')
                                        ->required(),
                                ]),

                            Tab::make('Budgets')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('daily_operation_limit')
                                        ->label('Daily operation budget')
                                        ->numeric()
                                        ->minValue(1)
                                        ->required()
                                        ->helperText('The default leaves a 10% safety margin below the provider trial limit.'),

                                    TextInput::make('monthly_operation_limit')
                                        ->label('Monthly operation budget')
                                        ->numeric()
                                        ->minValue(1)
                                        ->required()
                                        ->helperText('The default leaves a 10% safety margin below the monthly trial allowance.'),

                                    TextInput::make('inline_item_limit')
                                        ->label('Inline scan item limit')
                                        ->numeric()
                                        ->minValue(1)
                                        ->required(),

                                    TextInput::make('deferred_item_limit')
                                        ->label('Deferred scan item limit')
                                        ->numeric()
                                        ->minValue(1)
                                        ->required(),

                                    TextInput::make('background_item_limit')
                                        ->label('Spawned background item limit')
                                        ->numeric()
                                        ->minValue(1)
                                        ->required(),

                                    TextInput::make('worker_item_limit')
                                        ->label('Queue worker item limit')
                                        ->numeric()
                                        ->minValue(1)
                                        ->required(),

                                    TextInput::make('stale_after_minutes')
                                        ->label('Interrupted scan timeout')
                                        ->numeric()
                                        ->minValue(5)
                                        ->suffix('minutes')
                                        ->required()
                                        ->helperText('A processing item older than this can be recovered and retried.'),

                                    TextInput::make('async_provider_timeout_minutes')
                                        ->label('Provider result timeout')
                                        ->numeric()
                                        ->minValue(30)
                                        ->maxValue(10080)
                                        ->suffix('minutes')
                                        ->required()
                                        ->helperText('An asynchronous video still waiting after this period is marked retryable and its reserved budget is settled conservatively.'),
                                ]),
                        ]),
                ])
                    ->statePath('moderation')
                    ->columnSpanFull(),
            ]);
    }

    public static function getMediaModerationUrl(): string
    {
        return static::getUrl(['tab' => self::MEDIA_MODERATION_TAB_ID]);
    }

    protected function ensureAgeGateSettingsExist(): void
    {
        $table = config('settings.repositories.database.table', 'settings');

        if (!SchemaFacade::hasTable($table)) {
            return;
        }

        $now = now();

        foreach ($this->ageGateDefaults() as $name => $default) {
            $exists = DB::table($table)
                ->where('group', 'compliance')
                ->where('name', $name)
                ->exists();

            if ($exists) {
                if ($name === 'age_gate_ageverif_challenges') {
                    $this->normalizeSavedAgeGateChallenges($table);
                }

                continue;
            }

            DB::table($table)->insert([
                'group' => 'compliance',
                'name' => $name,
                'payload' => json_encode($default),
                'locked' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    protected function normalizeSavedAgeGateChallenges(string $table): void
    {
        $setting = DB::table($table)
            ->where('group', 'compliance')
            ->where('name', 'age_gate_ageverif_challenges')
            ->first(['payload']);

        if (!$setting) {
            return;
        }

        $payload = json_decode((string) $setting->payload, true);

        if (is_array($payload)) {
            return;
        }

        $normalized = is_string($payload) && $payload !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $payload))))
            : [];

        DB::table($table)
            ->where('group', 'compliance')
            ->where('name', 'age_gate_ageverif_challenges')
            ->update([
                'payload' => json_encode($normalized),
                'updated_at' => now(),
            ]);
    }

    protected function countryDetectionDebugHtml(callable $get): string
    {
        $countryDetector = app(CountryDetector::class);
        $detectedCountry = $countryDetector->detect(request());
        $selectedCountries = array_values(array_filter(array_map(
            fn ($country) => strtoupper(trim((string) $country)),
            is_array($get('age_gate_countries')) ? $get('age_gate_countries') : []
        )));
        $requiresVerification = $this->debugRequiresVerification(
            $detectedCountry,
            (string) $get('age_gate_country_detection_driver'),
            (string) $get('age_gate_countries_mode'),
            $selectedCountries,
            (bool) $get('age_gate_require_unknown_country')
        );

        $rows = [
            'Request IP' => request()->ip() ?: 'unknown',
            'X-Forwarded-For' => request()->header('X-Forwarded-For') ?: 'none',
            'CF-IPCountry' => request()->header('CF-IPCountry') ?: 'none',
            'Detection driver' => $get('age_gate_country_detection_driver') ?: 'none',
            'Detected country' => $detectedCountry ?: 'unknown',
            'Selected countries' => $selectedCountries ? implode(', ', $selectedCountries) : 'none',
            'Require if unknown' => (bool) $get('age_gate_require_unknown_country') ? 'yes' : 'no',
            'Current decision' => $requiresVerification ? 'verification required' : 'verification skipped',
        ];

        $items = collect($rows)
            ->map(fn ($value, $label) => $this->debugRow($label, (string) $value))
            ->implode('');

        $abstractDebug = $countryDetector->debugAbstract(request());

        if ($abstractDebug !== []) {
            $abstractItems = collect($abstractDebug)
                ->map(fn ($value, $label) => $this->debugRow('Abstract '.$label, (string) $value))
                ->implode('');

            $items .= '<div class="mt-1">'
                .'<p class="font-semibold mb-1">AbstractAPI response preview</p>'
                .$abstractItems
                .'</div>';
        }

        return '<div class="alert-info alert admin-debug-box">'
            .'<p class="font-semibold mb-1">Country detection debug</p>'
            .'<p class="mb-1">Debug only. Values use saved detector credentials and the current admin request.</p>'
            .'<div>'.$items.'</div>'
            .'</div>';
    }

    protected function debugRow(string $label, string $value): string
    {
        $isLongValue = strlen($value) > 80;

        if ($isLongValue) {
            return '<div class="admin-debug-row admin-debug-row-block mb-1">'
                .'<span class="font-semibold">'.e($label).'</span>'
                .'<code>'.e($value).'</code>'
                .'</div>';
        }

        return '<div class="admin-debug-row d-flex justify-between v-align-center mb-1">'
            .'<span class="font-semibold">'.e($label).'</span>'
            .'<code>'.e($value).'</code>'
            .'</div>';
    }

    protected function debugRequiresVerification(?string $country, string $detectionDriver, string $countriesMode, array $selectedCountries, bool $requireUnknown): bool
    {
        if ($detectionDriver === 'none') {
            return true;
        }

        if ($countriesMode === 'everyone') {
            return true;
        }

        if (!$country) {
            return $requireUnknown;
        }

        return in_array($country, $selectedCountries, true);
    }

    protected function ageGateDefaults(): array
    {
        return [
            'age_gate_enabled' => false,
            'age_gate_driver' => 'none',
            'age_gate_mode' => 'checker',
            'age_gate_ageverif_public_key' => null,
            'age_gate_ageverif_oauth_client_id' => null,
            'age_gate_ageverif_oauth_client_secret' => null,
            'age_gate_ageverif_challenges' => [],
            'age_gate_country_detection_driver' => 'none',
            'age_gate_abstract_api_key' => null,
            'age_gate_countries_mode' => 'everyone',
            'age_gate_countries' => [],
            'age_gate_require_unknown_country' => true,
            'age_gate_minimum_age' => 18,
            'age_gate_cookie_lifetime_days' => 90,
        ];
    }

    protected function ensureAccountDeletionSettingsExist(): void
    {
        $table = config('settings.repositories.database.table', 'settings');

        if (!SchemaFacade::hasTable($table)) {
            return;
        }

        $now = now();

        foreach ($this->accountDeletionDefaults() as $name => $default) {
            $exists = DB::table($table)
                ->where('group', 'compliance')
                ->where('name', $name)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table($table)->insert([
                'group' => 'compliance',
                'name' => $name,
                'payload' => json_encode($default),
                'locked' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    protected function accountDeletionDefaults(): array
    {
        return [
            'account_deletion_enabled' => false,
            'account_deletion_mode' => 'manual_review_with_cooldown',
            'account_deletion_cooldown_days' => 14,
            'account_deletion_allow_user_cancel' => true,
            'account_deletion_enforce_wallet_blocker' => true,
            'account_deletion_enforce_subscription_blocker' => true,
            'account_deletion_enforce_moderation_blocker' => true,
        ];
    }
}
