<?php

namespace App\Providers;

use App\Events\NewStreamTip;
use App\Model\Notification;
use App\Model\Post;
use App\Model\PostComment;
use App\Model\ReelComment;
use App\Model\Stream;
use App\Model\Transaction;
use App\Model\UserList;
use App\Model\UserListMember;
use App\Model\UserMessage;
use App\Model\User;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\ServiceProvider;
use Pusher\Pusher;
use Ramsey\Uuid\Uuid;
use View;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Creates a notification payload and broadcasts it.
     *
     * @param $type
     * @param $toUser
     * @param $post
     * @param $postComment
     * @param $subscription
     * @param $transaction
     * @param $reaction
     * @param $withdrawal
     * @param $userMessage
     * @param $stream
     * @param $fromUserId
     * @return void|null
     */
    public static function createAndPublishNotification(
        $type,
        $toUser = null,
        $post = null,
        $postComment = null,
        $subscription = null,
        $transaction = null,
        $reaction = null,
        $withdrawal = null,
        $userMessage = null,
        $stream = null,
        $fromUserId = null,
        $reelComment = null
    ) {
        try {
            // generate unique id for notification
            do {
                $id = Uuid::uuid4()->getHex();
            } while (Notification::query()->where('id', $id)->first() != null);

            $notificationData = [];
            $notificationData['id'] = $id;
            $notificationData['from_user_id'] = $fromUserId ?: Auth::id();
            $notificationData['type'] = $type;
            $notificationData['to_user_id'] = null;
            $reelEmail = null;

            if ($post != null && isset($post->id) && isset($post->user_id)) {
                $notificationData['post_id'] = $post->id;
                $notificationData['message'] = __('post notification');
                $notificationData['to_user_id'] = $post->user_id;
            }

            // New top-level post comment
            if ($type === Notification::NEW_COMMENT && $postComment != null && isset($postComment->id) && isset($postComment->message) && isset($postComment->post_id)) {
                $post = Post::query()->where('id', $postComment->post_id)->first();
                // Building up the notification message to be broadcasted & db saved
                if ($post != null) {
                    App::setLocale($post->user->settings['locale']);
                    $fromUser = User::query()->where('id', $postComment->user_id)->first();
                    if ($fromUser != null) {
                        $notificationData['message'] = __(':name added a new comment on your post', ['name'=>$fromUser->name]);
                    }
                    $notificationData['post_comment_id'] = $postComment->id;
                    $notificationData['to_user_id'] = $post->user_id;
                }
                // Sending the user email notification
                $user = User::where('id', $post->user_id)->select(['email', 'name', 'settings'])->first();
                if (isset($user->settings['notification_email_new_comment']) && $user->settings['notification_email_new_comment'] == 'true') {
                    EmailsServiceProvider::sendGenericEmail(
                        [
                            'email' => $user->email,
                            'subject' => __('New comment received'),
                            'title' => __('Hello, :name,', ['name'=>$user->name]),
                            'content' =>  __("You've received a new comment on one of your posts at :siteName.", ['siteName'=>getSetting('site.name')]),
                            'button' => [
                                'text' => __('Your notifications'),
                                'url' => route('my.notifications'),
                            ],
                        ]
                    );
                }
            }

            // Structured comment reply
            if ($type === Notification::COMMENT_REPLY && $toUser && $postComment) {
                $post = Post::query()->with('user')->find($postComment->post_id);
                $fromUser = User::query()->find($postComment->user_id);

                if ($post && $fromUser) {
                    App::setLocale($toUser->settings['locale'] ?? 'en');
                    $notificationData['post_id'] = $post->id;
                    $notificationData['post_comment_id'] = $postComment->id;
                    $notificationData['to_user_id'] = $toUser->id;
                    $notificationData['message'] = __(':name replied to your comment', ['name' => $fromUser->name]);

                    if (($toUser->settings['notification_email_new_comment'] ?? null) === 'true') {
                        EmailsServiceProvider::sendGenericEmail([
                            'email' => $toUser->email,
                            'subject' => __('New comment reply'),
                            'title' => __('Hello, :name,', ['name' => $toUser->name]),
                            'content' => __(':name replied to your comment on :siteName.', [
                                'name' => $fromUser->name,
                                'siteName' => getSetting('site.name'),
                            ]),
                            'button' => [
                                'text' => __('View reply'),
                                'url' => route('posts.get', [
                                    'post_id' => $post->id,
                                    'username' => $post->user->username,
                                    'comment' => $postComment->id,
                                ]).'#comment-'.$postComment->id,
                            ],
                        ]);
                    }
                }
            }

            // Reel comments share the existing comment preferences and activity types.
            if ($reelComment instanceof ReelComment && $toUser
                && in_array($type, [Notification::NEW_COMMENT, Notification::COMMENT_REPLY], true)) {
                $reelComment->loadMissing(['reel', 'user']);
                if (!$reelComment->reel || !$reelComment->user) {
                    return null;
                }
                App::setLocale($toUser->settings['locale'] ?? 'en');
                $isReply = $type === Notification::COMMENT_REPLY;
                $notificationData['reel_id'] = $reelComment->reel_id;
                $notificationData['reel_comment_id'] = $reelComment->id;
                $notificationData['to_user_id'] = $toUser->id;
                $notificationData['message'] = __($isReply ? ':name replied to your comment' : ':name added a new comment on your reel', [
                    'name' => $reelComment->user->name,
                ]);

                if (($toUser->settings['notification_email_new_comment'] ?? null) === 'true') {
                    $reelEmail = [
                        'email' => $toUser->email,
                        'subject' => __($isReply ? 'New comment reply' : 'New comment received'),
                        'title' => __('Hello, :name,', ['name' => $toUser->name]),
                        'content' => $notificationData['message'],
                        'button' => [
                            'text' => __('View'),
                            'url' => route('reels.get', ['reel_id' => $reelComment->reel_id]),
                        ],
                    ];
                }
            }

            // New subscription
            if ($subscription != null && isset($subscription->id) && isset($subscription->sender_user_id)
                && isset($subscription->recipient_user_id)) {
                $notificationData['subscription_id'] = $subscription->id;
                $notificationData['to_user_id'] = $subscription->recipient_user_id;
                $notificationData['from_user_id'] = $subscription->sender_user_id;
                // Setting the locale of the message receiver
                $user = User::where('id', $subscription->recipient_user_id)->select(['email', 'name', 'settings'])->first();
                try{
                    App::setLocale($user->settings['locale']);
                }
                catch (\Exception $e){
                    App::setLocale('en');
                }
                // Building up the notification message to be broadcasted & db saved
                $subscriber = User::query()->where('id', $subscription->sender_user_id)->first();
                if ($subscriber != null) {
                    $notificationData['message'] = __('New subscription from :name', ['name'=>$subscriber->name]);
                } else {
                    $notificationData['message'] = __('A new user subscribed to your profile');
                }
                ListsHelperServiceProvider::managePredefinedUserMemberList($subscription->sender_user_id, $subscription->recipient_user_id, 'follow'); // TODO: Inspect
                // Sending the user email notification
                if (isset($user->settings['notification_email_new_sub']) && $user->settings['notification_email_new_sub'] == 'true') {
                    EmailsServiceProvider::sendGenericEmail(
                        [
                            'email' => $user->email,
                            'subject' => __('You got a new subscriber!'),
                            'title' => __('Hello, :name,', ['name'=>$user->name]),
                            'content' => __('You got a new subscriber! You can see more details over your subscriptions tab.'),
                            'button' => [
                                'text' => __('Manage your subs'),
                                'url' => route('my.settings', ['type' => 'subscriptions']),
                            ],
                        ]
                    );
                }
            }

            // New tip
            if (
                ($transaction != null && isset($transaction->id) && isset($transaction->sender_user_id)
                    && isset($transaction->amount) && isset($transaction->currency) && isset($transaction->recipient_user_id))
                && !in_array($type, [Notification::PPV_UNLOCK])
            ) {
                $notificationData['transaction_id'] = $transaction->id;
                $notificationData['to_user_id'] = $transaction->recipient_user_id;

                // Setting the locale of the message receiver
                $user = User::where('id', $transaction->recipient_user_id)
                    ->select(['email', 'username', 'name', 'settings'])
                    ->first();

                try {
                    App::setLocale($user->settings['locale']);
                } catch (\Exception $e) {
                    App::setLocale('en');
                }

                // Building up the notification message to be broadcasted & db saved
                $sender = User::query()->where('id', $transaction->sender_user_id)->first();
                $amount = PaymentsServiceProvider::getTransactionAmountWithTaxesDeducted($transaction);

                if ($sender != null) {
                    $notificationData['message'] = $sender->name.' '.__('sent you a tip of').' '.$amount.$transaction->currency.'.';
                } else {
                    // Fallback so we always have a message (prevents undefined index later)
                    $notificationData['message'] = __('You received a tip of :amount:currency.', [
                        'amount' => $amount,
                        'currency' => $transaction->currency,
                    ]);
                }

                // Sending the user email notification
                if (isset($user->settings['notification_email_new_tip']) && $user->settings['notification_email_new_tip'] == 'true') {
                    EmailsServiceProvider::sendGenericEmail(
                        [
                            'email' => $user->email,
                            'subject' => __('You got a new tip!'),
                            'title' => __('Hello, :name,', ['name' => $user->name]),
                            'content' => $notificationData['message'],
                            'button' => [
                                'text' => __('Your notifications'),
                                'url' => route('my.notifications', ['type' => 'tips']),
                            ],
                        ]
                    );
                }

                // Dispatching stream chat tip event (ONLY if this tip is associated with a stream)
                // This avoids crashing on tips that aren't stream-related (where $transaction->stream is null).
                if (!empty($transaction->stream_id) && $transaction->stream) {
                    $renderedMessage = View::make('elements.streams.stream-tip-box')
                        ->with('tip', $transaction)
                        ->render();

                    broadcast(new NewStreamTip($transaction->stream->id, $renderedMessage, Auth::user()->id))->toOthers();
                }
            }

            // PPV unlock
            if($type === Notification::PPV_UNLOCK) {
                $message = __("post");
                if($transaction->post_id){
                    $message = __("post");
                    $notificationData['post_id'] = $transaction->post_id;
                }
                if($transaction->stream_id){
                    $message = __("stream");
                    $notificationData['stream_id'] = $transaction->stream_id;
                }
                if($transaction->user_message_id){
                    $message = __("message");
                    $notificationData['user_message_id'] = $transaction->user_message_id;
                }

                $message = __('Someone unlocked your').' '.$message.'.';
                $notificationData['transaction_id'] = $transaction->id;
                $notificationData['to_user_id'] = $toUser = $transaction->recipient_user_id;
                $notificationData['from_user_id'] = $transaction->sender_user_id;
                $notificationData['message'] = $message;

                // Setting the locale of the message receiver
                $user = User::where('id', $transaction->recipient_user_id)->select(['email', 'username', 'name', 'settings'])->first();
                try{
                    App::setLocale($user->settings['locale']);
                }
                catch (\Exception $e){
                    App::setLocale('en');
                }
                if (isset($user->settings['notification_email_new_ppv_unlock']) && $user->settings['notification_email_new_ppv_unlock'] == 'true') {
                    EmailsServiceProvider::sendGenericEmail(
                        [
                            'email' => $user->email,
                            'subject' => __('Your paid content has been unlocked!'),
                            'title' => __('Hello, :name,', ['name'=>$user->name]),
                            'content' => $message,
                            'button' => [
                                'text' => __('Your notifications'),
                                'url' => route('my.notifications', ['type'=>'tips']),
                            ],
                        ]
                    );
                }

            }

            // New post / comment reaction
            if ($reaction != null && isset($reaction->id) && isset($reaction->user_id)) {
                $user = User::query()->where('id', $reaction->user_id)->first();
                // Post reaction
                if ($user != null) {
                    if (isset($reaction->post_id)) {
                        $post = Post::query()->where('id', $reaction->post_id)->first();
                        if ($post != null) {
                            // Setting the locale of the message receiver
                            $toUser = User::where('id', $post->user_id)->select(['email', 'username', 'name', 'settings'])->first();
                            try{
                                App::setLocale($user->settings['locale']);
                            }
                            catch (\Exception $e){
                                App::setLocale('en');
                            }
                            // Building up the notification message to be broadcasted & db saved
                            $notificationData['message'] = __(':name liked your post', ['name'=>$user->name]);
                            $notificationData['post_id'] = $post->id;
                            $notificationData['to_user_id'] = $post->user_id;
                        }
                    }
                    // Post comment reaction
                    if (isset($reaction->post_comment_id)) {
                        $postComment = PostComment::query()->where('id', $reaction->post_comment_id)->first();
                        if ($postComment != null) {
                            // Setting the locale of the message receiver
                            $toUser = User::where('id', $postComment->user_id)->select(['email', 'username', 'name', 'settings'])->first();
                            try{
                                App::setLocale($user->settings['locale']);
                            }
                            catch (\Exception $e){
                                App::setLocale('en');
                            }
                            // Building up the notification message to be broadcasted & db saved
                            $notificationData['message'] = __(':name liked your comment', ['name'=>$user->name]);
                            $notificationData['post_comment_id'] = $postComment->id;
                            $notificationData['to_user_id'] = $postComment->user_id;
                        }
                    }
                }
                $notificationData['reaction_id'] = $reaction->id;
            }

            // Withdrawal request
            if ($withdrawal != null && isset($withdrawal->id) && isset($withdrawal->user_id) && isset($withdrawal->amount)
                && isset($withdrawal->status)) {
                // Setting the locale of the message receiver
                $toUser = User::where('id', $withdrawal->user_id)->select(['email', 'username', 'name', 'settings'])->first();
                App::setLocale($toUser->settings['locale']);
                // Building up the notification message to be broadcasted & db saved
                $notificationData['withdrawal_id'] = $withdrawal->id;
                $notificationData['to_user_id'] = $withdrawal->user_id;
                if(SettingsServiceProvider::leftAlignedCurrencyPosition()) {
                    $key = 'Withdrawal processed';
                } else {
                    $key = 'Withdrawal processed rightAligned';
                }
                $notificationData['message'] = __($key, [
                    'currencySymbol' => SettingsServiceProvider::getWebsiteCurrencySymbol(),
                    'amount' => $withdrawal->amount,
                    'status' =>  $withdrawal->status,
                ]);
            }

            // New user message
            if ($userMessage != null && isset($userMessage->id) && isset($userMessage->sender_id) && isset($userMessage->receiver_id)) {
                $notificationData['user_message_id'] = $userMessage->id;
                $notificationData['from_user_id'] = $userMessage->sender_id;
                $notificationData['to_user_id'] = $userMessage->receiver_id;
                $notificationData['message'] = $userMessage->message ?: ($userMessage->gif ? __('GIF') : __('Attachment'));
            }

            // Expiring live streaming message and email notification
            if($stream && $type === Notification::EXPIRING_STREAM) {
                // Setting the locale of the message receiver
                App::setLocale($stream->user->settings['locale']);
                $message = __('Your live stream is about to end in 30 minutes. You can start another one afterwards.');
                $notificationData['message'] = $message;
                $notificationData['to_user_id'] = $stream->user->id;
                // send email notification
                EmailsServiceProvider::sendGenericEmail(
                    [
                        'email' => $stream->user->email,
                        'subject' => __('Your live stream is about to end'),
                        'title' => __('Hello, :name,', ['name'=>$stream->user->name]),
                        'content' =>  $message,
                        'button' => [
                            'text' => __('Watch streaming'),
                            'url' => Redirect::route('public.stream.get', ['streamID' => $stream->id, 'slug' => $stream->slug])->getTargetUrl(),
                        ],
                    ]
                );
            }

            // Mention notification
            if ($type === Notification::MENTION) {
                // determine receiver
                if ($toUser != null && isset($toUser->id)) {
                    $notificationData['to_user_id'] = $toUser->id;

                    // set locale for receiver
                    try {
                        App::setLocale($toUser->settings['locale']);
                    } catch (\Exception $e) {
                        App::setLocale('en');
                    }

                    // from user (fallback safe)
                    $fromUser = null;
                    if (!empty($notificationData['from_user_id'])) {
                        $fromUser = User::query()->where('id', $notificationData['from_user_id'])->first();
                    }

                    if ($postComment != null && isset($postComment->id)) {
                        $notificationData['post_comment_id'] = $postComment->id;

                        if (isset($postComment->post_id)) {
                            $notificationData['post_id'] = $postComment->post_id;
                        }

                        $notificationData['message'] = $fromUser
                            ? __(':name mentioned you in a comment', ['name' => $fromUser->name])
                            : __('Someone mentioned you in a comment');
                    } elseif ($post != null && isset($post->id)) {
                        $notificationData['post_id'] = $post->id;

                        $notificationData['message'] = $fromUser
                            ? __(':name mentioned you in a post', ['name' => $fromUser->name])
                            : __('Someone mentioned you in a post');
                    } else {
                        $notificationData['message'] = __('You were mentioned');
                    }

                }
            }

            if ($toUser == null && $notificationData['to_user_id'] == null) {
                return null;
            }

            if ($toUser != null && isset($toUser->id) && $notificationData['to_user_id'] == null) {
                $notificationData['to_user_id'] = $toUser->id;
            }

            $toUser = User::query()->where('id', $notificationData['to_user_id'])->first();

            if ($toUser != null) {
                $modelData = $notificationData;
                unset($modelData['message']);

                $notification = Notification::create($modelData);
                $notification->setAttribute('message', $notificationData['message']);

                if (self::shouldSendToastNotificationToUser($toUser)) {
                    self::publishNotification($notification, $toUser);
                }

                if (self::shouldSendPushNotificationToUser($toUser)) {
                    self::publishPushNotification($notification, $toUser);
                }

                if ($reelEmail !== null) {
                    EmailsServiceProvider::sendGenericEmail($reelEmail);
                }
            }
        } catch (\Exception $exception) {
            Log::error('Failed sending notification: '.$exception->getMessage());
        }
    }

    protected static function shouldSendToastNotificationToUser($toUser): bool
    {
        if (!$toUser || !isset($toUser->id)) {
            return false;
        }

        if (!getSetting('profiles.enable_toast_notification_setting')) {
            return false;
        }

        return !isset($toUser->settings['notification_toast_enabled'])
            || $toUser->settings['notification_toast_enabled'] === 'true';
    }

    protected static function shouldSendPushNotificationToUser($toUser): bool
    {
        if (!$toUser || !isset($toUser->id)) {
            return false;
        }

        if (!getSetting('profiles.push_notifications_enabled')) {
            return false;
        }

        return isset($toUser->settings['notification_push_enabled'])
            && $toUser->settings['notification_push_enabled'] === 'true';
    }

    /**
     * Dispatches the notification to puser.
     * @param $notification
     * @param $toUser
     */
    public static function publishNotification($notification, $toUser, $event = 'new-notification')
    {
        try {
            $options = (array) config('broadcasting.connections.pusher.options', []);

            if (!array_key_exists('useTLS', $options)) {
                $options['useTLS'] = (($options['scheme'] ?? 'http') === 'https');
            }

            if (isset($options['port'])) {
                $options['port'] = (int) $options['port'];
            }

            if (!SettingsServiceProvider::hasPusherSettings()) {
                Log::error('Pusher requires keys missing, returning early');
                return;
            }

            $pusher = new Pusher(
                config('broadcasting.connections.pusher.key'),
                config('broadcasting.connections.pusher.secret'),
                config('broadcasting.connections.pusher.app_id'),
                $options // CHANGED: now includes host/port/scheme for Soketi or cluster for Pusher
            );

            $data['message'] = $notification->message;
            $data['type'] = $notification->type;
            $data['notification'] = $notification;

            $pusher->trigger($toUser->username, $event, $data);

        } catch (GuzzleException $guzzleException) {
            Log::error('Pusher guzzle exception: '.$guzzleException->getMessage());
        } catch (\Exception $exception) {
            Log::error('Pusher exception: '.$exception->getMessage());
        }
    }

    public static function publishPushNotification($notification, $toUser): void
    {
        try {
            if (!$toUser || !isset($toUser->id)) {
                return;
            }

            // Optional: respect a user setting
            if (
                isset($toUser->settings['notification_push_enabled']) &&
                $toUser->settings['notification_push_enabled'] !== 'true'
            ) {
                return;
            }

            $filterType = self::getNotificationFilterType($notification);
            $targetUrl = route('my.notifications');

            if ($filterType) {
                $targetUrl = route('my.notifications', ['type' => $filterType]);
            }

            // Optional: smarter target urls by notification type
            if (in_array($notification->type, [Notification::NEW_COMMENT, Notification::COMMENT_REPLY], true) && !empty($notification->post_id)) {
                $post = Post::query()->find($notification->post_id);
                if ($post) {
                    $targetUrl = route('posts.get', [
                        'post_id' => $post->id,
                        'username' => $post->user->username ?? null,
                        'comment' => $notification->post_comment_id,
                    ]);

                    if ($notification->type === Notification::COMMENT_REPLY && $notification->post_comment_id) {
                        $targetUrl .= '#comment-'.$notification->post_comment_id;
                    }
                }
            }

            if ($notification->type === Notification::NEW_MESSAGE) {
                $targetUrl = route('my.messages');
            }

            if (in_array($notification->type, [Notification::NEW_COMMENT, Notification::COMMENT_REPLY], true) && !empty($notification->reel_id)) {
                $targetUrl = route('reels.get', ['reel_id' => $notification->reel_id]);
            }

            $payload = app(\App\Services\WebPushService::class)->buildPayload(
                title: getSetting('site.name') ?: config('app.name'),
                body: $notification->message,
                url: $targetUrl,
                extra: [
                    'tag' => 'notification-'.$notification->id,
                    'notification_id' => $notification->id,
                    'type' => $notification->type,
                ]
            );

            app(\App\Services\WebPushService::class)->sendToUser($toUser->id, $payload);
        } catch (\Exception $exception) {
            Log::error('Web push exception: '.$exception->getMessage());
        }
    }

    /**
     * Dispatches a reaction notification.
     * @param $reaction
     * @return void|null
     */
    public static function createNewReactionNotification($reaction)
    {
        $skip = false;
        if ($reaction->post_id != null) {
            $post = Post::query()->where('id', $reaction->post_id)->first();
            if ($post != null && $post->user_id === $reaction->user_id) {
                $skip = true;
            }
        }

        if ($reaction->post_comment_id != null) {
            $postComment = PostComment::query()->where('id', $reaction->post_comment_id)->first();
            if ($postComment != null && $postComment->user_id === $reaction->user_id) {
                $skip = true;
            }
        }

        if (!$skip) {
            return self::createAndPublishNotification(
                Notification::NEW_REACTION,
                null,
                null,
                null,
                null,
                null,
                $reaction
            );
        }
    }

    /**
     * Dispatches a new post comment notification.
     * @param $postComment
     * @return null
     */
    public static function createNewPostCommentNotification($postComment)
    {
        return self::createAndPublishNotification(
            Notification::NEW_COMMENT,
            null,
            null,
            $postComment,
            null,
            null,
            null
        );
    }

    /**
     * Resolves comment activity to one notification type per recipient.
     *
     * @param list<int> $mentionedUserIds
     */
    public static function createPostCommentNotifications(PostComment $comment, array $mentionedUserIds = []): void
    {
        $comment->loadMissing(['post', 'replyTarget']);
        $authorId = (int) $comment->user_id;
        $recipientTypes = [];

        if ($comment->replyTarget && (int) $comment->replyTarget->user_id !== $authorId) {
            $recipientTypes[(int) $comment->replyTarget->user_id] = Notification::COMMENT_REPLY;
        }

        foreach (array_unique(array_map('intval', $mentionedUserIds)) as $mentionedUserId) {
            if ($mentionedUserId !== $authorId && !isset($recipientTypes[$mentionedUserId])) {
                $recipientTypes[$mentionedUserId] = Notification::MENTION;
            }
        }

        if ($comment->post && (int) $comment->post->user_id !== $authorId) {
            $postOwnerId = (int) $comment->post->user_id;
            if (!isset($recipientTypes[$postOwnerId])) {
                $recipientTypes[$postOwnerId] = Notification::NEW_COMMENT;
            }
        }

        $recipients = User::query()->whereIn('id', array_keys($recipientTypes))->get()->keyBy('id');

        foreach ($recipientTypes as $recipientId => $type) {
            $recipient = $recipients->get($recipientId);
            if (!$recipient) {
                continue;
            }

            self::createAndPublishNotification(
                $type,
                $recipient,
                null,
                $comment,
                null,
                null,
                null,
                null,
                null,
                null,
                $authorId
            );
        }
    }

    /** Notify each reel-comment recipient once; replies take priority for an owner who is also the target. */
    public static function createReelCommentNotifications(ReelComment $comment): void
    {
        $comment->loadMissing(['reel', 'replyTarget']);
        if (!$comment->reel) {
            return;
        }

        $authorId = (int) $comment->user_id;
        $recipientTypes = [];
        if ($comment->replyTarget && (int) $comment->replyTarget->reel_id === (int) $comment->reel_id
            && (int) $comment->replyTarget->user_id !== $authorId) {
            $recipientTypes[(int) $comment->replyTarget->user_id] = Notification::COMMENT_REPLY;
        }
        if ((int) $comment->reel->user_id !== $authorId) {
            $recipientTypes[(int) $comment->reel->user_id] ??= Notification::NEW_COMMENT;
        }

        $recipients = User::query()->whereIn('id', array_keys($recipientTypes))->get()->keyBy('id');
        $locale = App::currentLocale();
        try {
            foreach ($recipientTypes as $recipientId => $type) {
                if ($recipient = $recipients->get($recipientId)) {
                    self::createAndPublishNotification($type, $recipient, fromUserId: $authorId, reelComment: $comment);
                }
            }
        } finally {
            App::setLocale($locale);
        }
    }

    /**
     * Dispatches a new sub notification.
     * @param $subscription
     * @return null
     */
    public static function createNewSubscriptionNotification($subscription)
    {
        return self::createAndPublishNotification(
            Notification::NEW_SUBSCRIPTION,
            null,
            null,
            null,
            $subscription,
            null,
            null
        );
    }

    /**
     * Dispatches a new tip notification.
     * @param $transaction
     * @return null
     */
    public static function createNewTipNotification($transaction)
    {
        return self::createAndPublishNotification(
            Notification::NEW_TIP,
            null,
            null,
            null,
            null,
            $transaction,
            null
        );
    }

    /**
     * Dispatches a PPV unlock notification.
     * @param $transaction
     * @return null
     */
    public static function createNewPPVUnlockNotification($transaction)
    {
        return self::createAndPublishNotification(
            Notification::PPV_UNLOCK,
            null,
            null,
            null,
            null,
            $transaction,
            null
        );
    }

    /**
     * Dispatches a withdrawal request change notification.
     * @param $withdrawal
     * @return null
     */
    public static function createApprovedOrRejectedWithdrawalNotification($withdrawal)
    {
        return self::createAndPublishNotification(
            Notification::WITHDRAWAL_ACTION,
            null,
            null,
            null,
            null,
            null,
            null,
            $withdrawal
        );
    }

    /**
     * Dispatches a new message notification.
     * @param $userMessage
     * @return null
     */
    public static function createNewUserMessageNotification($userMessage)
    {
        return self::createAndPublishNotification(
            Notification::NEW_MESSAGE,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            $userMessage
        );
    }

    /**
     * Dispatches a sub renewal notification.
     * @param $subscription
     * @param $succeeded
     * @return void
     */
    public static function sendSubscriptionRenewalEmailNotification($subscription, $succeeded)
    {
        if ($subscription != null) {
            if ($subscription->subscriber != null && $subscription->creator != null) {
                // send email for the user who initiated the subscription
                if (isset($subscription->subscriber->settings['notification_email_expiring_subs'])
                    && $subscription->subscriber->settings['notification_email_expiring_subs'] == 'true') {
                    $message = $succeeded ? __('successfully renewed') : __('failed renewing');
                    $buttonText = $succeeded ? __('Check out his profile for more content') : __('Go back to the website');
                    $buttonUrl = $succeeded ? route('profile', ['username' => $subscription->creator->username]) : route('home');

                    EmailsServiceProvider::sendGenericEmail(
                        [
                            'email' => $subscription->subscriber->email,
                            'subject' => __('Your subscription renewal'),
                            'title' => __('Hello, :name,', ['name'=>$subscription->subscriber->name]),
                            'content' =>  __('Email subscription updated', ['name'=>$subscription->creator->name, 'message'=>$message]),
                            'button' => [
                                'text' => $buttonText,
                                'url' => $buttonUrl,
                            ],
                        ]
                    );
                }
            }
        }
    }

    /**
     * Generate new tip notification.
     * @param $transaction
     */
    public static function createTipNotificationByTransaction($transaction) {
        if ($transaction != null && $transaction->status === Transaction::APPROVED_STATUS
            && ($transaction->type === Transaction::TIP_TYPE || $transaction->type === Transaction::CHAT_TIP_TYPE)) {
            self::createNewTipNotification($transaction);
        }
    }

    /**
     * Generate new PPV unlock notification.
     * @param $transaction
     */
    public static function createPPVNotificationByTransaction($transaction) {
        if ($transaction != null && $transaction->status === Transaction::APPROVED_STATUS
            && in_array($transaction->type, [
                Transaction::STREAM_ACCESS,
                Transaction::POST_UNLOCK,
                Transaction::MESSAGE_UNLOCK,
            ])) {
            self::createNewPPVUnlockNotification($transaction);
        }
    }

    /**
     * Get notification filter type.
     * @param $notification
     * @return string|null
     */
    public static function getNotificationFilterType($notification) {
        $type = null;
        if ($notification != null) {
            $type = self::getNotificationFilterTypeByType($notification->type);
        }

        return $type;
    }

    protected static function getNotificationFilterTypeByType($notificationType): ?string
    {
        switch ($notificationType) {
            case Notification::NEW_COMMENT:
            case Notification::COMMENT_REPLY:
            case Notification::NEW_MESSAGE:
                return Notification::MESSAGES_FILTER;
            case Notification::NEW_REACTION:
                return Notification::LIKES_FILTER;
            case Notification::NEW_SUBSCRIPTION:
                return Notification::SUBSCRIPTIONS_FILTER;
            case Notification::NEW_TIP:
                return Notification::TIPS_FILTER;
            case Notification::PROMOS_FILTER:
                return Notification::PROMOS_FILTER;
            case Notification::WITHDRAWAL_ACTION:
                return Notification::WITHDRAWAL_ACTION;
            case Notification::PPV_UNLOCK:
                return Notification::PPV_UNLOCK_FILTER;
            case Notification::MENTION:
                return Notification::MENTIONS_FILTER;
        }

        return null;
    }

    /**
     * Gets the user un-read notifications.
     * @return object
     */
    public static function getUnreadNotifications() {
        $unreadNotifications = [
            'total' => 0,
            Notification::MESSAGES_FILTER => 0,
            Notification::TIPS_FILTER => 0,
            Notification::SUBSCRIPTIONS_FILTER => 0,
            Notification::PROMOS_FILTER => 0,
            Notification::LIKES_FILTER => 0,
            Notification::WITHDRAWAL_ACTION => 0,
            Notification::PPV_UNLOCK_FILTER => 0,
            Notification::MENTIONS_FILTER => 0,
        ];
        if(Auth::user()){
            $userId = Auth::user()->id;
            $userUnreadNotifications = Notification::where(['to_user_id' => $userId, 'read' => false])
                ->groupBy('type')->select('type', DB::raw('count(*) as total'))->pluck('total', 'type');
            if(count($userUnreadNotifications)){
                foreach ($userUnreadNotifications as $type => $total){
                    $filterType = self::getNotificationFilterTypeByType($type);
                    if($filterType) {
                        $unreadNotifications[$filterType] += (int)$total;
                        $unreadNotifications['total'] += (int)$total;
                    }
                }
            }
        }

        return (object)$unreadNotifications;
    }

    /**
     * Gets the unread user messages.
     * @return mixed
     */
    public static function getUnreadMessages() {
        // Here we double check the existence of default lists
        // Sometimes people delete default ones out of admin
        ListsHelperServiceProvider::createUserDefaultLists(Auth::user()->id);
        $userID = Auth::user()->id;
        $blockedListId = UserList::query()
            ->where('user_id', $userID)
            ->where('type', 'blocked')
            ->value('id');
        $blockedMembers = $blockedListId
            ? UserListMember::query()
                ->where('list_id', $blockedListId)
                ->pluck('user_id')
                ->toArray()
            : [];
        $count = UserMessage::where('receiver_id', $userID)
            ->whereNotIn('sender_id', $blockedMembers)
            ->where('isSeen', 0)
            ->count();
        return $count;
    }

    public static function createMentionNotification($toUser, $post = null, $postComment = null, $fromUserId = null)
    {
        return self::createAndPublishNotification(
            Notification::MENTION,
            $toUser,
            $post,
            $postComment,
            null,
            null,
            null,
            null,
            null,
            null,
            $fromUserId
        );
    }

    /**
     * Send deposit approved email notification for user.
     * @param $transaction
     */
    public static function sendApprovedDepositTransactionEmailNotification($transaction) {
        if($transaction && $transaction->status === Transaction::APPROVED_STATUS && $transaction->type === Transaction::DEPOSIT_TYPE){
            EmailsServiceProvider::sendGenericEmail(
                [
                    'email' => $transaction->receiver->email,
                    'subject' => __('Your deposit request has been approved'),
                    'title' => __('Hello, :name,', ['name'=>$transaction->receiver->name]),
                    'content' =>  __('Your deposit request of :amount has been approved.', ['amount'=>$transaction->amount]),
                    'button' => [
                        'text' => __('Check your wallet'),
                        'url' => route('my.settings', ['type' => 'wallet']),
                    ],
                ]
            );
        }
    }

    /**
     * Send partially paid NowPayments transaction email notification for website admin.
     * @param $transaction
     */
    public static function sendNowPaymentsPartiallyPaidTransactionEmailNotification($transaction) {
        if($transaction && $transaction->status === Transaction::PARTIALLY_PAID_STATUS){
            $adminEmails = User::where('role_id', 1)->select(['email', 'name'])->get();
            foreach ($adminEmails as $email) {
                EmailsServiceProvider::sendGenericEmail(
                    [
                        'email' => $email,
                        'subject' => __('Partially paid payment'),
                        'title' => __('Hello, :name,', ['name'=>'Admin']),
                        'content' =>  __('There is a partially paid payment done with NowPayments that requires your attention. (:paymentId)', ['paymentId' => $transaction->nowpayments_payment_id]),
                        'button' => [
                            'text' => __('Check payment'),
                            'url' => 'https://account.nowpayments.io/payments',
                        ],
                    ]
                );
            }
        }
    }

    /**
     * @param $stream
     */
    public static function createExpiringStreamNotifications($stream)
    {
        if($stream && $stream->user && $stream->status === Stream::IN_PROGRESS_STATUS) {
            // create website and email notifications
            return self::createAndPublishNotification(
                Notification::EXPIRING_STREAM,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                $stream
            );
        }
    }
}
