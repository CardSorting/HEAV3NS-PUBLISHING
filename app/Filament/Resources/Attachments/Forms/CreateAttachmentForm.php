<?php

namespace App\Filament\Resources\Attachments\Forms;

use App\Model\Attachment;
use App\Providers\AttachmentServiceProvider;
use App\Services\Media\FileAttachmentService;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class CreateAttachmentForm
{
    public static function schema(
        ?int $postId = null,
        ?int $userMessageId = null,
        ?int $storyId = null,
        ?int $soundId = null,
        ?int $userId = null,
        ?int $reelId = null,
    ): array {
        $uuid = str_replace('-', '', Str::uuid()->toString());

        return [
            Placeholder::make('private_file_information')
                ->label(__('File attachment'))
                ->content(fn (Attachment $record): string => (string) $record->original_name)
                ->visible(fn (?Attachment $record): bool => $record && FileAttachmentService::isFile($record))
                ->columnSpanFull(),
            TextInput::make('id')
                ->label(__('admin.resources.attachment.fields.id'))
                ->helperText(__('admin.resources.attachment.help.id'))
                ->required()
                ->default($uuid)
                ->disabled(fn (string $operation): bool => $operation === 'edit'),

            TextInput::make('filename')
                ->label(__('admin.resources.attachment.fields.filename'))
                ->required()
                ->reactive()
                ->visible(fn (?Attachment $record): bool => !$record || !FileAttachmentService::isFile($record))
                ->default("assets/attachments/$uuid"),

            FileUpload::make('file')
                ->label(__('admin.resources.attachment.fields.file'))
                ->directory('assets/attachments')
                ->visibility(AttachmentServiceProvider::getAdminFileUploadVisibility())
                ->image()
                ->preventFilePathTampering()
                ->multiple(false)
                ->imagePreviewHeight('80px')
                ->maxSize(AttachmentServiceProvider::getUploadMaxFilesize())
                ->acceptedFileTypes($soundId !== null ? self::mediaMimeTypes() : self::imageMimeTypes())
                ->columnSpanFull()
                ->required(fn (string $operation) => $operation === 'create')
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->visible(fn (?Attachment $record): bool => !$record || !FileAttachmentService::isFile($record))
                ->getUploadedFileNameForStorageUsing(function (TemporaryUploadedFile $file) use ($soundId): string {
                    if ($soundId !== null) {
                        self::validateMediaUpload($file);
                    } else {
                        self::validateImageUpload($file);
                    }
                    $extension = match ($file->getMimeType()) {
                        'audio/mpeg' => 'mp3',
                        'audio/mp4' => 'm4a',
                        'video/quicktime' => 'mov',
                        default => $file->guessExtension(),
                    };

                    return Str::uuid()->getHex().'.'.$extension;
                })
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    if ($state instanceof TemporaryUploadedFile) {
                        $filename = $get('filename');
                        $filenameWithoutExt = preg_replace('/\.[^\.]+$/', '', $filename);
                        $extension = $state->getClientOriginalExtension();
                        $set('filename', "$filenameWithoutExt.$extension");
                    }
                }),

            Select::make('driver')
                ->label(__('admin.resources.attachment.fields.driver'))
                ->options([
                    Attachment::PUBLIC_DRIVER => 'Public (Local)',
                    Attachment::S3_DRIVER => 'Amazon S3',
                    Attachment::DO_DRIVER => 'DigitalOcean Spaces',
                    Attachment::WAS_DRIVER => 'Wasabi',
                    Attachment::MINIO_DRIVER => 'MinIO',
                    Attachment::PUSHR_DRIVER => 'Pushr',
                    Attachment::R2_DRIVER => 'Cloudflare R2',
                    Attachment::B2_DRIVER => 'Backblaze B2',
                ])
                ->required()
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->default(AttachmentServiceProvider::getStorageProviderID(getSetting('storage.driver')))
                ->helperText(__('admin.resources.attachment.help.driver'))
                ->columnSpanFull(),

            Select::make('type')
                ->label(__('admin.resources.attachment.fields.type'))
                ->required()
                ->disabled(fn (string $operation): bool => $operation === 'edit')
                ->options(
                    fn (?Attachment $record): array => $record ? [$record->type => strtoupper((string) $record->type)] :
                    collect(explode(',', trim(getSetting('media.allowed_file_extensions'))))
                        ->filter(fn (string $extension): bool => in_array(strtolower(trim($extension)), self::uploadExtensions($soundId !== null), true))
                        ->mapWithKeys(fn ($item) => [trim($item) => strtoupper(trim($item))])
                        ->toArray()
                ),

            Select::make('user_id')
                ->label(__('admin.resources.attachment.fields.user_id'))
                ->relationship('user', 'username')
                ->searchable()
                ->required()
                ->preload(true)
                ->default($userId)
                ->disabled(fn (string $operation): bool => $operation === 'edit'),

            Select::make('sound_id')
                ->label(__('admin.resources.attachment.fields.sound_id'))
                ->relationship('sound', 'title')
                ->searchable()
                ->default($soundId)
                ->preload(true)
                ->disabled(fn (string $operation): bool => $operation === 'edit'),

            Toggle::make('has_thumbnail')->hidden(),
            Toggle::make('has_blurred_preview')->hidden(),
        ];
    }

    public static function imageMimeTypes(): array
    {
        return array_values(array_intersect(
            AttachmentServiceProvider::extensionsToMimeTypes((string) getSetting('media.allowed_file_extensions')),
            ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
        ));
    }

    public static function mediaMimeTypes(): array
    {
        return array_values(array_filter(
            AttachmentServiceProvider::extensionsToMimeTypes((string) getSetting('media.allowed_file_extensions')),
            fn (string $mime): bool => str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/') || str_starts_with($mime, 'audio/'),
        ));
    }

    public static function validateMediaUpload(UploadedFile $file): void
    {
        Validator::make(['file' => $file], ['file' => [
            'required', 'file', 'mimetypes:'.implode(',', self::mediaMimeTypes()),
            'max:'.AttachmentServiceProvider::getUploadMaxFilesize(),
        ]])->validate();
    }

    public static function validateImageUpload(UploadedFile $file): void
    {
        Validator::make(['file' => $file], ['file' => [
            'required', 'image', 'mimetypes:'.implode(',', self::imageMimeTypes()),
            'max:'.AttachmentServiceProvider::getUploadMaxFilesize(),
        ]])->validate();
    }

    protected static function uploadExtensions(bool $allowMedia): array
    {
        return $allowMedia
            ? ['jpg', 'jpeg', 'png', 'webp', 'gif', 'wav', 'mp3', 'ogg', 'm4a', 'webm', 'mp4', 'avi', 'mov', 'moov', 'm4v', 'mpeg', 'wmv', 'asf']
            : ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    }

    public static function prepareCreateData(array $data, bool $allowMedia = false): array
    {
        $filename = $data['file'] ?? '';
        if (!is_string($filename) || !preg_match('~^assets/attachments/[a-f0-9]{32}\.[a-z0-9]+$~D', $filename)
            || !in_array(pathinfo($filename, PATHINFO_EXTENSION), self::uploadExtensions($allowMedia), true)) {
            throw ValidationException::withMessages(['data.file' => __('Upload an image using the attachment control.')]);
        }

        return [
            'id' => pathinfo($filename, PATHINFO_FILENAME),
            'filename' => $filename,
            'type' => pathinfo($filename, PATHINFO_EXTENSION),
            'driver' => AttachmentServiceProvider::getStorageProviderID(getSetting('storage.driver')),
            'user_id' => $data['user_id'],
            'sound_id' => $data['sound_id'] ?? null,
            'has_thumbnail' => false,
            'has_blurred_preview' => false,
        ];
    }
}
