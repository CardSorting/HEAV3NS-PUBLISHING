<?php

namespace App\Filament\Resources\Attachments;

use App\Filament\Resources\Posts\PostResource;
use App\Filament\Resources\Reels\ReelResource;
use App\Filament\Resources\Stories\StoryResource;
use App\Filament\Resources\UserMessages\UserMessageResource;
use App\Model\Attachment;
use App\Services\Moderation\AttachmentMediaService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class AttachmentUsages
{
    public static function render(Attachment $attachment): HtmlString
    {
        $items = app(AttachmentMediaService::class)->usages($attachment)->map(function (array $usage): string {
            $label = e(ucfirst(str_replace('_', ' ', $usage['type'])).' #'.$usage['id']);
            $resource = match ($usage['type']) {
                'post' => PostResource::class,
                'message' => UserMessageResource::class,
                'story' => StoryResource::class,
                'reel' => ReelResource::class,
                default => null,
            };

            if ($resource && Gate::allows('view', $usage['parent'])) {
                $url = e($resource::getUrl('view', ['record' => $usage['id']]));

                return '<li><a href="'.$url.'">'.$label.'</a></li>';
            }

            return '<li>'.$label.'</li>';
        });

        return new HtmlString($items->isEmpty()
            ? '<p>'.e(__('No current social uses.')).'</p>'
            : '<ul>'.$items->implode('').'</ul>');
    }
}
