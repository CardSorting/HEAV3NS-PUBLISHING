<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ReferralCodeUsage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'used_by', 'referral_code',
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
     * @return HasOne<User, $this>
     */
    public function usedBy(): HasOne
    {
        return $this->hasOne(User::class, 'id', 'used_by');
    }
}
