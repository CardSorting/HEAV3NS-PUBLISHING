<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HashtagLink extends Model
{
    protected $table = 'hashtag_links';

    protected $fillable = [
        'hashtag_id',
        'post_id',
        'post_comment_id',
    ];

    /**
     * @return BelongsTo<Hashtag, $this>
     */
    public function hashtag(): BelongsTo
    {
        return $this->belongsTo(Hashtag::class, 'hashtag_id', 'id');
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
