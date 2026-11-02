<?php

namespace App\Model;

use App\Providers\AttachmentServiceProvider;
use App\Services\Media\FileAttachmentService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property \Carbon\CarbonInterface|null $vaulted_at
 * @property \Carbon\CarbonInterface|null $vault_archived_at
 * @property string|null $blurred_preview
 * @property string|null $original_name
 * @property string|null $mime_type
 * @property int|null $size
 * @property bool $is_file_attachment
 */
class Attachment extends Model
{
    public const PUBLIC_DRIVER = 0;
    public const S3_DRIVER = 1;
    public const WAS_DRIVER = 2;
    public const DO_DRIVER = 3;
    public const MINIO_DRIVER = 4;
    public const PUSHR_DRIVER = 5;
    public const R2_DRIVER = 6;
    public const B2_DRIVER = 7;

    // Disable auto incrementing as we set the id manually (uuid)
    public $incrementing = false;

    protected $keyType = 'string';

    public function delete()
    {
        return $this->getConnection()->transaction(fn () => parent::delete());
    }

    protected static function booted(): void
    {
        static::saving(function (self $attachment) {
            if (($attachment->sound_id !== null || str_starts_with(ltrim((string) $attachment->filename, '/'), 'payment-request/'))
                && ($attachment->vaulted_at !== null || ($attachment->exists && $attachment->placements()->exists()))) {
                throw new \LogicException('Vault media cannot be assigned to an operational upload.');
            }
            if ($attachment->exists && $attachment->isDirty('user_id')
                && ($attachment->vaulted_at !== null || $attachment->placements()->exists())) {
                throw new \LogicException('Shared media ownership cannot be changed.');
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'filename',
        'type', 'id', 'driver',
        'sound_id',
        'has_thumbnail', 'has_blurred_preview', 'blurred_filename', 'length',
    ];

    protected $appends = ['attachmentType', 'path', 'thumbnail'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var list<string>
     */
    protected $hidden = [
        'is_file_attachment',
        // Older serialized records can still carry the retired storage metadata.
        'storage_scope', 'storage_location_fingerprint',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'id' => 'string',
        'vaulted_at' => 'datetime',
        'vault_archived_at' => 'datetime',
        'size' => 'integer',
        'is_file_attachment' => 'boolean',
    ];

    /*
     * Virtual attributes
     */

    public function getAttachmentTypeAttribute()
    {
        if (FileAttachmentService::isFile($this)) return 'file';
        return AttachmentServiceProvider::getAttachmentType($this->type);
    }

    public function getPathAttribute()
    {
        if (FileAttachmentService::isFile($this)) return null;
        return AttachmentServiceProvider::getFilePathByAttachment($this);
    }

    public function getThumbnailAttribute()
    {
        if (FileAttachmentService::isFile($this)) return null;
        return AttachmentServiceProvider::getThumbnailPathForAttachmentByResolution($this, 150, 150);
    }

    // TODO: Add get blurredPreview
    public function getBlurredPreviewAttribute()
    {
        if (FileAttachmentService::isFile($this)) return null;
        if(!$this->has_blurred_preview) return null;
        return AttachmentServiceProvider::getBlurredPreviewPathForAttachment($this);
    }

    public function attributesToArray(): array
    {
        $attributes = parent::attributesToArray();
        if (FileAttachmentService::isFile($this)) {
            // File names and metadata require an authorized placement payload.
            foreach (['filename', 'driver', 'original_name', 'mime_type', 'size', 'blurred_filename'] as $key) {
                unset($attributes[$key]);
            }
        }

        return $attributes;
    }

    /*
     * Relationships
     */

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasMany<AttachmentPlacement, $this> */
    public function placements(): HasMany
    {
        return $this->hasMany(AttachmentPlacement::class);
    }

    /**
     * @return BelongsTo<Sound, $this>
     */
    public function sound(): BelongsTo
    {
        return $this->belongsTo(Sound::class, 'sound_id');
    }

    /**
     * @return HasMany<MediaModerationItem, $this>
     */
    public function moderationItems(): HasMany
    {
        return $this->hasMany(MediaModerationItem::class, 'attachment_id');
    }

    /**
     * @return HasOne<AttachmentTranscoding, $this>
     */
    public function transcoding(): HasOne
    {
        return $this->hasOne(AttachmentTranscoding::class, 'attachment_id');
    }

    public static function getDriverName($driver): string
    {
        return match ($driver) {
            self::PUBLIC_DRIVER => "public",
            self::S3_DRIVER => "s3",
            self::WAS_DRIVER => "was",
            self::DO_DRIVER => "do",
            self::MINIO_DRIVER => "minio",
            self::PUSHR_DRIVER => "pushr",
            self::R2_DRIVER => "r2",
            self::B2_DRIVER => "b2",
            default => 'unknown',
        };
    }
}
