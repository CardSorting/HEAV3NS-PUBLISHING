<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Poll extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'post_id', 'ends_at',
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

    ];

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

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id', 'id');
    }

    /**
     * Get the possible answers for this poll.
     */
    /**
     * @return HasMany<PollAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(PollAnswer::class, 'poll_id');
    }

    /**
     * Get all user answers related to this poll.
     */
    /**
     * @return HasMany<PollUserAnswer, $this>
     */
    public function userAnswers(): HasMany
    {
        return $this->hasMany(PollUserAnswer::class, 'poll_id');
    }
}
