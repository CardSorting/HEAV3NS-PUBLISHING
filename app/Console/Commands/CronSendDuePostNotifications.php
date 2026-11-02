<?php

namespace App\Console\Commands;

use App\Model\Post;
use App\Helpers\ContentTagsMentionsHelper;
use App\Providers\PostsHelperServiceProvider;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CronSendDuePostNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:send_due_post_notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send follower notifications for posts once they are due';

    /**
     * @return int
     */
    public function handle(): int
    {
        Log::channel('cronjobs')->info('[*]['.date('H:i:s')."] Start sending due post notifications.\r\n");
        $this->info('Start sending due post notifications.');

        $now = Carbon::now();
        $posts = Post::query()
            ->with('user')
            ->whereDoesntHave('user.activeBan')
            ->where('status', Post::APPROVED_STATUS)
            ->where(function ($query) {
                $query->where(fn ($followers) => $followers->where('notify_followers', true)->whereNull('notifications_sent_at'))
                    ->orWhereExists(function ($mentions) {
                        $mentions->selectRaw('1')->from('mentions')->whereColumn('mentions.post_id', 'posts.id')
                            ->whereNotExists(fn ($notifications) => $notifications->selectRaw('1')->from('notifications')
                                ->whereColumn('notifications.post_id', 'posts.id')->where('notifications.type', 'mention')
                                ->whereNull('notifications.post_comment_id')->whereColumn('notifications.to_user_id', 'mentions.mentioned_user_id'));
                    });
            })
            ->where(function ($query) use ($now) {
                $query->where('release_date', '<=', $now);
                $query->orWhereNull('release_date');
            })
            ->where(function ($query) use ($now) {
                $query->where('expire_date', '>', $now);
                $query->orWhereNull('expire_date');
            })
            ->orderBy('release_date')
            ->orderBy('id')
            ->limit(100)
            ->get();

        if ($posts->count() < 1) {
            Log::channel('cronjobs')->info('[*]['.date('H:i:s')."] No due post notifications to send.\r\n");
            $this->info('No due post notifications to send.');

            return 0;
        }

        foreach ($posts as $post) {
            try {
                if ($post->user?->isBanned()) {
                    continue;
                }
                app(ContentTagsMentionsHelper::class)->publishPostMentions($post);
                if ($post->notify_followers && !$post->notifications_sent_at) {
                    PostsHelperServiceProvider::sendPostNotifications($post);
                }

                Log::channel('cronjobs')->info('[*]['.date('H:i:s').'] Successfully sent due post notification for post: '.$post->id.".\r\n");
                $this->info('Sent due post notification for post: '.$post->id.'.');
            } catch (\Exception $exception) {
                Log::channel('cronjobs')->info('[*]['.date('H:i:s').'] Error sending due post notification for post '.$post->id.' error: '.$exception->getMessage());
                $this->error('Error sending due post notification for post '.$post->id.': '.$exception->getMessage());
            }
        }

        Log::channel('cronjobs')->info('[*]['.date('H:i:s')."] Finished sending due post notifications.\r\n");
        $this->info('Finished sending due post notifications.');

        return 0;
    }
}
