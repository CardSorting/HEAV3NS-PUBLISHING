<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read ContentReview|null $contentReview
 * @property int $status
 * @property \Illuminate\Support\Carbon|null $published_at
 */
class Story extends Model
{
    use Traits\ClosesContentReviewsOnDelete;

    public const PENDING_STATUS = 0;
    public const APPROVED_STATUS = 1;
    public const DISAPPROVED_STATUS = 2;

    protected $fillable = [
        'user_id',
        'expires_at',
        'is_highlight',
        'is_public',
        'overlay',
        'mode',
        'text',
        'bg_preset',
        'link_url',
        'link_text',
        'sound_id',
        'status',
        'published_at',
    ];

    protected $casts = [
        'expires_at'  => 'datetime',
        'is_highlight'=> 'boolean',
        'overlay' => 'array',
        'status' => 'integer',
        'published_at' => 'datetime',
    ];

    /**
     * Owner of the story (the user whose bubble this appears under).
     *
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
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('source_type', 'story'));
    }

    /**
     * Attachments that belong to this story (its media items).
     *
     * @return HasMany<Attachment, $this>
     */
    use Traits\HasAttachmentPlacements;

    /**
     * Users who viewed this story.
     *
     * @return HasMany<StoryView, $this>
     */
    public function views(): HasMany
    {
        return $this->hasMany(StoryView::class);
    }

    /**
     * Scope: only not-expired stories (24h style).
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    public function getLinkTextAttribute($value)
    {
        if (!empty($this->attributes['link_url']) && empty($value)) {
            return __('Learn more');
        }

        return $value;
    }

    /**
     * @return BelongsTo<Sound, $this>
     */
    public function sound(): BelongsTo
    {
        return $this->belongsTo(Sound::class, 'sound_id');
    }
}
