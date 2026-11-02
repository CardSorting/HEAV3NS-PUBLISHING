<?php

namespace App\Model;

use App\Providers\AttachmentServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * @property-read ContentReview|null $contentReview
 * @property int $status
 */
class Reel extends Model
{
    use Traits\ClosesContentReviewsOnDelete;

    public const PENDING_STATUS = 0;
    public const APPROVED_STATUS = 1;
    public const DISAPPROVED_STATUS = 2;

    protected $fillable = [
        'user_id',
        'caption',
        'is_public',
        'overlay',
        'sound_id',
        'status',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'overlay' => 'array',
        'status' => 'integer',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<ContentReview, $this> */
    public function contentReview(): HasOne
    {
        return $this->hasOne(ContentReview::class, 'source_id')
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('source_type', 'reel'));
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    use Traits\HasAttachmentPlacements;

    /**
     * @return HasOneThrough<Attachment, AttachmentPlacement, $this>
     */
    public function video(): HasOneThrough
    {
        return $this->hasOneThrough(Attachment::class, AttachmentPlacement::class, 'reel_id', 'id', 'id', 'attachment_id')
            ->whereIn('attachments.type', AttachmentServiceProvider::getTypeByExtension('video'))
            ->orderBy('attachment_placements.position')->orderBy('attachment_placements.id');
    }

    /**
     * @return HasOneThrough<Attachment, AttachmentPlacement, $this>
     */
    public function cover(): HasOneThrough
    {
        return $this->hasOneThrough(Attachment::class, AttachmentPlacement::class, 'reel_id', 'id', 'id', 'attachment_id')
            ->whereIn('attachments.type', AttachmentServiceProvider::getTypeByExtension('default'))
            ->orderBy('attachment_placements.position')->orderBy('attachment_placements.id');
    }

    /**
     * @return HasMany<ReelView, $this>
     */
    public function views(): HasMany
    {
        return $this->hasMany(ReelView::class);
    }

    /**
     * @return HasMany<ReelComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ReelComment::class);
    }

    /**
     * @return HasMany<Reaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class, 'reel_id');
    }

    /**
     * @return HasMany<UserBookmark, $this>
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(UserBookmark::class, 'reel_id');
    }

    /**
     * @return BelongsTo<Sound, $this>
     */
    public function sound(): BelongsTo
    {
        return $this->belongsTo(Sound::class, 'sound_id');
    }
}
