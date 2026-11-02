<?php

namespace App\Http\Controllers;

use App\Services\CreatorAccessService;
use App\Http\Requests\DeletePostRequest;
use App\Http\Requests\EditPostCommentRequest;
use App\Http\Requests\SavePostCommentRequest;
use App\Http\Requests\SavePostRequest;
use App\Http\Requests\UpdatePostBookmarkRequest;
use App\Http\Requests\UpdateReactionRequest;
use App\Model\Attachment;
use App\Model\Poll;
use App\Model\PollAnswer;
use App\Model\PollUserAnswer;
use App\Model\Post;
use App\Model\PostComment;
use App\Model\Reaction;
use App\Model\UserBookmark;
use App\Providers\AttachmentServiceProvider;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\ListsHelperServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\ProfileMonetizationServiceProvider;
use App\Providers\PostsHelperServiceProvider;
use App\Services\Media\AttachmentTranscodingService;
use App\Services\GifService;
use App\Model\User;
use App\Services\Moderation\PublicationReviewService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JavaScript;
use View;

class PostsController extends Controller
{
    /**
     * Method used for rendering the single post page.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     * @throws \Exception
     */
    public function getPost(Request $request)
    {
        $post_id = $request->route('post_id');
        $username = $request->route('username');
        $user = PostsHelperServiceProvider::getUserByUsername($username);
        if (!$user) {
            abort(404);
        }

        $post = Post::withCount('tips')
            ->with('user', 'attachments', 'reactions', 'mentions', 'postPurchases')
            ->where('id', $post_id)
            ->first();

        if (!$post) {
            abort(404);
        }

        // Only allowing creators to preview non-released/non-approved/expired posts
        if(!(Auth::check() && Auth::user()->role_id === 1)) {
            if(!Auth::check() || $post->user_id != Auth::user()->id) {
                if($post->status !== Post::APPROVED_STATUS) {
                    abort(404);
                }
                if($post->release_date && $post->release_date > Carbon::now()) {
                    abort(404);
                }
                if($post->expire_date && $post->expire_date < Carbon::now()) {
                    abort(404);
                }
            }
        }

        $post->setAttribute('isSubbed', false);
        // Checking authorization & post existence
        if (PostsHelperServiceProvider::hasActiveSub(Auth::user()->id, $post->user->id)
            || Auth::user()->id == $post->user->id
            || PostsHelperServiceProvider::userPaidForPost(Auth::user()->id, $post->id)
            || (!ProfileMonetizationServiceProvider::userHasPaidProfile($post->user) && ListsHelperServiceProvider::loggedUserIsFollowingUser($post->user->id))
            || (ProfileMonetizationServiceProvider::userHasOpenProfile($post->user) && ListsHelperServiceProvider::loggedUserIsFollowingUser($post->user->id))
            || Auth::user()->role_id === 1
        ) {
            $post->setAttribute('isSubbed', true);
        }

        JavaScript::put([
            'postVars' => [
                'post_id' => $post->id,
                'comment_id' => $request->integer('comment') ?: null,
            ],
        ]);

        $data = [
            'post' => $post,
            'user' => $user,
        ];

        $data['recentMedia'] = false;
        if ($post->getAttribute('isSubbed') || Auth::user()->id == $post->user->id || ProfileMonetizationServiceProvider::userHasOpenProfile($post->user)) {
            $data['recentMedia'] = PostsHelperServiceProvider::getLatestUserAttachments($user->id, 'image');
        }

        return view('pages.post', $data);
    }

