<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Traits\HasShieldPageAccess;
use App\Providers\AttachmentServiceProvider;
use App\Settings\AdminSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use BackedEnum;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ManageAdminSettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $slug = 'settings/admin';

    protected static string $settings = AdminSettings::class;

    protected static ?string $title = 'Admin Settings';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Admin Settings')
                ->columnSpanFull()
                ->persistTabInQueryString('tab')
                ->tabs([
                    Tabs\Tab::make('Appearance')
                        ->columns(2)
                        ->schema([
                            TextInput::make('title')->label('Admin title')->columnSpanFull(),

                            FileUpload::make('light_logo')
                                ->label('Light logo')
                                ->directory('assets')
                                ->multiple(false)
                                ->visibility(AttachmentServiceProvider::getAdminFileUploadVisibility())
                                ->image()
                                ->imagePreviewHeight('80px')
                                ->maxSize(AttachmentServiceProvider::getUploadMaxFilesize())
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml']),

                            FileUpload::make('dark_logo')
                                ->label('Dark logo')
                                ->directory('assets')
                                ->multiple(false)
                                ->visibility(AttachmentServiceProvider::getAdminFileUploadVisibility())
                                ->image()
                                ->imagePreviewHeight('80px')
                                ->maxSize(AttachmentServiceProvider::getUploadMaxFilesize())
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml']),
                        ]),

                    Tabs\Tab::make('Public identity')
                        ->schema([
                            Toggle::make('show_staff_badges')
                                ->label('Show admin badges')
                                ->helperText('Show an icon beside administrator names in the app.'),
                        ]),

                    Tabs\Tab::make('Notifications')
                        ->columns(2)
                        ->schema([
                            Toggle::make('send_notifications_on_contact')
                                ->label('Notify on contact messages')
                                ->columnSpanFull()
                                ->helperText('If enabled, the admin users will receive an email with the contents of the contact message.'),

                            Toggle::make('send_notifications_on_pending_posts')
                                ->label('Notify on pending post approvals')
                                ->columnSpanFull()
                                ->helperText('If enabled, the admin users will receive an email whenever a post is pending approval.'),

                        ]),
                ]),
        ]);
    }
}
