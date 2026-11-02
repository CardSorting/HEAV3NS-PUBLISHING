<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    public const MESSAGES_FILTER = 'messages';
    public const LIKES_FILTER = 'likes';
    public const SUBSCRIPTIONS_FILTER = 'subscriptions';
    public const TIPS_FILTER = 'tips';
    public const PROMOS_FILTER = 'promos';
    public const PPV_UNLOCK_FILTER = 'PPV';
    public const MENTIONS_FILTER = 'mentions';

    public $notificationTypes = [
        self::MESSAGES_FILTER,
        self::LIKES_FILTER,
        self::SUBSCRIPTIONS_FILTER,
        self::TIPS_FILTER,
        self::PROMOS_FILTER,
        self::PPV_UNLOCK_FILTER,
        self::MENTIONS_FILTER,
    ];

    public const NEW_TIP = 'tip';
    public const NEW_REACTION = 'reaction';
    public const NEW_COMMENT = 'new-comment';
    public const COMMENT_REPLY = 'comment-reply';
    public const NEW_SUBSCRIPTION = 'new-subscription';
    public const WITHDRAWAL_ACTION = 'withdrawal-action';
    public const NEW_MESSAGE = 'new-message';
    public const EXPIRING_STREAM = 'expiring-stream';
    public const PPV_UNLOCK = 'ppv-unlock';
    public const MENTION = 'mention';

    // Disable auto incrementing as we set the id manually (uuid)
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'from_user_id', 'post_id', 'to_user_id', 'type', 'id', 'subscription_id', 'transaction_id', 'stream_id',
        'reaction_id', 'post_comment_id', 'withdrawal_id', 'user_message_id', 'message', 'read', 'sent_expiring_reminder',
        'reel_id', 'reel_comment_id',
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
        'id' => 'string',
    ];

    public function getPPVUnlockTypeAttribute() {
        if($this->post_id){
            return __("post");
        }
        if($this->stream_id){
            return __("stream");
        }
        if($this->user_message_id){
            return __("message");
        }
    }

    /*
     * Relationships
     */

    /**
     * @return BelongsTo<User, $this>
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    /**
     * @return BelongsTo<PostComment, $this>
     */
    public function postComment(): BelongsTo
    {
        return $this->belongsTo(PostComment::class, 'post_comment_id');
    }

    /**
     * @return BelongsTo<Reel, $this>
     */
    public function reel(): BelongsTo
    {
        return $this->belongsTo(Reel::class, 'reel_id');
    }

    /**
     * @return BelongsTo<ReelComment, $this>
     */
    public function reelComment(): BelongsTo
    {
        return $this->belongsTo(ReelComment::class, 'reel_comment_id');
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /**
     * @return BelongsTo<Reaction, $this>
     */
    public function reaction(): BelongsTo
    {
        return $this->belongsTo(Reaction::class, 'reaction_id');
    }

    /**
     * @return BelongsTo<Withdrawal, $this>
     */
    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(Withdrawal::class, 'withdrawal_id');
    }

    /**
     * @return BelongsTo<UserMessage, $this>
     */
    public function userMessage(): BelongsTo
    {
        return $this->belongsTo(UserMessage::class, 'user_message_id');
    }

    /**
     * @return BelongsTo<Stream, $this>
     */
    public function stream(): BelongsTo
    {
        return $this->belongsTo(Stream::class, 'stream_id');
    }
}
