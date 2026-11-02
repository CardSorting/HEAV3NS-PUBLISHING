<?php

namespace App\Filament\Pages\Settings;

use App\Settings\FeedSettings;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ManageFeedSettings extends SettingsPage
{
    use HasPageShield;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rss';

    protected static ?string $slug = 'settings/feed';

    protected static string $settings = FeedSettings::class;

    protected static ?string $title = 'Feed Settings';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Feed Settings')
                ->persistTabInQueryString('tab')
                ->columnSpanFull()
                ->tabs([
                    Tabs\Tab::make('General')
                        ->columns(2)
                        ->schema([
                            TextInput::make('min_post_description')
                                ->label('Min post description length')
                                ->suffix('characters')
                                ->helperText('If set to 0 or left empty, at least one attachment is required per post. Any other value makes attachments optional.'),

                            TextInput::make('post_box_max_height')
                                ->label('Post box max height')
                                ->suffix('px')
                                ->helperText('Images and videos are cropped or scaled to this height when not viewed fullscreen.'),

                            TextInput::make('feed_posts_per_page')
                                ->label('Posts per page')
                                ->helperText('Number of posts shown per page in the feed.')
                                ->columnSpanFull(),

                            Toggle::make('allow_post_polls')
                                ->helperText('When enabled, users can add polls to their posts.'),

                            Toggle::make('enable_post_description_excerpts')
                                ->helperText('If enabled, long post descriptions will be truncated with a \'Show more\' link.'),

                            Toggle::make('allow_post_scheduling')
                                ->helperText('When enabled, users can schedule posts with release and expiry dates'),

                            Toggle::make('disable_posts_text_preview')
                                ->helperText('If enabled, text content in posts and messages will also be hidden behind the paywall.'),

                            Toggle::make('allow_gallery_zoom')
                                ->helperText('If enabled, high-resolution photos in post galleries can be zoomed in during preview.'),

                            Toggle::make('youtube_embeds_enabled')
                                ->label('Enable YouTube embeds')
                                ->helperText('Shows YouTube links as videos in post galleries.'),

                            Toggle::make('autoplay_videos')
                                ->label('Autoplay feed videos')
                                ->helperText('Plays visible uploaded videos muted in feeds, search, bookmarks, and profiles.'),
                        ]),

                    Tabs\Tab::make('Hashtags & Mentions')
                        ->columns(2)
                        ->schema([
                            Group::make()
                                ->columnSpanFull()
                                ->schema([
                                    Grid::make(2)->schema([
                                        Toggle::make('enable_hashtags')
                                            ->label('Enable hashtags')
                                            ->helperText('Allows users to use #hashtags in posts and comments.')
                                            ->reactive(),

                                        Toggle::make('enable_mentions')
                                            ->label('Enable mentions')
                                            ->helperText('Allows users to mention other users using @username.')
                                            ->reactive(),
                                    ]),

                                    Grid::make(2)->schema([
                                        TextInput::make('max_hashtags')
                                            ->label('Max hashtags per item')
                                            ->helperText('Maximum number of hashtags allowed per post/comment.')
                                            ->numeric()
                                            ->minValue(0),

                                        TextInput::make('max_mentions')
                                            ->label('Max mentions per item')
                                            ->helperText('Maximum number of mentions allowed per post/comment.')
                                            ->numeric()
                                            ->minValue(0),
                                    ]),

                                    Toggle::make('enable_mention_suggestions')
                                        ->label('Enable mention suggestions')
                                        ->helperText('Shows an autocomplete dropdown when typing @ to suggest users.'),
                                ]),
                        ]),

                    Tabs\Tab::make('Widgets')
                        ->columns(2)
                        ->schema([
                            Select::make('selected_widget')
                                ->label('Widget')
                                ->options([
                                    'suggestions' => 'Suggestions slider',
                                    'expired' => 'Expired subscriptions',
                                    'search' => 'Search box',
                                    'popular_hashtags' => 'Popular hashtags',
                                ])
                                ->helperText('Select which widget you want to edit.')
                                ->default('suggestions')
                                ->placeholder('Select a widget')
                                ->columnSpanFull()
                                ->reactive(),

                            // =====================
                            // Suggestions slider
                            // =====================
                            Group::make()
                                ->visible(fn ($get) => $get('selected_widget') === 'suggestions')
                                ->columnSpanFull()
                                ->schema([
                                    Grid::make(2)
                                        ->schema([
                                            TextInput::make('feed_suggestions_card_per_page')
                                                ->label('Cards per page')
                                                ->helperText('Number of suggested profiles shown at once in the slider.'),

                                            TextInput::make('feed_suggestions_total_cards')
                                                ->label('Total cards')
                                                ->helperText('Total number of suggestions fetched for the slider.'),
                                        ]),

                                    Grid::make(2)
                                        ->schema([
                                            Group::make()
                                                ->schema([
                                                    Toggle::make('hide_suggestions_slider')
                                                        ->label('Hide the widget')
                                                        ->helperText('Hides the suggestions slider from the feed page when enabled.'),

                                                    Toggle::make('mobile_suggestions_enabled')
                                                        ->label('Show suggestions in mobile feed')
                                                        ->helperText('Shows suggested creators between posts on mobile.'),

                                                    Toggle::make('feed_suggestions_autoplay')
                                                        ->label('Autoplay suggestions')
                                                        ->helperText('Automatically scrolls through suggested profiles in the slider.'),
                                                ]),

                                            Group::make()
                                                ->schema([
                                                    Toggle::make('suggestions_skip_empty_profiles')
                                                        ->label("Skip empty profiles")
                                                        ->helperText('Only shows profiles with both avatar and cover images.'),

                                                    Toggle::make('suggestions_skip_unverified_profiles')
                                                        ->label("Skip non-verified profiles")
                                                        ->helperText('Show only ID verified profiles in suggestions.'),

                                                    Toggle::make('suggestions_use_featured_users_list')
                                                        ->label("Use featured users")
                                                        ->helperText('Limit suggestions to users marked as featured.'),
                                                ]),
                                        ]),
                                ]),

                            // =====================
                            // Expired Subs Widget
                            // =====================
                            Group::make()
                                ->visible(fn ($get) => $get('selected_widget') === 'expired')
                                ->columnSpanFull()
                                ->schema([
                                    Grid::make(2)->schema([
                                        TextInput::make('expired_subs_widget_card_per_page')
                                            ->label('Cards per page')
                                            ->helperText('Number of expired subscriptions shown at once.')
                                            ->numeric()
                                            ->minValue(1),

                                        TextInput::make('expired_subs_widget_total_cards')
                                            ->label('Total cards')
                                            ->helperText('Total number of expired subscriptions loaded into the widget.')
                                            ->numeric()
                                            ->minValue(1),

                                        Toggle::make('expired_subs_widget_hide')
                                            ->label('Hide the widget')
                                            ->helperText('Hides the expired subscriptions slider from view.'),

                                        Toggle::make('expired_subs_widget_autoplay')
                                            ->helperText('Automatically scrolls through expired subscription cards.'),

                                    ]),

                                ]),

                            // =====================
                            // Search Widget
                            // =====================
                            Group::make()
                                ->visible(fn ($get) => $get('selected_widget') === 'search')
                                ->columnSpanFull()
                                ->schema([
                                    Grid::make(2)->schema([
                                        Toggle::make('search_widget_hide')
                                            ->label('Hide the widget')
                                            ->helperText('Removes the search widget from the feed when enabled.'),

                                        Toggle::make('hide_non_verified_users_from_search')
                                            ->label('Hide non-verified profiles')
                                            ->helperText('Prevents unverified profiles from appearing in search results.'),

                                        Select::make('default_search_widget_filter')
                                            ->label('Default search filter')
                                            ->options([
                                                'live' => 'Live',
                                                'top' => 'Top',
                                                'people' => 'People',
                                                'videos' => 'Videos',
                                                'photos' => 'Photos',
                                            ])
                                            ->helperText('Sets the default filter applied to search results.')
                                        ->columnSpanFull(),
                                    ]),
                                ]),

                            // =====================
                            // Popular hashtags widget
                            // =====================
                            Group::make()
                                ->visible(fn ($get) => $get('selected_widget') === 'popular_hashtags')
                                ->columnSpanFull()
                                ->schema([
                                    Grid::make(2)->schema([
                                        Toggle::make('popular_hashtags_widget_disable')
                                            ->label('Hide the widget')
                                            ->helperText('Disables the “Popular hashtags” widget in the feed & feed page.')
                                            ->reactive(),
                                        TextInput::make('popular_hashtags_days')
                                            ->label('Trending window')
                                            ->helperText('Only counts recent hashtag usage. Leave empty for all-time.')
                                            ->numeric()
                                            ->minValue(1)
                                            ->suffix('days')
                                            ->nullable(),
                                    ]),
                                ]),
                        ]),
                ]),
        ]);
    }
}
