<?php

namespace App\Filament\Pages\Settings;

use App\Settings\LicenseSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use BackedEnum;

class ManageLicenseSettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $slug = 'settings/license';

    protected static ?string $title = 'License Settings';

    protected static string $settings = LicenseSettings::class;

    public function beforeSave(): void
    {
        $licenseCode = $this->data['product_license_key'] ?? null;

        if (!$licenseCode) {
            return; // Let Filament handle required field
        }

        $license = \App\Providers\InstallerServiceProvider::gld($licenseCode);

        if (isset($license->error)) {
            throw ValidationException::withMessages([
                'data.product_license_key' => $license->error,
            ]);
        }

        // Valid license — save to installed file
        Storage::disk('local')->put('installed', json_encode(array_merge((array) $license, ['code' => $licenseCode])));

        Notification::make()
            ->title('License verified successfully.')
            ->success()
            ->send();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('License code setup')
                ->columnSpanFull()
                ->description('Here you can activate the product, according to your license.')
                ->columns(2)
                ->schema([

                    Placeholder::make('license_box')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->content(fn () => new HtmlString(view('filament.partials.license')->render())),

                    TextInput::make('product_license_key')
                        ->label('Product license code')
                        ->helperText('Your product license key. Can be taken out of your Codecanyon downloads page. ')
                        ->required()
                        ->columnSpanFull(),

                ]),
        ]);
    }
}
