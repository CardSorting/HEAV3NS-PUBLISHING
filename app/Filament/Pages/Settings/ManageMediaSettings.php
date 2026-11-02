<?php

namespace App\Filament\Pages\Settings;

use App\Providers\AttachmentServiceProvider;
use App\Services\Media\FileAttachmentService;
use App\Services\Queue\QueueRuntimeService;
use App\Settings\MediaSettings;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use BackedEnum;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManageMediaSettings extends SettingsPage
{
    use HasPageShield;

    protected static ?string $slug = 'settings/media';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-photo';

    protected static string $settings = MediaSettings::class;

    protected static ?string $title = 'Media Settings';

    protected const FFMPEG_SPEED_PRESETS = [
        'nvenc' => [
            'default' => 'p1',
            'options' => [
                'p1' => 'p1 (fastest)',
                'p2' => 'p2',
                'p3' => 'p3',
                'p4' => 'p4',
                'p5' => 'p5 (recommended)',
                'p6' => 'p6',
                'p7' => 'p7 (best quality, slowest)',
            ],
        ],
        'qsv' => [
            'default' => 'veryfast',
            'options' => [
                'veryfast' => 'veryfast (recommended)',
                'faster' => 'faster',
                'fast' => 'fast',
                'medium' => 'medium',
                'slow' => 'slow (better quality, slower)',
            ],
        ],
        'amf' => [
            'default' => 'balanced',
            'options' => [
                'speed' => 'Speed',
                'balanced' => 'Balanced',
                'quality' => 'Quality',
            ],
        ],
        'cpu' => [
            'default' => 'ultrafast',
            'options' => [
                'ultrafast' => 'ultrafast (fastest)',
                'superfast' => 'superfast',
                'veryfast'  => 'veryfast (recommended)',
                'faster'    => 'faster',
                'fast'      => 'fast',
                'medium'    => 'medium (better quality, slower)',
            ],
        ],
    ];

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make()
                ->persistTabInQueryString('tab')
                ->columnSpanFull()
                ->tabs([
                    Tabs\Tab::make('General')
                        ->columns(2)
                        ->schema([

                            $this->uploadsSection(),

                            $this->profileImagesSection(),

                            $this->mediaFeaturesSection(),

                            $this->fileAttachmentsSection(),

                            $this->gifPickerSection(),

                        ]),

                    Tabs\Tab::make('Videos')
                        ->columns(2)
                        ->schema([

                            Placeholder::make('ffmpeg_queue_setup')
                                ->hiddenLabel()
                                ->content(fn () => new \Illuminate\Support\HtmlString(view(
                                    'filament.partials.ffmpeg-queue-setup',
                                    [
                                        'snapshot' => app(QueueRuntimeService::class)->snapshot(),
                                        'runtimeSettingsUrl' => ManageRuntimeSettings::getUrl([
                                            'tab' => 'queue::data::tab',
                                        ]),
                                        'websocketsEnabled' => (bool) (
                                            getSetting('websockets.pusher_app_id')
                                            || getSetting('websockets.soketi_host_address')
                                        ),
                                    ],
                                )->render()))
                                ->visible(fn ($get) => $get('transcoding_driver') === 'ffmpeg')
                                ->columnSpanFull(),

                            Placeholder::make('coconut_warnings')
                                ->hiddenLabel()
                                ->label('')
                                ->content(new \Illuminate\Support\HtmlString(view('filament.partials.coconut-warnings')->render()))
                                ->visible(
                                    // TODO: Watermark should stay visible for images eithe way
                                    fn ($get) => $get('transcoding_driver') === 'coconut'
                                        && (
                                            getSetting('storage.driver') === 'public'
                                            || (!getSetting('websockets.pusher_app_id') && !getSetting('websockets.soketi_host_address'))
                                        )
                                )
                                ->columnSpanFull(),

                            Select::make('transcoding_driver')
                                ->label('Transcoding driver')
                                ->options([
                                    'none' => 'None',
                                    'ffmpeg' => 'FFmpeg',
                                    'coconut' => 'Coconut',
                                ])
                                ->placeholder('Select a driver')
                                ->helperText('Select the video transcoding engine to use.')
                                ->reactive()
                                ->required(),

                            TextInput::make('max_videos_length')
                                ->label('Max video length')
                                ->visible(fn ($get) => in_array($get('transcoding_driver'), ['ffmpeg', 'coconut']))
                                ->required()
                                ->integer()
                                ->suffix('seconds')
                                ->helperText('Set to 0 for unlimited length.'),

                            TextInput::make('ffmpeg_path')
                                ->label('FFmpeg path')
                                ->required()
                                ->helperText("FFmpeg executable path. EG: /usr/bin/ffmpeg")
                                ->visible(fn ($get) => $get('transcoding_driver') === 'ffmpeg'),

                            TextInput::make('ffprobe_path')
                                ->label('FFprobe path')
                                ->required()
                                ->helperText("FFmpeg executable path. EG: /usr/bin/ffprobe")
                                ->visible(fn ($get) => $get('transcoding_driver') === 'ffmpeg'),

                            Select::make('ffmpeg_audio_encoder')
                                ->label('Audio encoder')
                                ->options([
                                    'aac' => 'AAC Encoder',
                                    'libfdk_aac' => 'libfdk_aac Encoder',
                                    'libmp3lame' => 'LAME MP3 Encoder',
                                ])
                                ->required()
                                ->helperText("AAC is recommended, it usually offers the best compatibility.")
                                ->visible(fn ($get) => $get('transcoding_driver') === 'ffmpeg'),

                            Select::make('ffmpeg_video_encoder')
                                ->label('Video encoder')
                                ->options([
                                    'libx264' => 'CPU (libx264)',
                                    'h264_nvenc' => 'NVIDIA GPU (NVENC)',
                                    'h264_qsv' => 'Intel GPU (Quick Sync / Arc)',
                                    'h264_amf' => 'AMD GPU (AMF)',
                                ])
                                ->default('libx264')
                                ->required()
                                ->helperText('Select the encoder to use. If the encoder is not available on the server, the system will fallback to libx264.')
                                ->visible(fn (Get $get) => $get('transcoding_driver') === 'ffmpeg')
                                ->live() // <-- important
                                ->afterStateUpdated(function (Set $set, ?string $state) {
                                    // Reset the dependent field to a valid preset for the selected encoder
                                    if ($state && str_ends_with($state, '_nvenc')) {
                                        $set('ffmpeg_video_speed_preset', 'p5');
                                    } elseif ($state && str_ends_with($state, '_qsv')) {
                                        $set('ffmpeg_video_speed_preset', 'veryfast');
                                    } elseif ($state && str_ends_with($state, '_amf')) {
                                        $set('ffmpeg_video_speed_preset', 'balanced');
                                    } else {
                                        $set('ffmpeg_video_speed_preset', 'veryfast');
                                    }
                                }),

                            Select::make('ffmpeg_video_conversion_quality_preset')
                                ->label('Video quality preset')
                                ->options([
                                    'legacy' => 'Ultra small (legacy)',
                                    'size' => 'Size optimized',
                                    'balanced' => 'Balanced',
                                    'quality' => 'Quality optimized',
                                ])
                                ->required()
                                ->helperText('Controls target bitrate (file size & quality).')
                                ->visible(fn ($get) => $get('transcoding_driver') === 'ffmpeg'),

                            Select::make('ffmpeg_video_speed_preset')
                                ->key(fn (Get $get) => 'ffmpeg_video_speed_preset_'.($get('ffmpeg_video_encoder') ?: 'libx264'))
                                ->label('Speed preset')
                                ->options(fn (Get $get) => self::speedPresetOptions((string) ($get('ffmpeg_video_encoder') ?: 'libx264')))
                                ->dehydrateStateUsing(fn ($state, Get $get) => self::coerceSpeedPreset(
                                    (string) ($get('ffmpeg_video_encoder') ?: 'libx264'),
                                    is_string($state) ? $state : null
                                ))
                                ->required()
                                ->helperText('Controls encoding speed vs efficiency. Slower can improve quality at the same bitrate.')
                                ->visible(fn (Get $get) => $get('transcoding_driver') === 'ffmpeg')
                                ->live(),

                            Toggle::make('enforce_mp4_conversion')
                                ->label('Force MP4 conversion')
                                ->helperText('Re-encodes MP4 uploads instead of storing them as-is. Required when MP4 files also need video processing such as watermarks, blurred previews, or duration metadata.')
                                ->visible(fn ($get) => $get('transcoding_driver') === 'ffmpeg'),

                            TextInput::make('coconut_api_key')
                                ->label('Coconut API key')
                                ->helperText('The coconut API Key')
                                ->required()
                                ->visible(fn ($get) => $get('transcoding_driver') === 'coconut')
                                ->password()
                                ->revealable()
                                ->autocomplete(false),

                            Select::make('coconut_video_region')
                                ->label('Region')
                                ->options([
                                    'us-east-1' => 'us-east-1 (North Virginia)',
                                    'us-west-2' => 'us-west-2 (Oregon)',
                                    'eu-west-1' => 'eu-west-1 (Ireland)',
                                ])
                                ->required()
                                ->helperText('Make sure you\'re using the same region under which you registered the account on')
                                ->visible(fn ($get) => $get('transcoding_driver') === 'coconut'),

                            Select::make('coconut_audio_encoder')
                                ->label('Audio encoder')
                                ->options([
                                    'aac' => 'AAC Encoder',
                                    'mp3' => 'MP3 Encoder',
                                ])
                                ->required()
                                ->helperText("AAC is recommended, it usually offers the best compatibility.")
                                ->visible(fn ($get) => $get('transcoding_driver') === 'coconut'),

                            Select::make('coconut_video_conversion_quality_preset')
                                ->label('Video quality preset')
                                ->options([
                                    'coconut_size' => 'Size optimized',
                                    'coconut_balanced' => 'Balanced',
                                    'coconut_quality' => 'Quality optimized',
                                ])
                                ->required()
                                ->helperText("Better quality speeds up processing, but files will be bigger than the original.")
                                ->visible(fn ($get) => $get('transcoding_driver') === 'coconut'),

                            Toggle::make('coconut_enforce_mp4_conversion')
                                ->label('Force MP4 conversion')
                                ->helperText('Sends MP4 uploads through Coconut instead of storing them as-is. Required when MP4 files also need video processing such as watermarks, blurred previews, or duration metadata.')
                                ->visible(fn ($get) => $get('transcoding_driver') === 'coconut'),
                        ]),

                    Tabs\Tab::make('Watermark')
                        ->columns(2)
                        ->schema([

                            Toggle::make('apply_watermark')
                                ->label('Apply watermark')
                                ->helperText('For images, GD library is required. For videos, either ffmpeg or coconut transcoder.')
                                ->reactive()
                                ->afterStateUpdated(function (Set $set, $state) {
                                    if (!$state) {
                                        $set('use_url_watermark', false);
                                    }
                                }),

                            Toggle::make('use_url_watermark')
                                ->label('Use watermark URL')
                                ->helperText('Adds profile url link as watermark to media. FFmpeg only (not supported by Coconut).')
                                ->reactive()
                                ->visible(fn (Get $get) => (bool) $get('apply_watermark'))
                                ->afterStateUpdated(function (Set $set, $state) {
                                    if ($state) {
                                        $set('watermark_image', null);
                                    } else {
                                        // reset to default whenever URL watermark is disabled
                                        $set('watermark_opacity', 80);
                                    }
                                }),

                            FileUpload::make('watermark_image')
                                ->label('Watermark image')
                                ->directory('assets')
                                ->multiple(false)
                                ->visibility(AttachmentServiceProvider::getAdminFileUploadVisibility())
                                ->image()
                                ->columnSpan(
                                    fn (Get $get) => $get('transcoding_driver') === 'ffmpeg' ? 2 : 1
                                )
                                ->imagePreviewHeight('80px')
                                ->maxSize(AttachmentServiceProvider::getUploadMaxFilesize())
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])
                                ->visible(fn (Get $get) => (bool) $get('apply_watermark') && !(bool) $get('use_url_watermark'))
                                ->afterStateUpdated(function (Set $set, $state) {
                                    // If an image is selected, make sure URL watermark is off
                                    if (!empty($state)) {
                                        $set('use_url_watermark', false);
                                    }
                                }),

                            Select::make('watermark_position')
                                ->label('Watermark position')
                                ->options([
                                    'top-left' => 'Top left',
                                    'top-right' => 'Top right',
                                    'bottom-left' => 'Bottom left',
                                    'bottom-right' => 'Bottom right',
                                ])
                                ->default('bottom-right')
                                ->visible(fn (Get $get) => (bool) $get('apply_watermark'))
                                ->required(fn (Get $get) => (bool) $get('apply_watermark')),

                            TextInput::make('watermark_scale_percent')
                                ->label('Watermark size')
                                ->helperText('Relative to the media width. FFmpeg only.')
                                ->integer()
                                ->minValue(5)
                                ->maxValue(60)
                                ->suffix('%')
                                ->default(25)
                                ->visible(
                                    fn (Get $get) => (bool) $get('apply_watermark')
                                    && !(bool) $get('use_url_watermark')
                                    && $get('transcoding_driver') === 'ffmpeg'
                                )
                                ->required(fn (Get $get) => (bool) $get('apply_watermark')),

                            TextInput::make('watermark_opacity')
                                ->label('Watermark opacity')
                                ->helperText('Only supported for FFmpeg and text watermarks.')
                                ->integer()
                                ->minValue(0)
                                ->maxValue(100)
                                ->suffix('%')
                                ->default(80)
                                ->visible(fn (Get $get) => (bool) $get('apply_watermark') && (bool) $get('use_url_watermark'))
                                ->required(fn (Get $get) => (bool) $get('apply_watermark') && (bool) $get('use_url_watermark')),
                        ]),
                ]),

        ]);
    }

    protected function uploadsSection(): Section
    {
        return Section::make(__('Uploads'))
            ->columns(2)
            ->columnSpanFull()
            ->schema([
                Select::make('allowed_file_extensions')
                    ->label(__('Allowed media formats'))
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => self::mediaFormatOptions(app(MediaSettings::class)->allowed_file_extensions))
                    ->dehydrateStateUsing(fn ($state): string => implode(',', self::normalizeMediaFormats($state)))
                    ->helperText(__('Without video transcoding, only MP4 videos are accepted.')),
                TextInput::make('max_file_upload_size')
                    ->label(__('Max upload size'))
                    ->suffix('MB')
                    ->helperText(__('Maximum allowed size for media and file attachments.')),
                Toggle::make('use_chunked_uploads')
                    ->label(__('Use chunked uploads'))
                    ->helperText(__('Uploads large files in smaller parts to stay within server upload limits.')),
                TextInput::make('upload_chunk_size')
                    ->label(__('Upload chunk size'))
                    ->suffix('MB')
                    ->helperText(__('Keep each upload chunk within the server upload limits.')),
            ]);
    }

    protected function profileImagesSection(): Section
    {
        return Section::make(__('Profile images'))
            ->columns(2)
            ->columnSpanFull()
            ->schema([
                TextInput::make('users_covers_size')
                    ->label(__('User cover size'))
                    ->suffix('px')
                    ->helperText(__('Enter width x height. Maintain the original aspect ratio for best results.')),
                TextInput::make('users_avatars_size')
                    ->label(__('User avatar size'))
                    ->suffix('px')
                    ->helperText(__('Enter width x height. Maintain the original aspect ratio for best results.')),
                TextInput::make('max_avatar_cover_file_size')
                    ->label(__('Max avatar/cover size'))
                    ->suffix('MB')
                    ->helperText(__('Maximum file size for avatar and cover images.')),
            ]);
    }

    protected function mediaFeaturesSection(): Section
    {
        return Section::make(__('Media features'))
            ->columns(2)
            ->columnSpanFull()
            ->schema([
                Toggle::make('disable_media_right_click')
                    ->label(__('Disable right-click on media'))
                    ->helperText(__('Disables right-click on media in posts, messages, stories and reels.')),
                Toggle::make('use_blurred_previews_for_locked_posts')
                    ->label(__('Blur previews for locked posts'))
                    ->helperText(__('Shows blurred previews for locked content. Video previews require video transcoding.')),
                Toggle::make('voice_messages_enabled')
                    ->label(__('Enable voice messages'))
                    ->helperText(__('Allows users to record and send audio messages in chat.')),
                Toggle::make('vault_enabled')
                    ->label(__('Enable Media Vault'))
                    ->helperText(__('Shows the Vault library and pickers. Disabling keeps existing files and normal uploads available.')),
            ]);
    }

    protected static function normalizeMediaFormats(array|string|null $state): array
    {
        $formats = is_array($state) ? $state : explode(',', (string) $state);

        return array_values(array_unique(array_filter(array_map('trim', $formats), fn (string $extension): bool => $extension !== '')));
    }

    protected static function mediaFormatOptions(?string $configuredExtensions): array
    {
        $groups = [
            __('Images') => array_merge(AttachmentServiceProvider::getTypeByExtension('images'), ['gif']),
            __('Video') => AttachmentServiceProvider::getTypeByExtension('video'),
            __('Audio') => AttachmentServiceProvider::getTypeByExtension('audio'),
        ];
        $known = array_merge(...array_values($groups));
        $custom = array_diff(self::normalizeMediaFormats($configuredExtensions), $known);
        if ($custom) $groups[__('Other saved formats')] = $custom;

        return array_map(fn (array $formats): array => array_combine($formats, array_map('strtoupper', $formats)), $groups);
    }

    protected function fileAttachmentsSection(): Section
    {
        return Section::make(__('File attachments'))
            ->description(__('Downloadable files for posts, Messenger and the Vault. The existing Max upload size applies.'))
            ->columns(2)
            ->columnSpanFull()
            ->schema([
                Toggle::make('file_attachments_enabled')
                    ->label(__('Enable file attachments'))
                    ->helperText(__('Disabling stops new uploads and selections. Existing files remain available to permitted viewers.'))
                    ->live(),
                Select::make('file_attachment_formats')
                    ->label(__('Allowed file formats'))
                    ->options(FileAttachmentService::supportedFormats())
                    ->multiple()
                    ->required(fn (Get $get): bool => (bool) $get('file_attachments_enabled'))
                    ->helperText(__('Files are downloaded without an inline document preview. ZIP and Office formats require the PHP ZIP extension.')),
            ]);
    }

    protected function gifPickerSection(): Section
    {
        return Section::make(__('GIF picker'))
            ->description(__('Search GIFs in messages and comments. Existing GIFs remain visible when the picker is disabled.'))
            ->columns(2)
            ->columnSpanFull()
            ->schema([
                Group::make([
                    Toggle::make('gif_picker_enabled')
                        ->label(__('Enable GIF picker'))
                        ->live(),
                    Toggle::make('gif_messenger_enabled')
                        ->label(__('Messenger and story replies'))
                        ->live(),
                    Toggle::make('gif_post_comments_enabled')
                        ->label(__('Post comments and replies'))
                        ->live(),
                    Toggle::make('gif_reel_comments_enabled')
                        ->label(__('Reel comments and replies'))
                        ->live(),
                ])->columns(1),
                Group::make([
                    Select::make('gif_provider')
                        ->label(__('Active GIF provider'))
                        ->options(['klipy' => 'KLIPY', 'giphy' => 'GIPHY'])
                        ->rules([Rule::in(['klipy', 'giphy'])])
                        ->required()
                        ->live(),
                    TextInput::make('gif_klipy_key')
                        ->label(__('KLIPY client key'))
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->visible(fn (Get $get) => $get('gif_provider') === 'klipy')
                        ->required(fn (Get $get) => $get('gif_picker_enabled') && $get('gif_provider') === 'klipy'
                            && ($get('gif_messenger_enabled') || $get('gif_post_comments_enabled') || $get('gif_reel_comments_enabled'))),
                    TextInput::make('gif_giphy_messenger_key')
                        ->label(__('GIPHY Messenger key'))
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->visible(fn (Get $get) => $get('gif_provider') === 'giphy')
                        ->required(fn (Get $get) => $get('gif_picker_enabled') && $get('gif_provider') === 'giphy' && $get('gif_messenger_enabled')),
                    TextInput::make('gif_giphy_post_comments_key')
                        ->label(__('GIPHY post-comments key'))
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->visible(fn (Get $get) => $get('gif_provider') === 'giphy')
                        ->required(fn (Get $get) => $get('gif_picker_enabled') && $get('gif_provider') === 'giphy' && $get('gif_post_comments_enabled')),
                    TextInput::make('gif_giphy_reel_comments_key')
                        ->label(__('GIPHY reel-comments key'))
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->visible(fn (Get $get) => $get('gif_provider') === 'giphy')
                        ->required(fn (Get $get) => $get('gif_picker_enabled') && $get('gif_provider') === 'giphy' && $get('gif_reel_comments_enabled')),
                ])->columns(1),
                Placeholder::make('gif_provider_information')
                    ->hiddenLabel()
                    ->content(__('Use browser client keys from your own provider account. Configure content filtering and disable advertising with the provider. Production access may require approval.'))
                    ->columnSpanFull(),
            ]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Multiple selects cast their state before field hydration hooks run.
        if (array_key_exists('allowed_file_extensions', $data)) {
            $data['allowed_file_extensions'] = self::normalizeMediaFormats($data['allowed_file_extensions']);
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Hidden provider fields are omitted from dehydration, preserving their saved values.
        $values = array_replace(app(MediaSettings::class)->toArray(), $data);
        $rules = [
            'file_attachments_enabled' => ['sometimes', 'boolean'],
            'file_attachment_formats' => ['array', Rule::requiredIf((bool) ($values['file_attachments_enabled'] ?? false))],
            'file_attachment_formats.*' => ['string', 'distinct', Rule::in(array_keys(FileAttachmentService::supportedFormats()))],
            'gif_picker_enabled' => ['boolean'],
            'gif_provider' => ['required', Rule::in(['klipy', 'giphy'])],
        ];
        foreach (['messenger', 'post_comments', 'reel_comments'] as $surface) {
            $rules['gif_'.$surface.'_enabled'] = ['boolean'];
            $rules['gif_giphy_'.$surface.'_key'] = [
                Rule::requiredIf(($values['gif_picker_enabled'] ?? false) && ($values['gif_provider'] ?? '') === 'giphy' && ($values['gif_'.$surface.'_enabled'] ?? true)),
                'nullable', 'string', 'max:255',
            ];
        }
        $rules['gif_klipy_key'] = [
            Rule::requiredIf(($values['gif_picker_enabled'] ?? false) && ($values['gif_provider'] ?? '') === 'klipy'
                && (($values['gif_messenger_enabled'] ?? true) || ($values['gif_post_comments_enabled'] ?? true) || ($values['gif_reel_comments_enabled'] ?? true))),
            'nullable', 'string', 'max:255',
        ];
        Validator::make($values, $rules)->validate();

        if ($values['file_attachments_enabled'] ?? false) {
            if (array_intersect($values['file_attachment_formats'] ?? [], ['zip', 'docx', 'xlsx', 'pptx']) && !$this->archiveUploadsSupported()) {
                throw ValidationException::withMessages([
                    'file_attachment_formats' => __('ZIP and Office uploads require the PHP ZIP extension. Enable it or remove those formats.'),
                ]);
            }
        }

        return $data;
    }

    protected function archiveUploadsSupported(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    protected static function speedPresetGroup(string $encoder): string
    {
        if (str_ends_with($encoder, '_nvenc')) {
            return 'nvenc';
        }

        if (str_ends_with($encoder, '_qsv')) {
            return 'qsv';
        }

        if (str_ends_with($encoder, '_amf')) {
            return 'amf';
        }

        return 'cpu';
    }

    protected static function speedPresetOptions(string $encoder): array
    {
        return self::FFMPEG_SPEED_PRESETS[self::speedPresetGroup($encoder)]['options'];
    }

    protected static function coerceSpeedPreset(string $encoder, ?string $value): string
    {
        $group = self::speedPresetGroup($encoder);
        $meta = self::FFMPEG_SPEED_PRESETS[$group];

        return array_key_exists((string) $value, $meta['options'])
            ? (string) $value
            : $meta['default'];
    }
}
