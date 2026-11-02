<?php

namespace App\Filament\Pages\Settings;

use App\Settings\StoriesSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use BackedEnum;

class ManageStoriesSettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-play-circle';

    protected static ?string $slug = 'settings/stories';

    protected static string $settings = StoriesSettings::class;

    protected static ?string $title = 'Stories Settings';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Stories Settings')
                ->persistTabInQueryString('tab')
                ->columnSpanFull()
                ->tabs([

                    Tabs\Tab::make('General')
                        ->columns(2)
                        ->schema([
                            Toggle::make('stories_enabled')
                                ->label('Enable stories')
                                ->helperText('Master switch for the entire Stories feature (UI + API).')
                                ->default(true),

                            Toggle::make('allow_highlights')
                                ->label('Allow pinned stories')
                                ->helperText('Allow users to pin stories as highlights.')
                                ->default(true),

                            Toggle::make('allow_public_stories')
                                ->label('Allow public stories')
                                ->helperText('If disabled, all stories are private to eligible viewers.')
                                ->default(true),

                            Toggle::make('allow_cta_links')
                                ->label('Enable CTA links')
                                ->helperText('Allow clickable links inside stories.')
                                ->default(true),

                            Toggle::make('allow_sounds')
                                ->label('Enable sounds')
                                ->helperText('Master switch for story sound support.')
                                ->default(true),
                        ]),

                    Tabs\Tab::make('Limits')
                        ->columns(2)
                        ->schema([
                            TextInput::make('default_story_length_seconds')
                                ->label('Default story length')
                                ->helperText('Used for photo and text stories.')
                                ->numeric()
                                ->minValue(1)
                                ->suffix('seconds')
                                ->required(),

                            TextInput::make('max_video_length_seconds')
                                ->label('Max video length')
                                ->helperText('Maximum allowed duration for video stories.')
                                ->numeric()
                                ->minValue(1)
                                ->suffix('seconds')
                                ->required(),

                            TextInput::make('story_expires_hours')
                                ->label('Story expiry')
                                ->helperText('How long stories remain visible after publishing.')
                                ->numeric()
                                ->minValue(1)
                                ->suffix('hours')
                                ->required(),

                            TextInput::make('max_text_length')
                                ->label('Max text length')
                                ->helperText('Maximum length allowed for story text and overlays.')
                                ->numeric()
                                ->minValue(1)
                                ->suffix('characters')
                                ->required(),

                        ]),

                ]),
        ]);
    }
}
