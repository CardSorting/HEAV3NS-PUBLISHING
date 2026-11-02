<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PollAnswer extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'poll_id',
        'answer',
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
     * Get the poll that this answer belongs to.
     */
    /**
     * @return BelongsTo<Poll, $this>
     */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class, 'poll_id');
    }

    /**
     * Get all user answers (choices) that point to this particular poll answer.
     */
    /**
     * @return HasMany<PollUserAnswer, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(PollUserAnswer::class, 'answer_id');
    }
}
