<?php

namespace App\Filament\Pages\Settings;

use App\Settings\WebsocketsSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ManageWebsocketsSettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $slug = 'settings/websockets';

    protected static string $settings = WebsocketsSettings::class;

    protected static ?string $title = 'Websockets Settings';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('driver')
                        ->label('Websockets driver')
                        ->options([
                            'pusher' => 'Pusher',
                            'soketi' => 'Soketi',
                        ])
                        ->required()
                        ->reactive()
                        ->placeholder('Select a driver')
                        ->helperText("Select which the websockets driver to use.")
                        ->columnSpanFull(),

                    // === Pusher ===
                    TextInput::make('pusher_app_id')->label('App ID')->visible(fn ($get) => $get('driver') === 'pusher')->required(),
                    TextInput::make('pusher_app_key')->label('App key')->visible(fn ($get) => $get('driver') === 'pusher')->required(),
                    TextInput::make('pusher_app_secret')
                        ->label('App secret')
                        ->visible(fn ($get) => $get('driver') === 'pusher')
                        ->required()
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password'),
                    Select::make('pusher_app_cluster')
                        ->label('App cluster')
                        ->options([
                            'mt1' => 'mt1 (N. Virginia)',
                            'us2' => 'us2 (Ohio)',
                            'us3' => 'us3 (Oregon)',
                            'eu'  => 'eu (Ireland)',
                            'ap1' => 'ap1 (Singapore)',
                            'ap2' => 'ap2 (Mumbai)',
                            'ap3' => 'ap3 (Tokyo)',
                            'ap4' => 'ap4 (Sydney)',
                            'sa1' => 'sa1 (São Paulo)',
                        ])
                        ->searchable()
                        ->required()
                        ->visible(fn ($get) => $get('driver') === 'pusher'),

                    // === Soketi ===
                    TextInput::make('soketi_host_address')->label('Host address')->visible(fn ($get) => $get('driver') === 'soketi')->required(),
                    TextInput::make('soketi_host_port')->label('Host port')->visible(fn ($get) => $get('driver') === 'soketi')->required(),
                    TextInput::make('soketi_app_id')->label('App ID')->visible(fn ($get) => $get('driver') === 'soketi')->required(),
                    TextInput::make('soketi_app_key')->label('App key')->visible(fn ($get) => $get('driver') === 'soketi')->required(),
                    TextInput::make('soketi_app_secret')
                        ->label('App secret')
                        ->visible(fn ($get) => $get('driver') === 'soketi')
                        ->required()
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password'),
                    Toggle::make('soketi_use_TSL')->label('Use TLS')->visible(fn ($get) => $get('driver') === 'soketi'),
                ]),
        ]);
    }
}
