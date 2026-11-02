<?php

namespace App\Filament\Pages\Settings;

use App\Providers\EmailsServiceProvider;
use App\Providers\SettingsServiceProvider;
use App\Settings\EmailsSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Forms\Form;
use BackedEnum;

class ManageEmailsSettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-envelope';

    protected static ?string $slug = 'settings/emails';

    protected static string $settings = EmailsSettings::class;

    protected static ?string $title = 'Emails Settings';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
//                    ::make('Email Configuration')
                ->columns(2)
                ->schema([
                    Select::make('driver')
                        ->label('Email driver')
                        ->options([
                            'log' => 'Log',
                            'sendmail' => 'Sendmail',
                            'smtp' => 'SMTP',
                            'mailgun' => 'Mailgun',
                        ])
                        ->required()
                        ->reactive()
                        ->helperText('Select which email driver to use for transactional emails.')
                        ->columnSpanFull(),

                    TextInput::make('from_name')->label('From name')->required(),
                    TextInput::make('from_address')->label('From address')->required(),

                    // === Mailgun ===
                    TextInput::make('mailgun_domain')->label('Domain')
                        ->helperText("Do not include https://")
                        ->visible(fn ($get) => $get('driver') === 'mailgun')
                        ->required(),
                    TextInput::make('mailgun_secret')
                        ->label('Secret')
                        ->visible(fn ($get) => $get('driver') === 'mailgun')
                        ->required()
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password'),
                    Select::make('mailgun_endpoint')
                        ->label('Endpoint')
                        ->options([
                            'api.mailgun.net'     => 'US: api.mailgun.net',
                            'api.eu.mailgun.net' => 'EU:  api.eu.mailgun.net',
                        ])
                        ->required()
                        ->visible(fn ($get) => $get('driver') === 'mailgun')
                        ->columnSpanFull(),

                    // === SMTP ===
                    TextInput::make('smtp_host')->label('Host')->visible(fn ($get) => $get('driver') === 'smtp')->required(),
                    TextInput::make('smtp_port')->label('Port')->visible(fn ($get) => $get('driver') === 'smtp')->required(),
                    Select::make('smtp_encryption')
                        ->label('Encryption')
                        ->options([
                            'tls' => 'TLS',
                            'ssl' => 'SSL',
                        ])
                        ->visible(fn ($get) => $get('driver') === 'smtp')
                        ->default('tls')
                        ->columnSpanFull()->required(),

                    TextInput::make('smtp_username')->label('Username')->visible(fn ($get) => $get('driver') === 'smtp')->required(),
                    TextInput::make('smtp_password')
                        ->label('Password')
                        ->visible(fn ($get) => $get('driver') === 'smtp')
                        ->required()
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password'),
                ]),
        ]);
    }

    protected string $previousDriver = 'log';

    protected function beforeSave(): void
    {
        $this->previousDriver = app(EmailsSettings::class)->driver;
    }

    protected function afterSave(): void
    {
        try {
//            var_dump(getSetting('emails.driver'));die();
            SettingsServiceProvider::setUpEmailCredentials();

            EmailsServiceProvider::sendGenericEmail([
                'email' => 'smtp-test-'.rand(1000, 9999).'@mailinator.com',
                'subject' => 'SMTP Test',
                'title' => 'SMTP Test',
                'content' => 'Testing SMTP',
                'button' => [
                    'text' => 'Docs',
                    'url' => 'https://example.com',
                ],
            ]);

//            Notification::make()
//                ->title('Email driver updated and tested successfully.')
//                ->success()
//                ->send();

        } catch (\Throwable $e) {
            // Revert config and settings
            config(['mail.default' => $this->previousDriver]);

            app(EmailsSettings::class)->driver = $this->previousDriver;
            app(EmailsSettings::class)->save();

            $this->data['driver'] = $this->previousDriver;

            $this->addError('driver', 'Email test failed: '.$e->getMessage());

            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }
    }

    protected function getSavedNotificationMessage(): ?string
    {
        return null; // Prevent duplicate toast from Filament
    }
}
