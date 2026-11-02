<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Hashtag extends Model
{
    protected $fillable = ['tag'];

    /**
     * @return HasMany<HashtagLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(HashtagLink::class, 'hashtag_id', 'id');
    }

    /**
     * @return HasMany<HashtagLink, $this>
     */
    public function postLinks(): HasMany
    {
        return $this->hasMany(HashtagLink::class, 'hashtag_id', 'id')
            ->whereNotNull('post_id');
    }

    /**
     * @return HasMany<HashtagLink, $this>
     */
    public function commentLinks(): HasMany
    {
        return $this->hasMany(HashtagLink::class, 'hashtag_id', 'id')
            ->whereNotNull('post_comment_id');
    }
}
