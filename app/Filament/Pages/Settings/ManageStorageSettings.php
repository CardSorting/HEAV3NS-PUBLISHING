<?php

namespace App\Filament\Pages\Settings;

use App\Providers\SettingsServiceProvider;
use App\Settings\StorageSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use BackedEnum;

class ManageStorageSettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cloud';

    protected static ?string $slug = 'settings/storage';

    protected static string $settings = StorageSettings::class;

    protected static ?string $title = 'Storage Settings';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('driver')
                        ->label('Storage driver')
                        ->options([
                            'public' => 'Public (Local)',
                            's3' => 'Amazon S3',
                            'do_spaces' => 'DigitalOcean Spaces',
                            'wasabi' => 'Wasabi',
                            'minio' => 'MinIO',
                            'pushr' => 'Pushr',
                            'r2' => 'Cloudflare R2',
                            'b2' => 'Backblaze B2',
                        ])
                        ->required()
                        ->reactive()
                        ->placeholder('Select a driver')
                        ->helperText('Select which storage driver to use for the user assets.')
                        ->columnSpanFull(),

                    // === S3 ===
                    TextInput::make('aws_access_key')->label('Access key')->visible(fn ($get) => $get('driver') === 's3')->required(),
                    TextInput::make('aws_secret_key')
                        ->label('Secret key')
                        ->visible(fn ($get) => $get('driver') === 's3')
                        ->required()
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password'),

                    Select::make('aws_region')
                        ->label('Region')
                        ->options([
                            'us-east-1'      => 'us-east-1 (N. Virginia)',
                            'us-east-2'      => 'us-east-2 (Ohio)',
                            'us-west-1'      => 'us-west-1 (N. California)',
                            'us-west-2'      => 'us-west-2 (Oregon)',

                            'ca-central-1'   => 'ca-central-1 (Canada Central)',

                            'eu-west-1'      => 'eu-west-1 (Ireland)',
                            'eu-west-2'      => 'eu-west-2 (London)',
                            'eu-west-3'      => 'eu-west-3 (Paris)',
                            'eu-central-1'   => 'eu-central-1 (Frankfurt)',
                            'eu-north-1'     => 'eu-north-1 (Stockholm)',
                            'eu-south-1'     => 'eu-south-1 (Milan)',
                            'eu-south-2'     => 'eu-south-2 (Spain)',

                            'ap-southeast-1' => 'ap-southeast-1 (Singapore)',
                            'ap-southeast-2' => 'ap-southeast-2 (Sydney)',
                            'ap-southeast-3' => 'ap-southeast-3 (Jakarta)',
                            'ap-northeast-1' => 'ap-northeast-1 (Tokyo)',
                            'ap-northeast-2' => 'ap-northeast-2 (Seoul)',
                            'ap-northeast-3' => 'ap-northeast-3 (Osaka)',
                            'ap-south-1'     => 'ap-south-1 (Mumbai)',
                            'ap-south-2'     => 'ap-south-2 (Hyderabad)',

                            'sa-east-1'      => 'sa-east-1 (São Paulo)',

                            'me-south-1'     => 'me-south-1 (Bahrain)',
                            'me-central-1'   => 'me-central-1 (UAE)',

                            'af-south-1'     => 'af-south-1 (Cape Town)',
                        ])
                        ->searchable()
                        ->required()
                        ->visible(fn ($get) => $get('driver') === 's3'),
                    TextInput::make('aws_bucket_name')->label('Bucket name')->visible(fn ($get) => $get('driver') === 's3')->required(),
                    Toggle::make('aws_cdn_enabled')->label('Enable CDN')->visible(fn ($get) => $get('driver') === 's3'),
                    Toggle::make('aws_cdn_presigned_urls_enabled')->label('Enable presigned URLs')->visible(fn ($get) => $get('driver') === 's3'),
                    TextInput::make('aws_cdn_key_pair_id')->label('CloudFront key pair ID')->visible(fn ($get) => $get('driver') === 's3'),
                    TextInput::make('aws_cdn_private_key_path')->label('Private key path')->visible(fn ($get) => $get('driver') === 's3'),
                    TextInput::make('cdn_domain_name')->label('CDN Domain')->visible(fn ($get) => $get('driver') === 's3')->helperText("Do not include https://")->columnSpanFull(),

                    // === Wasabi ===
                    TextInput::make('was_access_key')->label('Access key')->visible(fn ($get) => $get('driver') === 'wasabi')->required(),
                    TextInput::make('was_secret_key')
                        ->label('Secret key')
                        ->visible(fn ($get) => $get('driver') === 'wasabi')
                        ->required()
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password'),
                    Select::make('was_region')
                        ->label('Region')
                        ->options([
                            // North America
                            'us-west-1'      => 'us-west-1 (Oregon, United States)',
                            'us-east-1'      => 'us-east-1 (Virginia, United States)',
                            'us-east-2'      => 'us-east-2 (Virginia, United States)',
                            'us-central-1'   => 'us-central-1 (Plano, Texas, United States)',
                            'ca-central-1'   => 'ca-central-1 (Toronto, Canada)',

                            // Europe (EMEA)
                            'eu-west-1'      => 'eu-west-1 (United Kingdom)',
                            'eu-west-3'      => 'eu-west-3 (United Kingdom)',
                            'eu-west-2'      => 'eu-west-2 (Paris, France)',
                            'eu-central-1'   => 'eu-central-1 (Amsterdam, Netherlands)',
                            'eu-central-2'   => 'eu-central-2 (Frankfurt, Germany)',
                            'eu-south-1'     => 'eu-south-1 (Milan, Italy)',

                            // Asia Pacific (APAC)
                            'ap-northeast-1' => 'ap-northeast-1 (Tokyo, Japan)',
                            'ap-northeast-2' => 'ap-northeast-2 (Osaka, Japan)',
                            'ap-southeast-2' => 'ap-southeast-2 (Sydney, Australia)',
                            'ap-southeast-1' => 'ap-southeast-1 (Singapore)',
                        ])
                        ->searchable()
                        ->required()
                        ->visible(fn ($get) => $get('driver') === 'wasabi'),
                    TextInput::make('was_bucket_name')->label('Bucket name')->visible(fn ($get) => $get('driver') === 'wasabi')->required(),

                    // === DigitalOcean Spaces ===
                    TextInput::make('do_access_key')->label('Access key')->visible(fn ($get) => $get('driver') === 'do_spaces')->required(),
                    TextInput::make('do_secret_key')
                        ->label('Secret key')
                        ->visible(fn ($get) => $get('driver') === 'do_spaces')
                        ->required()
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password'),

                    Select::make('do_region')
                        ->label('Region')
                        ->options([
                            'nyc1' => 'nyc1 (New York City, United States)',
                            'nyc2' => 'nyc2 (New York City, United States)',
                            'nyc3' => 'nyc3 (New York City, United States)',
                            'ams3' => 'ams3 (Amsterdam, Netherlands)',
                            'sfo2' => 'sfo2 (San Francisco, United States)',
                            'sfo3' => 'sfo3 (San Francisco, United States)',
                            'sgp1' => 'sgp1 (Singapore)',
                            'lon1' => 'lon1 (London, United Kingdom)',
                            'fra1' => 'fra1 (Frankfurt, Germany)',
                            'tor1' => 'tor1 (Toronto, Canada)',
                            'blr1' => 'blr1 (Bangalore, India)',
                            'syd1' => 'syd1 (Sydney, Australia)',
                            'atl1' => 'atl1 (Atlanta, United States)',
                        ])
                        ->searchable()
                        ->required()
                        ->visible(fn ($get) => $get('driver') === 'do_spaces'),
                    TextInput::make('do_bucket_name')->label('Bucket name')->visible(fn ($get) => $get('driver') === 'do_spaces')->required(),

                    // === MinIO ===
                    TextInput::make('minio_access_key')->label('Access key')->visible(fn ($get) => $get('driver') === 'minio')->required()->autocomplete(false),
                    TextInput::make('minio_secret_key')
                        ->label('Secret key')
                        ->visible(fn ($get) => $get('driver') === 'minio')
                        ->required()
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password'),
                    TextInput::make('minio_region')->label('Region')->visible(fn ($get) => $get('driver') === 'minio')->required(),
                    TextInput::make('minio_bucket_name')->label('Bucket name')->visible(fn ($get) => $get('driver') === 'minio')->required(),
                    TextInput::make('minio_endpoint')->label('Endpoint URL')->visible(fn ($get) => $get('driver') === 'minio')->required(),

                    // === Pushr ===
                    TextInput::make('pushr_access_key')->label('Access key')->visible(fn ($get) => $get('driver') === 'pushr')->required(),
                    TextInput::make('pushr_secret_key')
                        ->label('Secret key')
                        ->visible(fn ($get) => $get('driver') === 'pushr')
                        ->required()
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password'),
                    TextInput::make('pushr_cdn_hostname')->label('CDN Hostname')->visible(fn ($get) => $get('driver') === 'pushr')->required()->helperText('This field must contain the https:// prefix.'),
                    TextInput::make('pushr_bucket_name')->label('Bucket name')->visible(fn ($get) => $get('driver') === 'pushr')->required(),
                    TextInput::make('pushr_endpoint')->label('Endpoint URL')->visible(fn ($get) => $get('driver') === 'pushr')->required(),

                    // R2
                    TextInput::make('r2_access_key')->label('Access Key')->visible(fn ($get) => $get('driver') === 'r2')->required(),
                    TextInput::make('r2_secret_key')
                        ->label('Secret Key')
                        ->visible(fn ($get) => $get('driver') === 'r2')
                        ->required()
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password'),
                    TextInput::make('r2_bucket_name')->label('Bucke name')->visible(fn ($get) => $get('driver') === 'r2')->required(),
                    Select::make('r2_region')
                        ->label('Region')
                        ->options([
                            'wnam' => 'wnam (Western North America)',
                            'enam' => 'enam (Eastern North America)',
                            'weur' => 'weur (Western Europe)',
                            'eeur' => 'eeur (Eastern Europe)',
                            'apac' => 'apac (Asia-Pacific)',
                            'oc' => 'oc (Oceania)',
                        ])
                        ->searchable()
                        ->required()
                        ->visible(fn ($get) => $get('driver') === 'r2'),
                    TextInput::make('r2_endpoint')->label('Endpoint URL')->visible(fn ($get) => $get('driver') === 'r2')->required(),
                    TextInput::make('r2_custom_url')->label('Custom URL')->visible(fn ($get) => $get('driver') === 'r2')->required(),

                    // Backblaze B2
                    TextInput::make('b2_access_key')->label('Application key ID')->visible(fn ($get) => $get('driver') === 'b2')->required(),
                    TextInput::make('b2_secret_key')
                        ->label('Application key')
                        ->visible(fn ($get) => $get('driver') === 'b2')
                        ->required()
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password'),
                    TextInput::make('b2_bucket_name')->label('Bucket name')->visible(fn ($get) => $get('driver') === 'b2')->required(),
                    TextInput::make('b2_region')
                        ->label('Region')
                        ->visible(fn ($get) => $get('driver') === 'b2')
                        ->required()
                        ->helperText('Use the region from your S3 endpoint, for example us-east-005.'),
                    TextInput::make('b2_endpoint')
                        ->label('Endpoint URL')
                        ->visible(fn ($get) => $get('driver') === 'b2')
                        ->required()
                        ->helperText('Example: https://s3.us-east-005.backblazeb2.com'),
                    TextInput::make('b2_custom_url')
                        ->label('Custom URL')
                        ->visible(fn ($get) => $get('driver') === 'b2')
                        ->helperText('Optional CDN/custom URL, including https://.'),

                ]),
        ]);
    }

    protected string $previousDriver = 'public';

    protected function beforeSave(): void
    {
        $this->previousDriver = app(StorageSettings::class)->driver;
    }

    protected function afterSave(): void
    {
        try {
            SettingsServiceProvider::setUpStorageCredentials();
            SettingsServiceProvider::setDefaultStorageDriver();
            Storage::files('/');

        } catch (\Throwable $e) {
            //  Roll back config
            config(['filesystems.default' => $this->previousDriver]);

            // Roll back DB setting
            app(StorageSettings::class)->driver = $this->previousDriver;
            app(StorageSettings::class)->save();

            // Roll back Livewire + form state
            $this->data['driver'] = $this->previousDriver;
//            $this->form->fill(['driver' => $this->previousDriver]);

            // Show field error + toast
            $this->addError('driver', 'Storage check failed: '.$e->getMessage());

            Notification::make()
                ->title('Error')
//                ->body("Storage validation failed. Driver reverted to previously used one.")
                ->body($e->getMessage())
                ->danger()
                ->send();

            return; // stop Filament's post-save success flow
        }
    }

    protected function getSavedNotificationMessage(): ?string
    {
        return null; // disable built-in toast
    }
}
