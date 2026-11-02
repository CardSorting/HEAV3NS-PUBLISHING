<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property string|null $external_url
 * @property bool|null $open_in_new_tab
 */
class PublicPage extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'slug',
        'title',
        'short_title',
        'content',
        'page_order',
        'shown_in_footer',
        'show_last_update_date',
        'is_tos',
        'is_privacy',
        'external_url',
        'open_in_new_tab',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var list<string>
     */
    protected $hidden = [
    ];

    protected $attributes = [
        'content' => '[]',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'title' => 'array',
        'short_title' => 'array',
        'content' => 'array',
        'shown_in_footer' => 'boolean',
        'show_last_update_date' => 'boolean',
        'is_tos' => 'boolean',
        'is_privacy' => 'boolean',
        'page_order' => 'integer',
        'open_in_new_tab' => 'boolean',
    ];

    public function getExternalUrl(): ?string
    {
        $url = $this->external_url;
        if (!is_string($url) || !Str::isUrl($url, ['http', 'https'])) {
            return null;
        }

        return $url;
    }

    public function isExternal(): bool
    {
        return $this->getExternalUrl() !== null;
    }

    public function getUrl(): string
    {
        return $this->getExternalUrl() ?? route('pages.get', ['slug' => $this->slug]);
    }

    public function translated(string $field, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $fallback = config('app.fallback_locale', 'en');

        $data = (array) ($this->{$field} ?? []);

        return (string) ($data[$locale] ?? $data[$fallback] ?? '');
    }
}
