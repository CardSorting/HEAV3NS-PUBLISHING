<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Attachments\AttachmentResource;
use App\Model\Attachment;
use App\Model\Post;
use App\Model\UserMessage;
use App\Services\Media\AttachmentEligibility;
use App\Services\Media\FileAttachmentAccess;
use App\Services\Media\FileAttachmentService;
use App\Services\Media\PrivateFileStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FileAttachmentDownloadController extends Controller
{
    public function owner(Request $request, Attachment $attachment): Response
    {
        $this->assertSocialFile($attachment);
        abort_unless((int) $attachment->user_id === (int) $request->user()->id && $attachment->vaulted_at, 403);

        return app(PrivateFileStorage::class)->download($attachment);
    }

    public function post(Request $request, Post $post, Attachment $attachment): Response
    {
        $this->assertSocialFile($attachment);
        abort_unless((int) $attachment->user_id === (int) $post->user_id
            && $post->placements()->where('attachment_id', $attachment->id)->exists(), 404);
        abort_unless($post->user !== null, 404);
        ProfileController::ensureUserProfileIsAccessible($post->user);
        abort_unless(app(FileAttachmentAccess::class)->canViewPost($post, $request->user()), 403);

        return app(PrivateFileStorage::class)->download($attachment);
    }

    public function message(Request $request, UserMessage $message, Attachment $attachment): Response
    {
        $this->assertSocialFile($attachment);
        abort_unless((int) $attachment->user_id === (int) $message->sender_id
            && $message->placements()->where('attachment_id', $attachment->id)->exists(), 404);
        abort_unless(app(FileAttachmentAccess::class)->canViewMessage($message, $request->user()), 403);

        return app(PrivateFileStorage::class)->download($attachment);
    }

    public function admin(Request $request, Attachment $attachment): Response
    {
        $this->assertSocialFile($attachment);
        abort_unless(AttachmentResource::canView($attachment), 403);

        return app(PrivateFileStorage::class)->download($attachment);
    }

    protected function assertSocialFile(Attachment $attachment): void
    {
        abort_unless(FileAttachmentService::isFile($attachment), 404);
        abort_if(app(AttachmentEligibility::class)->isProtected($attachment), 403);
    }
}
