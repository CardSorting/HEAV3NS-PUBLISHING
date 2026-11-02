<?php

namespace App\Filament\Pages\Settings;

use App\Settings\StreamsSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use BackedEnum;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ManageStreamsSettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-video-camera';

    protected static ?string $slug = 'settings/streams';

    protected static string $settings = StreamsSettings::class;

    protected static ?string $title = 'Streams Settings';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    Select::make('streaming_driver')
                        ->label('Streaming driver')
                        ->options([
                            'none' => 'None',
                            'pushr' => 'Pushr',
                            'livekit' => 'LiveKit',
                        ])
                        ->default('none')
                        ->required()
                        ->reactive()
                        ->placeholder('Select a driver')
                        ->helperText("Select which streaming driver to use.")
                        ->columnSpanFull(),

                    // === Pushr ===
                    TextInput::make('pushr_key')->label('API key')
                        ->visible(fn ($get) => $get('streaming_driver') === 'pushr')
                        ->helperText("The PushrCDN API Key.")->required()
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password'),
                    TextInput::make('pushr_zone_id')->label('Zone ID')
                        ->visible(fn ($get) => $get('streaming_driver') === 'pushr')
                        ->helperText("The PushrCDN Zone (bucket) ID.")->required(),
                    Select::make('pushr_encoder')
                        ->label('Encoder region')
                        ->options([
                            'eu' => 'eu (Europe)',
                        ])
                        ->helperText("Pushr stream encoder. For now, only `eu` (Europe) is available.")
                        ->required()
                        ->visible(fn ($get) => $get('streaming_driver') === 'pushr')
                        ->default('eu')->columnSpanFull(),

                    Toggle::make('pushr_allow_dvr')->label('Enable VODs')->visible(fn ($get) => $get('streaming_driver') === 'pushr'),

                    Toggle::make('pushr_allow_360p')->label('Allow 360p')->visible(fn ($get) => $get('streaming_driver') === 'pushr'),
                    Toggle::make('pushr_allow_mux')->label('Enable MUX')->visible(fn ($get) => $get('streaming_driver') === 'pushr'),
                    Toggle::make('pushr_allow_480p')->label('Allow 480p')->visible(fn ($get) => $get('streaming_driver') === 'pushr'),
                    Toggle::make('pushr_allow_576p')->label('Allow 576p')->visible(fn ($get) => $get('streaming_driver') === 'pushr'),
                    Toggle::make('pushr_allow_720p')->label('Allow 720p')->visible(fn ($get) => $get('streaming_driver') === 'pushr'),
                    Toggle::make('pushr_allow_1080p')->label('Allow 1080p')->visible(fn ($get) => $get('streaming_driver') === 'pushr'),

                    // === LiveKit ===
                    TextInput::make('livekit_api_key')->label('API key')->visible(fn ($get) => $get('streaming_driver') === 'livekit')->required(),
                    TextInput::make('livekit_api_secret')
                        ->label('API secret')
                        ->visible(fn ($get) => $get('streaming_driver') === 'livekit')
                        ->required()
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password'),
                    TextInput::make('livekit_ws_url')->label('WS URL')->visible(fn ($get) => $get('streaming_driver') === 'livekit')->required(),

                    Toggle::make('allow_free_streams')
                        ->label('Allow free streams')
                        ->helperText('If disabled, PPV only streams will be allowed.')
                        ->visible(fn ($get) => in_array($get('streaming_driver'), ['pushr', 'livekit'])),

                    TextInput::make('max_live_duration')
                        ->label('Max live stream duration')
                        ->numeric()
                        ->minValue(1)
                        ->suffix('hours')
                        ->helperText('Maximum duration allowed for a live stream.')
                        ->visible(fn ($get) => in_array($get('streaming_driver'), ['pushr', 'livekit']))
                        ->columnSpanFull()
                        ->required(),

                ]),
        ]);
    }
}
