<?php

namespace App\Filament\Pages\Settings;

use App\Settings\SecuritySettings;
use App\Settings\GeneralSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

class ManageSecuritySettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $slug = 'settings/security';

    protected static string $settings = SecuritySettings::class;

    protected static ?string $title = 'Security Settings';

    protected ?bool $hasDatabaseTransactions = true;

    protected array $pendingSiteSettings = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['site'] = ['enforce_email_validation' => (bool) getSettingFilament('site.enforce_email_validation')];

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->pendingSiteSettings = Arr::only(is_array($data['site'] ?? null) ? $data['site'] : [], ['enforce_email_validation']);
        unset($data['site']);

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->pendingSiteSettings !== []) {
            app(GeneralSettings::class)->refresh()->fill($this->pendingSiteSettings)->save();
        }
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Security Settings')
                ->persistTabInQueryString('tab')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('General')
                        ->columns(2)
                        ->schema([
                            Group::make([
                                Toggle::make('enforce_email_validation')
                                    ->label(__('Enforce email validation'))
                                    ->helperText(__('Requires users to verify their email before accessing the site.')),
                            ])->statePath('site')->columnSpanFull(),

                            Toggle::make('enable_2fa')
                                ->label('Enable 2FA')
                                ->helperText('Adds an email-based 2FA step via email when users log in.')
                                ->columnSpanFull(),

                            Toggle::make('default_2fa_on_register')
                                ->label('Enabled 2FA on register')
                                ->helperText('Automatically enable 2FA for new registrations.')
                                ->columnSpanFull(),

                            Toggle::make('allow_users_2fa_switch')
                                ->label('Allow users to disable 2FA')
                                ->helperText('Allowing users to be able to change their 2FA settings.')
                                ->columnSpanFull(),

                            Toggle::make('enforce_app_ssl')
                                ->label('Enforce SSL')
                                ->helperText('Redirect all traffic to HTTPS. Not necessary on most hosting providers.')
                                ->columnSpanFull(),

                        ]),

                    Tab::make('Captcha')
                        ->columns(2)
                        ->schema([
                            Select::make('captcha_driver')
                                ->label('Captcha driver')
                                ->options([
                                    'none' => 'None',
                                    'turnstile' => 'Cloudflare Turnstile',
                                    'hcaptcha' => 'hCaptcha',
                                    'recaptcha' => 'Google reCAPTCHA',
                                ])
                                ->default('none')
                                ->reactive()
                                ->placeholder('Select a driver')
                                ->helperText('Select which captcha system to use for authentication forms.')
                                ->columnSpanFull(),

                            // === reCAPTCHA ===
                            TextInput::make('recaptcha_site_key')
                                ->label('Site key')
                                ->visible(fn ($get) => $get('captcha_driver') === 'recaptcha'),

                            TextInput::make('recaptcha_site_secret_key')
                                ->label('Secret key')
                                ->visible(fn ($get) => $get('captcha_driver') === 'recaptcha')
                                ->password()
                                ->revealable()
                                ->autocomplete('new-password'),

                            // === Turnstile ===
                            TextInput::make('turnstile_site_key')
                                ->label('Site key')
                                ->visible(fn ($get) => $get('captcha_driver') === 'turnstile'),

                            TextInput::make('turnstile_site_secret_key')
                                ->label('Secret key')
                                ->visible(fn ($get) => $get('captcha_driver') === 'turnstile')
                                ->password()
                                ->revealable()
                                ->autocomplete('new-password'),

                            // === hCaptcha ===
                            TextInput::make('hcaptcha_site_key')
                                ->label('Site key')
                                ->visible(fn ($get) => $get('captcha_driver') === 'hcaptcha'),

                            TextInput::make('hcaptcha_site_secret_key')
                                ->label('Secret key')
                                ->visible(fn ($get) => $get('captcha_driver') === 'hcaptcha')
                                ->password()
                                ->revealable()
                                ->autocomplete('new-password'),
                        ]),

                    Tab::make('Geo-blocking')
                        ->columns(2)
                        ->schema([
                            Toggle::make('allow_geo_blocking')
                                ->label('Enable Geo-blocking')
                                ->helperText("If enabled, users will be able to disallow certain countries to access their content."),

                            TextInput::make('abstract_api_key')
                                ->label('Abstract API key')
                                ->helperText('Used to detect and block users by region (via Abstract API).')
                                ->placeholder('Your Abstract API Key')
                                ->password()
                                ->revealable()
                                ->autocomplete('new-password'),

                        ]),

                    Tab::make('Email deliverability')
                        ->columns(2)
                        ->schema([
                            Toggle::make('enforce_email_valid_check')
                                ->label('Validate emails on register')
                                ->helperText('Requires valid, deliverable email during registration.'),

                            TextInput::make('email_abstract_api_key')
                                ->label('Abstract API key')
                                ->helperText('Used for validating email addresses on signup (via Abstract API).')
                                ->placeholder('Your Abstract API Key')
                                ->password()
                                ->revealable()
                                ->autocomplete('new-password'),

                        ]),

                    Tab::make(__('admin.settings_forms.security.email_domains.tab'))
                        ->columns(2)
                        ->schema([
                            Select::make('email_domain_policy')
                                ->label(__('admin.settings_forms.security.email_domains.fields.domain_policy'))
                                ->options([
                                    'allow_all' => __('admin.settings_forms.security.email_domains.options.allow_all'),
                                    'allowlist_only' => __('admin.settings_forms.security.email_domains.options.allowlist_only'),
                                    'blocklist_only' => __('admin.settings_forms.security.email_domains.options.blocklist_only'),
                                ])
                                ->default('allow_all')
                                ->helperText(__('admin.settings_forms.security.email_domains.helpers.domain_policy'))
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state === 'allowlist_only') {
                                        $set('email_blocklist_domains', []);
                                    } elseif ($state === 'blocklist_only') {
                                        $set('email_allowedlist_domains', []);
                                    }
                                })
                                ->columnSpanFull(),

                            TagsInput::make('email_allowedlist_domains')
                                ->label(__('admin.settings_forms.security.email_domains.fields.allowedlist_domains'))
                                ->helperText(__('admin.settings_forms.security.email_domains.helpers.allowedlist_domains'))
                                ->placeholder(__('admin.settings_forms.security.email_domains.placeholders.domains'))
                                ->separator(',')
                                ->suggestions([])
                                ->visible(fn ($get) => $get('email_domain_policy') === 'allowlist_only')
                                ->dehydrated(fn ($get) => $get('email_domain_policy') === 'allowlist_only')
                                ->dehydrateStateUsing(fn ($state) => is_array($state) ? array_values(array_filter($state)) : [])
                                ->rule('array')
                                ->nestedRecursiveRules([
                                    'regex:/^(\*\.)?([a-z0-9-]+\.)+[a-z]{2,}$/i',
                                ])
                                ->columnSpanFull(),

                            TagsInput::make('email_blocklist_domains')
                                ->label(__('admin.settings_forms.security.email_domains.fields.blocklist_domains'))
                                ->helperText(__('admin.settings_forms.security.email_domains.helpers.blocklist_domains'))
                                ->placeholder(__('admin.settings_forms.security.email_domains.placeholders.domains'))
                                ->separator(',')
                                ->suggestions([])
                                ->visible(fn ($get) => $get('email_domain_policy') === 'blocklist_only')
                                ->dehydrated(fn ($get) => $get('email_domain_policy') === 'blocklist_only')
                                ->dehydrateStateUsing(fn ($state) => is_array($state) ? array_values(array_filter($state)) : [])
                                ->rule('array')
                                ->nestedRecursiveRules([
                                    'regex:/^(\*\.)?([a-z0-9-]+\.)+[a-z]{2,}$/i',
                                ])
                                ->columnSpanFull(),
                        ]),

                    Tab::make('Hyperlinks')
                        ->columns(2)
                        ->schema([
                            Select::make('domain_policy')
                                ->label('Domain policy')
                                ->options([
                                    'allow_all' => 'Allow all domains',
                                    'allowlist_only' => 'Allow only allowlisted domains',
                                    'blocklist_only' => 'Block only blocklisted domains',
                                ])
                                ->default('allow_all')
                                ->helperText('Controls how outbound hyperlinks are handled across the app.')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    // Optional: keep data clean when switching
                                    if ($state === 'allowlist_only') {
                                        $set('blocklist_domains', []);
                                    } elseif ($state === 'blocklist_only') {
                                        $set('allowedlist_domains', []);
                                    }
                                })
                                ->columnSpanFull(),

                            TagsInput::make('allowedlist_domains')
                                ->label('Allowedlist domains')
                                ->helperText('Used when policy is “Allow only allowlisted domains”. Enter domains like: example.com (no scheme).')
                                ->placeholder('Add a domain and press Enter')
                                ->separator(',')
                                ->suggestions([])
                                ->visible(fn ($get) => $get('domain_policy') === 'allowlist_only')
                                ->dehydrated(fn ($get) => $get('domain_policy') === 'allowlist_only')
                                ->dehydrateStateUsing(fn ($state) => is_array($state) ? array_values(array_filter($state)) : [])
                                ->rule('array')
                                ->nestedRecursiveRules([
                                    'regex:/^(\*\.)?([a-z0-9-]+\.)+[a-z]{2,}$/i',
                                ])
                                ->columnSpanFull(),

                            TagsInput::make('blocklist_domains')
                                ->label('Blocklist domains')
                                ->helperText('Used when policy is “Block only blocklisted domains”. Enter domains like: bad.com (no scheme).')
                                ->placeholder('Add a domain and press Enter')
                                ->separator(',')
                                ->suggestions([])
                                ->visible(fn ($get) => $get('domain_policy') === 'blocklist_only')
                                ->dehydrated(fn ($get) => $get('domain_policy') === 'blocklist_only')
                                ->dehydrateStateUsing(fn ($state) => is_array($state) ? array_values(array_filter($state)) : [])
                                ->rule('array')
                                ->nestedRecursiveRules([
                                    'regex:/^(\*\.)?([a-z0-9-]+\.)+[a-z]{2,}$/i',
                                ])
                                ->columnSpanFull(),
                        ]),

                    Tab::make(__('admin.settings_forms.security.rate_limits.tab'))
                        ->columns(2)
                        ->schema([
                            Toggle::make('enable_feature_rate_limits')
                                ->label(__('admin.settings_forms.security.rate_limits.fields.enable_feature_rate_limits'))
                                ->helperText(__('admin.settings_forms.security.rate_limits.helpers.enable_feature_rate_limits'))
                                ->columnSpanFull(),

                            $this->makeRateLimitSection(
                                'admin.settings_forms.security.rate_limits.features.posts_save.title',
                                'rate_limit_posts_save',
                                'admin.settings_forms.security.rate_limits.features.posts_save.description'
                            ),

                            $this->makeRateLimitSection(
                                'admin.settings_forms.security.rate_limits.features.posts_comments_add.title',
                                'rate_limit_posts_comments_add',
                                'admin.settings_forms.security.rate_limits.features.posts_comments_add.description'
                            ),

                            $this->makeRateLimitSection(
                                'admin.settings_forms.security.rate_limits.features.stories_store.title',
                                'rate_limit_stories_store',
                                'admin.settings_forms.security.rate_limits.features.stories_store.description'
                            ),

                            $this->makeRateLimitSection(
                                'admin.settings_forms.security.rate_limits.features.reels_store.title',
                                'rate_limit_reels_store',
                                'admin.settings_forms.security.rate_limits.features.reels_store.description'
                            ),

                            $this->makeRateLimitSection(
                                'admin.settings_forms.security.rate_limits.features.reels_comments_add.title',
                                'rate_limit_reels_comments_add',
                                'admin.settings_forms.security.rate_limits.features.reels_comments_add.description'
                            ),

                            $this->makeRateLimitSection(
                                'admin.settings_forms.security.rate_limits.features.streams_init.title',
                                'rate_limit_streams_init',
                                'admin.settings_forms.security.rate_limits.features.streams_init.description'
                            ),

                            $this->makeRateLimitSection(
                                'admin.settings_forms.security.rate_limits.features.stream_comments_add.title',
                                'rate_limit_stream_comments_add',
                                'admin.settings_forms.security.rate_limits.features.stream_comments_add.description'
                            ),

                            $this->makeRateLimitSection(
                                'admin.settings_forms.security.rate_limits.features.suggestions_generate.title',
                                'rate_limit_suggestions_generate',
                                'admin.settings_forms.security.rate_limits.features.suggestions_generate.description'
                            ),

                            $this->makeRateLimitSection(
                                'admin.settings_forms.security.rate_limits.features.profile_asset_generate.title',
                                'rate_limit_profile_asset_generate',
                                'admin.settings_forms.security.rate_limits.features.profile_asset_generate.description'
                            ),

                            $this->makeRateLimitSection(
                                'admin.settings_forms.security.rate_limits.features.messenger_send.title',
                                'rate_limit_messenger_send',
                                'admin.settings_forms.security.rate_limits.features.messenger_send.description'
                            ),
                        ]),

                ]),
        ]);
    }

    protected function makeRateLimitSection(string $title, string $settingPrefix, string $description): Section
    {
        return Section::make(__($title))
            ->description(__($description))
            ->columnSpanFull()
            ->columns(2)
            ->schema([
                Toggle::make("{$settingPrefix}_enabled")
                    ->label(__('admin.settings_forms.security.rate_limits.fields.enabled'))
                    ->helperText(__('admin.settings_forms.security.rate_limits.helpers.enabled'))
                    ->columnSpanFull(),

                TextInput::make("{$settingPrefix}_max_attempts")
                    ->label(__('admin.settings_forms.security.rate_limits.fields.max_attempts'))
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->helperText(__('admin.settings_forms.security.rate_limits.helpers.max_attempts')),

                TextInput::make("{$settingPrefix}_decay_seconds")
                    ->label(__('admin.settings_forms.security.rate_limits.fields.window'))
                    ->numeric()
                    ->minValue(1)
                    ->required()
                    ->suffix(__('admin.settings_forms.security.rate_limits.units.seconds'))
                    ->helperText(__('admin.settings_forms.security.rate_limits.helpers.window')),
            ]);
    }
}
