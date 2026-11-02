<?php

namespace App\Model;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property-read ContentReview|null $contentReview
 */
class Post extends Model
{
    use Traits\ClosesContentReviewsOnDelete;

    public const PENDING_STATUS = 0;
    public const APPROVED_STATUS = 1;
    public const DISAPPROVED_STATUS = 2;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'text',
        'price',
        'status',
        'release_date',
        'expire_date',
        'is_pinned',
        'notify_followers',
        'notifications_sent_at',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var list<string>
     */
    protected $hidden = [
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'notify_followers' => 'boolean',
        'notifications_sent_at' => 'datetime',
    ];

    public function getIsExpiredAttribute() {
        if($this->expire_date > Carbon::now()){
            return false;
        }
        return true;
    }

    public function getIsScheduledAttribute() {
        if($this->release_date > Carbon::now()){
            return true;
        }
        return false;
    }

    /*
     * Relationships
     */

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /** @return HasOne<ContentReview, $this> */
    public function contentReview(): HasOne
    {
        return $this->hasOne(ContentReview::class, 'source_id')
            ->ofMany(['id' => 'max'], fn ($query) => $query->where('source_type', 'post'));
    }

    /**
     * @return HasMany<PostComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    /**
     * @return HasMany<Reaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(Reaction::class);
    }

    /**
     * @return HasMany<UserBookmark, $this>
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(UserBookmark::class);
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    use Traits\HasAttachmentPlacements;

    /**
     * @return HasOne<Poll, $this>
     */
    public function poll(): HasOne
    {
        return $this->hasOne(Poll::class, 'post_id', 'id');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function postPurchases(): HasMany
    {
        return $this->hasMany(Transaction::class, 'post_id', 'id')->where('status', 'approved')->where('type', 'post-unlock');
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function tips(): HasMany
    {
        return $this->hasMany(Transaction::class)->where('type', 'tip')->where('status', 'approved');
    }

    /**
     * @return HasMany<HashtagLink, $this>
     */
    public function hashtagLinks(): HasMany
    {
        return $this->hasMany(HashtagLink::class, 'post_id', 'id');
    }

    /**
     * @return BelongsToMany<Hashtag, $this>
     */
    public function hashtags(): BelongsToMany
    {
        return $this->belongsToMany(
            Hashtag::class,
            'hashtag_links',
            'post_id',
            'hashtag_id'
        )->withTimestamps();
    }

    /**
     * @return HasMany<Mention, $this>
     */
    public function mentions(): HasMany
    {
        return $this->hasMany(Mention::class, 'post_id', 'id');
    }

    public static function getStatusName($status)
    {
        return match ($status) {
            self::PENDING_STATUS => __('pending'),
            self::APPROVED_STATUS => __('approved'),
            self::DISAPPROVED_STATUS => __('disapproved'),
            default => null,
        };
    }

    // Scopes
    public function scopeNotExpiredAndReleased($query) {
        $query->where(function ($query) {
            $query->where('release_date', '<', Carbon::now());
            $query->orWhere('release_date', null);
        });
        $query->where(function ($query) {
            $query->where('expire_date', '>', Carbon::now());
            $query->orWhere('expire_date', null);
        });
    }
}
