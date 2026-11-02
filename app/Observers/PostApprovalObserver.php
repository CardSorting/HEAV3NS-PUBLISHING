<?php

namespace App\Observers;

use App\Model\Post;
use App\Model\User;
use App\Providers\EmailsServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PostApprovalObserver
{
    public function updated(Post $post): void
    {
        // Reviewed content has its own committed decision notification.
        if (!$post->wasChanged('status') || $post->contentReview !== null) {
            return;
        }
        $status = $post->status;
        $postId = $post->id;
        $userId = $post->user_id;
        DB::afterCommit(static function () use ($postId, $userId, $status): void {
            $locale = app()->getLocale();
            try {
                $user = User::find($userId);
                if (!$user) {
                    return;
                }
                app()->setLocale($user->settings['locale'] ?? 'en');
                EmailsServiceProvider::sendGenericEmail([
                    'email' => $user->email, 'subject' => __('Post status updated'),
                    'title' => __('Hello, :name,', ['name' => $user->name]),
                    'content' => __('Your post has been :status.', ['status' => Post::getStatusName($status)]),
                    'button' => [
                        'text' => __('View post'),
                        'url' => route('posts.get', ['post_id' => $postId, 'username' => $user->username]),
                    ],
                ]);
            } catch (Throwable $exception) {
                Log::warning('Post status notification failed.', ['post_id' => $postId, 'exception' => $exception]);
            } finally {
                app()->setLocale($locale);
            }
        });
    }
}
