<?php

namespace App\Model;

use App\Providers\AttachmentServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sound extends Model
{
    protected $fillable = [
        'title',
        'artist',
        'description',
        'is_active',
    ];

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereHas('coverAttachment')
            ->whereHas('audioAttachment');
    }

    /**
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class, 'sound_id');
    }

    /**
     * @return HasOne<Attachment, $this>
     */
    public function audioAttachment(): HasOne
    {
        return $this->hasOne(Attachment::class, 'sound_id')
            ->whereIn('type', AttachmentServiceProvider::getTypeByExtension('audio'));
    }

    /**
     * @return HasOne<Attachment, $this>
     */
    public function coverAttachment(): HasOne
    {
        return $this->hasOne(Attachment::class, 'sound_id')
            ->whereIn('type', AttachmentServiceProvider::getTypeByExtension('default'));
    }
}
