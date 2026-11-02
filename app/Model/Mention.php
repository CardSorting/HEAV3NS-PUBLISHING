<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mention extends Model
{
    protected $table = 'mentions';

    protected $fillable = [
        'mentioned_user_id',
        'mentioned_by_user_id',
        'post_id',
        'post_comment_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function mentionedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_user_id', 'id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function mentionedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_by_user_id', 'id');
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }

    /**
     * @return BelongsTo<PostComment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(PostComment::class, 'post_comment_id', 'id');
    }
}