    /**
     * Renders the post create page.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function create()
    {
        $creatorAccess = app(CreatorAccessService::class);
        if (!$creatorAccess->canViewCreationPage(Auth::user())) {
            return redirect($creatorAccess->onboardingUrl(Auth::user()))
                ->with('error', $creatorAccess->restrictionMessage(Auth::user()));
        }
        $canPost = $creatorAccess->canPublish(Auth::user());
        $jsData = [
            'isAllowedToPost' => $canPost,
            'mediaSettings' => [
                'allowed_file_extensions' => '.'.str_replace(',', ',.', AttachmentServiceProvider::getAllowedUploadExtensions('post')),
                'max_file_upload_size' => (int)getSetting('media.max_file_upload_size'),
                'use_chunked_uploads' => (bool)getSetting('media.use_chunked_uploads'),
                'upload_chunk_size' => (int)getSetting('media.upload_chunk_size'),
                'transcoding_driver' => getSetting('media.transcoding_driver'),
                'enforce_mp4_conversion' => (bool)getSetting('media.enforce_mp4_conversion'),
                'coconut_enforce_mp4_conversion' => (bool)getSetting('media.coconut_enforce_mp4_conversion'),
                'max_post_description_size' => (int)getSetting('feed.min_post_description'),
            ],
        ];
        if(getSetting('feed.enable_mention_suggestions')) {
            $jsData['mentionContacts'] = GenericHelperServiceProvider::selectizeList(Auth::user()->id, false);
        }
        JavaScript::put($jsData);

        return view('pages.create', []);
    }

    /**
     * Shows post edit template.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function edit(Request $request)
    {
        $postID = $request->route('post_id');
        $post = Post::where('id', $postID)->where('user_id', Auth::user()->id)->with(['attachments.transcoding'])->first();
        if (!$post) {
            abort(404);
        }
        $jsData = [
            'postData' => [
                'id' => $post->id,
                'text' => $post->text,
                'attachments' => $post->attachments
                    /* @phpstan-var \Illuminate\Database\Eloquent\Collection<int, Attachment> $postAttachments */
                    ->pipe(fn ($postAttachments) => $postAttachments)
                    ->map(fn ($attachment) => $attachment instanceof Attachment
                        ? app(AttachmentTranscodingService::class)->payload($attachment)
                        : [])
                    ->values(),
                'price' => $post->price,
                'hasPoll' => $post->poll ? true : false,
            ],
            'isAllowedToPost' => app(CreatorAccessService::class)->canEditPost(Auth::user(), $post),
            'mediaSettings' => [
                'allowed_file_extensions' => '.'.str_replace(',', ',.', AttachmentServiceProvider::getAllowedUploadExtensions('post')),
                'max_file_upload_size' => (int) getSetting('media.max_file_upload_size'),
                'use_chunked_uploads' => (bool)getSetting('media.use_chunked_uploads'),
                'upload_chunk_size' => (int)getSetting('media.upload_chunk_size'),
                'transcoding_driver' => getSetting('media.transcoding_driver'),
                'enforce_mp4_conversion' => (bool)getSetting('media.enforce_mp4_conversion'),
                'coconut_enforce_mp4_conversion' => (bool)getSetting('media.coconut_enforce_mp4_conversion'),
                'max_post_description_size' => (int)getSetting('feed.min_post_description'),
            ],
        ];
        if(getSetting('feed.enable_mention_suggestions')) {
            $jsData['mentionContacts'] = GenericHelperServiceProvider::selectizeList(Auth::user()->id, false);
        }
        JavaScript::put($jsData);

        return view('pages.create', [
            'post' => $post,
        ]);
    }

    /**
     * Method used for creating / editing posts.
     *
     * @param SavePostRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function savePost(SavePostRequest $request, AttachmentTranscodingService $transcodings)
    {
        $creatorAccess = app(CreatorAccessService::class);
        $canPublish = $creatorAccess->canPublish($request->user());
        $existingPost = !$canPublish && $request->get('type') === 'update'
            ? Post::whereKey($request->get('id'))->where('user_id', $request->user()->id)->first() : null;
        if (!$canPublish && (!$existingPost || !$creatorAccess->canEditPost($request->user(), $existingPost))) {
            $message = $creatorAccess->restrictionMessage($request->user());

            return response()->json(['success' => false, 'message' => $message, 'errors' => ['permissions' => $message]], 403);
        }
        try {
            $releaseDate = $request->get('postReleaseDate') ? Carbon::parse($request->get('postReleaseDate')) : Carbon::now();
            if (!$canPublish && $releaseDate->isFuture()) {
                throw ValidationException::withMessages(['postReleaseDate' => __('Creator access is required to schedule a post.')]);
            }
            $attachmentIds = collect($request->get('attachments') ?? [])
                ->map(fn ($attachment) => $attachment['attachmentID'] ?? $attachment['id'] ?? null)
                ->filter()
                ->unique()
                ->values()
                ->all();

            $ownedAttachmentCount = Attachment::query()
                ->whereIn('id', $attachmentIds)
                ->where('user_id', $request->user()->id)
                ->count();

            if ($ownedAttachmentCount !== count($attachmentIds)) {
                return response()->json([
                    'success' => false,
                    'message' => __('One or more attachments are no longer available. Please remove them and try again.'),
                ], 422);
            }

            if ($transcodings->hasUnavailableAttachments($attachmentIds, $request->user()->id)) {
                return response()->json([
                    'success' => false,
                    'message' => __('A video is still processing or needs attention. Wait for it to finish, retry it, or remove it.'),
                ], 422);
            }

            $type = $request->get('type');
            $postStatus = PostsHelperServiceProvider::getDefaultPostStatus(Auth::user()->id);
            $postID = null;
            $post = null;
            $postNotifications = getSetting('profiles.enable_new_post_notification_setting') && $request->get('postNotifications') == 'true';

            // Always parse requested dates
            $expireDate = $request->get('postExpireDate') ? Carbon::parse($request->get('postExpireDate')) : null;

            $pollAnswers = $request->get('pollAnswers');

            $post = DB::transaction(function () use ($type, $request, $releaseDate, $expireDate, $postStatus, $postNotifications, $pollAnswers, $attachmentIds, $creatorAccess) {
                // Serialize scheduled publication with administrator access changes.
                $actor = User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                if (($type === 'create' || $releaseDate->isFuture()) && !$creatorAccess->canPublish($actor)) {
                    throw new AuthorizationException($creatorAccess->restrictionMessage($actor));
                }
                $postID = null;
                $post = null;
                if ($type == 'create') {
                    // Adjust release_date if it's in the past
                    if ($releaseDate->isPast()) {
                        $releaseDate = Carbon::now();
                    }

                    $postID = Post::create([
                        'user_id' => $request->user()->id,
                        'text' => $request->get('text'),
                        'price' => $request->get('price'),
                        'status' => $postStatus,
                        'release_date' => $releaseDate->toDateTimeString(),
                        'expire_date' => $expireDate?->toDateTimeString(),
                        'notify_followers' => $postNotifications,
                    ])->id;

                    if ($pollAnswers) {
                        PostsHelperServiceProvider::createNewPoll($postID, $pollAnswers);
                    }

                } elseif ($type == 'update') {
                    $postID = $request->get('id');
                    $post = Post::where('id', $postID)->where('user_id', Auth::user()->id)->lockForUpdate()->first();

                    if ($post) {
                        if (!$creatorAccess->canEditPost($actor, $post)) {
                            throw new AuthorizationException($creatorAccess->restrictionMessage($actor));
                        }
                        // Adjust release_date if it's in the past
                        if ($releaseDate->isPast()) {
                            $releaseDate = $post->created_at;
                        }

                        $post->update([
                            'text' => $request->get('text'),
                            'price' => $request->get('price'),
                            'release_date' => $releaseDate->toDateTimeString(),
                            'expire_date' => $expireDate?->toDateTimeString(),
                        ]);

                        $postID = $post->id;

                        if ($pollAnswers) {
                            if ($post->poll) {
                                PostsHelperServiceProvider::updatePoll($post, $pollAnswers);
                            } else {
                                PostsHelperServiceProvider::createNewPoll($postID, $pollAnswers);
                            }
                        } else {
                            // TODO: Fix this -- it deletes polls
                            if ($post->poll) {
                                $post->poll->delete();
                            }
                        }
                    } else {
                        abort(403, __('Post not found'));
                    }
                }

                // Attaching files to the post
                if ($postID) {
                    // Syncing tags and mention if needed
                    $post = Post::query()->find($postID);
                    if ($post) {
                        app(\App\Services\Media\AttachmentPlacementService::class)->sync($post, $attachmentIds, (int) Auth::id());
                        app(PublicationReviewService::class)->submit($post);
                        app(\App\Helpers\ContentTagsMentionsHelper::class)->syncForPost($post, false);
                    }
                }
                abort_unless($post !== null, 422, __('Invalid post.'));
                return $post;
            }, 3);
            $post->refresh();

            $message = __('Post created.');
            if ($type == 'update') {
                $message = __('Post updated successfully.');
            }
            else {
                try {
                    // Sending post notifications
                    if($post && $post->notify_followers && (int) $post->status === Post::APPROVED_STATUS) {
                        $isReleased = !$post->release_date || Carbon::parse($post->release_date)->lte(Carbon::now());
                        if ($isReleased && !$post->notifications_sent_at) {
                            PostsHelperServiceProvider::sendPostNotifications($post);
                        }
                    }
                    // Sending approval admin email if needed
                    if(getSetting('admin.send_notifications_on_pending_posts') && (int) $post->status === Post::PENDING_STATUS) {
                        PostsHelperServiceProvider::sendAdminPostsApprovalNotifications();
                    }
                } catch (\Throwable $exception) {
                    // The post is already committed. Notification failures must not invite a second submission.
                    report($exception);
                }
            }

            if (!PublicationReviewService::isApproved($post)) {
                $message = (int) $post->status === Post::PENDING_STATUS ? __('Submitted for review.') : __('Rejected');
            } else {
                try {
                    app(\App\Helpers\ContentTagsMentionsHelper::class)->publishPostMentions($post);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }

            return response()->json([
                'success' => 'true', 'message' => $message,
                'publication_review' => PublicationReviewService::presentation($post),
            ]);

        } catch (AuthorizationException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 403);
        } catch (ValidationException $exception) {
            return response()->json(['success' => false, 'errors' => $exception->errors(), 'message' => $exception->getMessage()], 422);
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['success' => false, 'message' => __('An internal error has occurred.')], 500);
        }
    }

    /**
     * Gets (ajaxed) post comments.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPostComments(Request $request)
    {
        try {
            $postID = $request->get('post_id');

            // Checking authorization & post existence
            $post = Post::with(['user'])->where('id', $postID)->first();
            if (!$post) {
                return response()->json(['success' => false, 'errors' => [__('Not found')], 'message'=> __('Post not found')], 404);
            }

            if ($this->validateUserAccessForPost($post)) {
                $limit = max(1, min(50, (int) ($request->get('limit') ?: 9)));
                $commentsData = PostsHelperServiceProvider::getPostComments(
                    $postID,
                    $limit,
                    'DESC',
                    true,
                    $request->integer('target_comment_id') ?: null
                );

                if (
                    $request->boolean('include_mention_contacts')
                    && getSetting('feed.enable_mentions')
                    && getSetting('feed.enable_mention_suggestions')
                ) {
                    $commentsData['mentionContacts'] = collect(
                        GenericHelperServiceProvider::selectizeList(Auth::id(), false)
                    )->map(static fn (array $contact) => [
                        'id' => $contact['id'],
                        'username' => $contact['username'],
                        'name' => $contact['name'],
                        'avatar' => $contact['avatar'],
                    ])->values()->all();
                }

                return response()->json([
                    'success' => true,
                    'data' => $commentsData,
                ]);
            } else {
                return response()->json(['success' => false, 'errors' => [__('Not authorized')], 'message' => __('Not authorized')], 403);
            }
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'errors' => [$exception->getMessage()]]);
        }
    }

    /**
     * Loads a bounded batch of replies for a top-level comment.
     */
    public function getCommentReplies(Request $request)
    {
        $data = $request->validate([
            'post_id' => ['required', 'integer', 'exists:posts,id'],
            'root_id' => ['required', 'integer', 'exists:post_comments,id'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $post = Post::with('user')->find((int) $data['post_id']);
        $root = PostComment::query()
            ->where('id', (int) $data['root_id'])
            ->where('post_id', (int) $data['post_id'])
            ->whereNull('parent_id')
            ->first();

        if (!$post || !$root || !$this->validateUserAccessForPost($post)) {
            return response()->json(['message' => __('Not authorized')], 403);
        }

        $offset = (int) ($data['offset'] ?? 0);
        $query = PostComment::with([
            'author',
            'post.user',
            'reactions',
            'mentions.mentionedUser',
            'replyTarget.author',
        ])->where('parent_id', $root->id);

        $total = (clone $query)->count();
        $replies = $query
            ->oldest('created_at')
            ->oldest('id')
            ->offset($offset)
            ->limit(5)
            ->get();

        app(\App\Services\UserStaffBadge::class)->preload($replies->pluck('author'));

        return response()->json([
            'success' => true,
            'html' => $replies->map(fn (PostComment $reply) => View::make('elements.feed.post-comment', [
                'comment' => $reply,
                'isFirst' => false,
                'isReply' => true,
            ])->render())->values(),
            'total' => $total,
            'next_offset' => $offset + $replies->count(),
        ]);
    }

    /**
     * Method used for adding a new post comment.
     *
     * @param SavePostCommentRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addNewComment(SavePostCommentRequest $request)
    {
        try {
            $data = $request->validated();
            $postID = (int) $data['post_id'];

            // Checking authorization & post existence
            $post = Post::where('id', $postID)->first();
            if (!$post) {
                return response()->json(['success' => false, 'errors' => [__('Not found')], 'message' => __('Post not found')], 404);
            }

            if(GenericHelperServiceProvider::hasUserBlocked($post->user_id, Auth::user()->id)) {
                return response()->json(['success' => false, 'errors' => [__('This user has blocked you')], 'message'=> __('This user has blocked you')], 403);
            }

            if ($this->validateUserAccessForPost($post)) {
                $gif = app(GifService::class)->validateSelection($data['gif'] ?? null, 'post_comments');
                $message = (string) ($data['message'] ?? '');
                if (trim($message) === '' && $gif === null) {
                    throw ValidationException::withMessages(['message' => __('Write a message or choose a GIF.')]);
                }
                $replyTarget = null;
                $parentID = null;

                if (!empty($data['reply_to_id'])) {
                    $replyTarget = PostComment::query()
                        ->where('id', (int) $data['reply_to_id'])
                        ->where('post_id', $postID)
                        ->first();

                    if (!$replyTarget) {
                        return response()->json(['message' => __('Invalid comment.')], 422);
                    }

                    $parentID = $replyTarget->parent_id ?: $replyTarget->id;
                }

                $wasFirst = !$parentID && PostComment::where('post_id', $postID)->whereNull('parent_id')->doesntExist();
                $comment = PostComment::create([
                    'message' => $message,
                    'gif' => $gif,
                    'post_id' => $postID,
                    'user_id' => Auth::user()->id,
                    'parent_id' => $parentID,
                    'reply_to_id' => $replyTarget?->id,
                ]);

                $mentionedUserIds = app(\App\Helpers\ContentTagsMentionsHelper::class)
                    ->syncForComment($comment, false);
                $comment->load([
                    'author',
                    'post.user',
                    'mentions.mentionedUser',
                    'replyTarget.author',
                ]);
                $comment->loadCount('replies');
                NotificationServiceProvider::createPostCommentNotifications($comment, $mentionedUserIds);

                if (!$parentID) {
                    $comment->setRelation('replies', collect());
                }

                return response()->json([
                    'success' => true,
                    'data' => $parentID
                        ? View::make('elements.feed.post-comment', [
                            'comment' => $comment,
                            'isFirst' => false,
                            'isReply' => true,
                        ])->render()
                        : View::make('elements.feed.post-comment-thread', [
                            'comment' => $comment,
                            'isFirst' => $wasFirst,
                        ])->render(),
                    'is_reply' => (bool) $parentID,
                    'parent_id' => $parentID,
                    'comments' => PostComment::where('post_id', $postID)->count(),
                    'thread_replies' => $parentID
                        ? PostComment::where('parent_id', $parentID)->count()
                        : 0,
                ]);
            }
            else {
                return response()->json(['success' => false, 'errors' => [__('Not authorized')], 'message' => __('Not authorized')], 403);
            }

        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'errors' => [$exception->getMessage()]]);
        }
    }

    /**
     * Method used for adding a new post comment.
     *
     * @param EditPostCommentRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function editComment(EditPostCommentRequest $request)
    {
        try {
            $data = $request->validated();
            $comment = (string) ($data['message'] ?? '');
            $postID = (int) $data['post_id'];
            $commentID = (int) $data['comment_id'];

            // Checking authorization & post existence
            $post = Post::where('id', $postID)->first();
            $postComment = PostComment::query()
                ->where('id', $commentID)
                ->where('post_id', $postID)
                ->where('user_id', Auth::id())
                ->first();
            if (!$post || !$postComment) {
                return response()->json(['success' => false, 'errors' => [__('Not found')], 'message' => __('Post not found')], 404);
            }

            if(GenericHelperServiceProvider::hasUserBlocked($post->user_id, Auth::user()->id)) {
                return response()->json(['success' => false, 'errors' => [__('This user has blocked you')], 'message'=> __('This user has blocked you')], 403);
            }

            if ($this->validateUserAccessForPost($post)) {
                $comment = array_key_exists('message', $data) ? (string) ($data['message'] ?? '') : (string) $postComment->message;
                $gifService = app(GifService::class);
                $existingGif = $gifService->normalize($postComment->gif);
                $gif = array_key_exists('gif', $data)
                    ? $gifService->validateSelection($data['gif'], 'post_comments', $existingGif)
                    : $existingGif;
                if (trim($comment) === '' && $gif === null) {
                    throw ValidationException::withMessages(['message' => __('Write a message or choose a GIF.')]);
                }
                $postComment->message = $comment;
                $postComment->gif = $gif;
                $postComment->save();

                // Sync hashtags + mentions for this edited comment
                app(\App\Helpers\ContentTagsMentionsHelper::class)->syncForComment($postComment);
                $postComment->load(['author', 'post.user', 'mentions.mentionedUser', 'replyTarget.author']);

                // Determine if this is the first-ever comment on the post
                $firstCommentId = PostComment::where('post_id', $postID)
                    ->whereNull('parent_id')
                    ->orderBy('created_at', 'asc')   // or ->orderBy('id', 'ASC') if you prefer by ID
                    ->value('id');
                $wasFirst = ($postComment->id === $firstCommentId);

                return response()->json([
                    'success' => true,
                    'data' => View::make('elements.feed.post-comment', [
                        'comment' => $postComment,
                        'isFirst' => $wasFirst,
                        'isReply' => (bool) $postComment->parent_id,
                    ])->render(),
                ]);
            }
            else {
                return response()->json(['success' => false, 'errors' => [__('Not authorized')], 'message' => __('Not authorized')], 403);
            }

        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'errors' => [$exception->getMessage()]]);
        }
    }

    /**
     * Method used for adding / removing a post / comment reaction.
     *
     * @param UpdateReactionRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateReaction(UpdateReactionRequest $request)
    {
        $type = $request->get('type');
        $action = $request->get('action');
        $id = $request->get('id');

        $data = [
            'reaction_type' => 'like',
            'user_id' => Auth::user()->id,
        ];

        try {
            // Checking authorization & post existence
            $postComment = PostComment::where('id', $id)->first();
            $post = null;
            if ($postComment != null) {
                $post = $postComment->post;
            } elseif ($type === 'post' && $id != null) {
                $post = Post::where('id', $id)->first();
            }

            if (!$post) {
                return response()->json(['success' => false, 'errors' => [__('Not found')], 'message' => __('Post not found')], 404);
            }

            if ($this->validateUserAccessForPost($post)) {
                if ($type == 'post') {
                    $data['post_id'] = $id;
                } elseif ($type == 'comment') {
                    $data['post_comment_id'] = $id;
                }
                $message = '';
                if ($action == 'add') {
                    $message = __('Reaction added.');
                    $reaction = Reaction::create($data);

                    if ($reaction != null) {
                        NotificationServiceProvider::createNewReactionNotification($reaction);
                    }
                } elseif ($action == 'remove') {
                    $message = __('Reaction removed.');
                    Reaction::where($data)->first()->delete();
                }

                return response()->json(['success' => true, 'message' => $message]);
            }

            return response()->json(['success' => false, 'errors' => [__('Not authorized')], 'message' => __('Not authorized')], 403);

        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'errors' => [__('An internal error has occurred.')], 'message' => $exception->getMessage()]);
        }
    }

    /**
     * Method used for adding / deleting a post bookmark.
     *
     * @param UpdatePostBookmarkRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePostBookmark(UpdatePostBookmarkRequest $request)
    {
        $action = $request->get('action');
        $id = $request->get('id');
        $data = [
            'post_id' => $id,
            'user_id' => Auth::user()->id,
        ];
        try {

            // Checking authorization & post existence
            $post = Post::where('id', $id)->first();
            if (!$post) {
                return response()->json(['success' => false, 'errors' => [__('Not found')], 'message'=> __('Post not found')], 404);
            }

            if ($this->canAccessPostPublication($post) && (PostsHelperServiceProvider::hasActiveSub(Auth::user()->id, $post->user->id)
                || Auth::user()->id == $post->user->id
                || PostsHelperServiceProvider::userPaidForPost(Auth::user()->id, $post->id)
                || (!ProfileMonetizationServiceProvider::userHasPaidProfile($post->user) && ListsHelperServiceProvider::loggedUserIsFollowingUser($post->user->id))
                || (ProfileMonetizationServiceProvider::userHasOpenProfile($post->user) && ListsHelperServiceProvider::loggedUserIsFollowingUser($post->user->id))
                || Auth::user()->role_id === 1)
            ) {
                $message = '';
                if ($action == 'add') {
                    $message = 'Bookmark added.';
                    UserBookmark::create($data);
                } elseif ($action == 'remove') {
                    $message = 'Bookmark removed.';
                    UserBookmark::where($data)->first()->delete();
                }

                return response()->json(['success' => true, 'message' => __($message)]);
            }
            else {
                return response()->json(['success' => false, 'errors' => [__('Not authorized')], 'message'=> __('Not authorized')], 403);
            }

        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'errors' => [__('An internal error has occurred.')]]);
        }
    }

    /**
     * Updated the post pin status.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePostPin(Request $request)
    {
        $postID = $request->get('id');
        $action = $request->get('action');
        try {
            // Checking authorization & post existence
            $post = Post::where('id', $postID)->where('user_id', Auth::user()->id)->first();
            if (!$post) {
                return response()->json(['success' => false, 'errors' => [__('Not found')], 'message'=> __('Post not found')], 404);
            }

            // Delete prev pinned post
            $pinnedPost = Post::where('user_id', Auth::user()->id)->where('is_pinned', 1)->first();
            if($pinnedPost) {
                $pinnedPost->is_pinned = false;
                $pinnedPost->save();
            }

            $message = '';
            if ($action == 'add') {
                $message = 'Pin added.';
                $post->is_pinned = true;
                $post->save();
            } elseif ($action == 'remove') {
                $message = 'Pin removed.';
            }

            return response()->json(['success' => true, 'message' => __($message)]);

        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'errors' => [__('An internal error has occurred.').$exception->getMessage()]]);
        }
    }

    /**
     * Method used for deleting a post.
     *
     * @param DeletePostRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deletePost(DeletePostRequest $request)
    {
        $postID = $request->get('id');

        $userPosts = Auth::user()->posts;
        if(getSetting('compliance.minimum_posts_deletion_limit') > 0 && count($userPosts) <= getSetting('compliance.minimum_posts_deletion_limit')) {
            return response()->json(['success' => false, 'errors' => [__('You reached the minimum limit of posts')]]);
        }

        $post = Post::where('id', $postID)->where('user_id', Auth::user()->id)->withCount('postPurchases')->first();

        if(getSetting('compliance.disable_creators_ppv_delete')) {
            if(isset($post->post_purchases_count) && $post->post_purchases_count > 0) {
                return response()->json(['success' => false, 'errors' => [__('The post has been bought and can not be deleted.')]]);
            }
        }

        if ($post) {
            app(\App\Services\Media\AttachmentLifecycleService::class)->deleteParent($post);
            return response()->json(['success' => true, 'message' => __('Post deleted successfully.')]);
        }

        return response()->json(['success' => false, 'errors' => [__('Post not found.')]]);
    }

    /**
     * Deletes post comment.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteComment(Request $request)
    {
        $commentID = (int) $request->get('id');

        $comment = PostComment::with('post')->find($commentID);

        if (!$comment) {
            return response()->json([
                'success' => false,
                'errors' => [__('Not authorized')],
                'message' => __('Comment not found'),
            ], 404);
        }

        $userId = Auth::id();

        $isCommentOwner = $comment->user_id === $userId;

        // Adjust field name if your posts table uses something else (e.g. user_id / author_id)
        $isPostOwner = $comment->post && $comment->post->user_id === $userId;

        if (!($isCommentOwner || $isPostOwner)) {
            return response()->json([
                'success' => false,
                'errors' => [__('Not authorized')],
                'message' => __('Not authorized'),
            ], 403);
        }

        $postID = (int) $comment->post_id;
        $parentID = $comment->parent_id ? (int) $comment->parent_id : null;
        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => __('Comment deleted successfully.'),
            'comments' => PostComment::where('post_id', $postID)->count(),
            'parent_id' => $parentID,
            'thread_replies' => $parentID ? PostComment::where('parent_id', $parentID)->count() : 0,
        ]);
    }

    private function canAccessPostPublication(Post $post): bool
    {
        return PublicationReviewService::canPreview($post, Auth::user())
            || (PublicationReviewService::isApproved($post) && !$post->is_scheduled && (!$post->expire_date || !$post->is_expired));
    }

    /**
     * Validates post access.
     * @param $post
     * @return bool
     */
    private function validateUserAccessForPost($post)
    {
        if (!$this->canAccessPostPublication($post)) return false;

        return PostsHelperServiceProvider::hasActiveSub(Auth::user()->id, $post->user_id)
            || Auth::user()->id == $post->user_id
            || ProfileMonetizationServiceProvider::userHasOpenProfile($post->user)
            || (!ProfileMonetizationServiceProvider::userHasPaidProfile($post->user) && ListsHelperServiceProvider::loggedUserIsFollowingUser($post->user->id))
            // check if logged user is admin
            || Auth::user()->role_id === 1;
    }

    /**
     * Adds user vote to a given poll.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function userPollVote(Request $request)
    {
        $pollID = $request->get('pollID');
        $answerID = $request->get('answerID');

        $poll = Poll::where('id', $pollID)->first();
        $post = $poll->post;
        $answer = PollAnswer::where('id', $answerID)->first();
        $userAnswerInPoll = PollUserAnswer::where('poll_id', $pollID)->where('user_id', Auth::user()->id)->first();

        if(!($poll && $post && $answer)) {
            return response()->json(['success' => false, 'errors' => [__('Not authorized')], 'message' => __('Not authorized')], 403);
        }

        if($userAnswerInPoll) {
            return response()->json(['success' => false, 'errors' => [__('Already voted')], 'message' => __('Already voted')], 403);
        }

        if (!$this->validateUserAccessForPost($post)) {
            return response()->json(['success' => false, 'errors' => [__('Not authorized')], 'message' => __('Not authorized')], 403);
        }

        PollUserAnswer::create([
            'poll_id' => $pollID,
            'user_id' => Auth::user()->id,
            'answer_id' => $answerID,
        ]);

        return response()->json([
            'success' => true,
            'message' => __('Vote added'),
            'html' => View::make('elements.feed.post-box-poll')
                ->with('pollResults', PostsHelperServiceProvider::getPollResults($post->poll))
                ->with('votedAnswer', PostsHelperServiceProvider::hasUserVotedInPoll($post->poll->id))
                ->with('post', $post)
                ->render(),
        ]);

    }
}
