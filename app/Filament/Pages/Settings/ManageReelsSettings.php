<?php

namespace App\Filament\Pages\Settings;

use App\Settings\ReelsSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ManageReelsSettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-film';

    protected static ?string $slug = 'settings/reels';

    protected static string $settings = ReelsSettings::class;

    protected static ?string $title = 'Reels Settings';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Reels Settings')
                ->persistTabInQueryString('tab')
                ->columnSpanFull()
                ->tabs([
                    Tabs\Tab::make('General')
                        ->columns(2)
                        ->schema([
                            Toggle::make('reels_enabled')
                                ->label('Enable reels')
                                ->helperText('Master switch for the Reels feature.')
                                ->default(true),

                            Toggle::make('allow_public_reels')
                                ->label('Allow public reels')
                                ->helperText('If enabled, creators can publish reels that bypass the subscription paywall.')
                                ->default(true),

                            Toggle::make('allow_sounds')
                                ->label('Enable sounds')
                                ->helperText('Allows creators to attach sounds from the shared sounds library.')
                                ->default(true),

                            Toggle::make('allow_progress_scrubbing')
                                ->label('Allow progress scrubbing')
                                ->helperText('Lets viewers drag or tap the reel progress bar to seek within a reel.')
                                ->default(true),

                            TextInput::make('max_video_length_seconds')
                                ->label('Max video length')
                                ->helperText('Maximum allowed duration for reel videos.')
                                ->numeric()
                                ->minValue(1)
                                ->suffix('seconds')
                                ->required()
                                ->columnSpanFull(),

                        ]),

                    Tabs\Tab::make('Feed widget')
                        ->columns(2)
                        ->schema([
                            Toggle::make('feed_widget_enabled')
                                ->label('Show reels in feed')
                                ->helperText('Injects a horizontal reels strip between feed posts.')
                                ->default(true)
                                ->columnSpanFull(),

                            Select::make('feed_widget_placement_mode')
                                ->label('Placement mode')
                                ->helperText('Choose whether the feed shows one reels strip or repeats strips through longer feeds.')
                                ->options([
                                    'once' => 'Once per feed',
                                    'repeat' => 'Repeat through feed',
                                ])
                                ->default('once')
                                ->required(),

                            TextInput::make('feed_widget_first_after_posts')
                                ->label('First insertion after posts')
                                ->helperText('How many posts should appear before the first reels strip. Use 0 to show it at the top.')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(50)
                                ->required(),

                            TextInput::make('feed_widget_repeat_every_posts')
                                ->label('Repeat every posts')
                                ->helperText('Used when placement mode is set to repeat.')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(100)
                                ->required(),

                            TextInput::make('feed_widget_cards_per_widget')
                                ->label('Max reels loaded per strip')
                                ->helperText('Maximum reels fetched for each horizontal strip. The feed only shows as many as fit onscreen; the rest are available by horizontal drag.')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(30)
                                ->required(),
                        ]),
                ]),
        ]);
    }
}
