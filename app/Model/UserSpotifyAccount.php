<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $spotify_id
 * @property string|null $display_name
 * @property string|null $avatar
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property \Carbon\Carbon|null $expires_at
 * @property string|null $anthem_track_id
 * @property array|null $top_artists
 */
class UserSpotifyAccount extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id', 'spotify_id', 'display_name', 'avatar',
        'access_token', 'refresh_token', 'expires_at',
        'anthem_track_id', 'top_artists',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'top_artists' => 'array',
    ];
}
