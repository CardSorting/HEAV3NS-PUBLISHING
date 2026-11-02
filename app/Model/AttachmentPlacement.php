<?php

namespace App\Model;

use App\Model\Traits\HasSafeGuardedColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * @property Post|null $post
 * @property UserMessage|null $message
 * @property Story|null $story
 * @property Reel|null $reel
 * @property MessageTemplate|null $messageTemplate
 * @property int|null $post_id
 * @property int|null $message_id
 * @property int|null $message_template_id
 * @property int|null $story_id
 * @property int|null $reel_id
 */
class AttachmentPlacement extends Model
{
    use HasSafeGuardedColumns;

    public const PARENTS = [
        'post_id' => Post::class,
        'message_id' => UserMessage::class,
        'message_template_id' => MessageTemplate::class,
        'story_id' => Story::class,
        'reel_id' => Reel::class,
    ];

    protected $guarded = ['id'];

    protected $casts = ['position' => 'integer'];

    protected static function booted(): void
    {
        static::saving(function (self $placement) {
            if (count(array_filter(array_keys(self::PARENTS), fn ($key) => $placement->$key !== null)) !== 1) {
                throw new InvalidArgumentException('An attachment placement must have exactly one parent.');
            }
        });
    }

    public static function parentKey(Model $parent): string
    {
        foreach (self::PARENTS as $key => $class) {
            if ($parent instanceof $class) {
                return $key;
            }
        }
        throw new InvalidArgumentException('Unsupported attachment placement parent.');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(UserMessage::class, 'message_id');
    }

    public function messageTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class);
    }

    public function story(): BelongsTo
    {
        return $this->belongsTo(Story::class);
    }

    public function reel(): BelongsTo
    {
        return $this->belongsTo(Reel::class);
    }
}
