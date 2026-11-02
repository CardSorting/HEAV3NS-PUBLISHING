<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserList extends Model
{
    public const FOLLOWERS_TYPE = 'followers';
    public const FOLLOWING_TYPE = 'following';
    public const BLOCKED_TYPE = 'blocked';
    public const CUSTOM_TYPE = 'custom';

    public $notificationTypes = [
        self::FOLLOWERS_TYPE,
        self::FOLLOWING_TYPE,
        self::BLOCKED_TYPE,
        self::CUSTOM_TYPE,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'name', 'type',
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
     * @return HasMany<UserListMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(UserListMember::class, 'list_id');
    }

    public function getMembersUsers()
    {
        $filteredUsers = [];
        foreach ($this->members as $member) {
            $filteredUsers[] = $member->user;
        }
        return collect($filteredUsers);
    }
}
