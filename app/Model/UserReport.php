<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserReport extends Model
{
    public const I_DONT_LIKE_TYPE = "I don't like this content";
    public const OFFENSIVE_CONTENT_TYPE = "Content is offensive or violates Terms of Service.";
    public const DMCA_TYPE = "Content contains stolen material (DMCA)";
    public const SPAM_TYPE = "Content is spam";
    public const ABUSE_TYPE = "Report abuse";
    public const RECEIVED_STATUS = 'received';
    public const SEEN_STATUS = 'seen';
    public const SOLVED_STATUS = 'solved';

    public static $typesMap = [
        self::I_DONT_LIKE_TYPE,
        self::OFFENSIVE_CONTENT_TYPE,
        self::DMCA_TYPE,
        self::SPAM_TYPE,
        self::ABUSE_TYPE,
    ];

    public static $statusMap = [
        self::RECEIVED_STATUS,
        self::SEEN_STATUS,
        self::SOLVED_STATUS,
        'false',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['from_user_id', 'user_id', 'post_id', 'message_id', 'stream_id', 'story_id', 'reel_id', 'reel_comment_id', 'type', 'details', 'status'];

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
    ];

    /*
     * Relationships
     */

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reportedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function reportedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    /**
     * @return BelongsTo<UserMessage, $this>
     */
    public function reportedMessage(): BelongsTo
    {
        return $this->belongsTo(UserMessage::class, 'message_id');
    }

    /**
     * @return BelongsTo<Stream, $this>
     */
    public function reportedStream(): BelongsTo
    {
        return $this->belongsTo(Stream::class, 'stream_id');
    }

    /**
     * @return BelongsTo<Story, $this>
     */
    public function reportedStory(): BelongsTo
    {
        return $this->belongsTo(Story::class, 'story_id');
    }

    /**
     * @return BelongsTo<Reel, $this>
     */
    public function reportedReel(): BelongsTo
    {
        return $this->belongsTo(Reel::class, 'reel_id');
    }

    /**
     * @return BelongsTo<ReelComment, $this>
     */
    public function reportedReelComment(): BelongsTo
    {
        return $this->belongsTo(ReelComment::class, 'reel_comment_id');
    }

    /**
     * @return array{type: string, id: int}|null
     */
    public function deletedContentReference(): ?array
    {
        if (!preg_match(
            '/^\[Moderation\] Deleted reported ([a-z ]+) #(\d+) by administrator #[^ ]+ on .+\.$/m',
            (string) $this->details,
            $matches,
        )) {
            return null;
        }

        return [
            'type' => $matches[1],
            'id' => (int) $matches[2],
        ];
    }

    public function deletedContentLabel(): ?string
    {
        $reference = $this->deletedContentReference();

        if (!$reference) {
            return null;
        }

        return 'Deleted '.ucfirst($reference['type']).' #'.$reference['id'];
    }
}
