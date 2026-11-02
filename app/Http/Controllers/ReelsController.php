<?php

namespace App\Http\Controllers;

use App\Services\CreatorAccessService;
use App\Http\Requests\SaveReelCommentRequest;
use App\Http\Requests\StoreReelRequest;
use App\Model\Attachment;
use App\Model\AttachmentTranscoding;
use App\Model\Reaction;
use App\Model\Reel;
use App\Model\ReelComment;
use App\Model\ReelView;
use App\Model\Sound;
use App\Model\UserBookmark;
use App\Providers\AttachmentServiceProvider;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\ReelsServiceProvider;
use App\Services\GifService;
use App\Services\Moderation\PublicationReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JavaScript;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Throwable;

class ReelsController extends Controller
{
    private const DEFAULT_REELS_LIMIT = 10;
    private const MAX_REELS_LIMIT = 30;
    private const MAX_EXCLUDED_REEL_IDS = 100;

    public function index()
    {
        if (!getSetting('reels.reels_enabled')) abort(404);

        return view('pages.reels.index');
    }

    public function create()
    {
        if (!getSetting('reels.reels_enabled')) abort(404);

        $creatorAccess = app(CreatorAccessService::class);
        if (!$creatorAccess->canViewCreationPage(Auth::user())) {
            return redirect($creatorAccess->onboardingUrl(Auth::user()))
                ->with('error', $creatorAccess->restrictionMessage(Auth::user()));
        }
        $canPost = $this->canCreateReel();

        JavaScript::put([
            'isAllowedToPost' => $canPost,
            'mediaSettings' => [
                'allowed_file_extensions' => '.'.str_replace(',', ',.', AttachmentServiceProvider::getAllowedUploadExtensions('reel')),
                'max_file_upload_size' => (int) getSetting('media.max_file_upload_size'),
                'transcoding_driver' => getSetting('media.transcoding_driver'),
                'enforce_mp4_conversion' => (bool)getSetting('media.enforce_mp4_conversion'),
                'coconut_enforce_mp4_conversion' => (bool)getSetting('media.coconut_enforce_mp4_conversion'),
                'manual_payments_file_extensions' => '.'.str_replace(',', ',.', AttachmentServiceProvider::filterExtensions('manualPayments')),
                'manual_payments_excel_icon' => asset('/img/excel-preview.svg'),
                'manual_payments_pdf_icon' => asset('/img/pdf-preview.svg'),
                'initUploader' => $canPost,
            ],
            'reels' => [
                'maxVideoLengthSeconds' => (int) getSetting('reels.max_video_length_seconds'),
                'allowSounds' => (bool) getSetting('reels.allow_sounds'),
                'allowPublic' => (bool) getSetting('reels.allow_public_reels'),
            ],
        ]);

        return view('pages.reels.create');
    }

    public function feed(Request $request)
    {
        if (!getSetting('reels.reels_enabled')) abort(404);

        $limit = (int) ($request->get('limit') ?: self::DEFAULT_REELS_LIMIT);
        $limit = max(1, min($limit ?: self::DEFAULT_REELS_LIMIT, self::MAX_REELS_LIMIT));
        $offset = max(0, (int) $request->get('offset', 0));
        $randomSeed = $request->boolean('randomize')
            ? max(1, (int) $request->get('seed', 1))
            : null;
        $prioritizeUnseen = $request->boolean('prioritize_unseen');
        // Feed widgets use this to keep repeated strips fresh without changing explore/profile ordering.
        $excludeIds = collect(explode(',', (string) $request->get('exclude_ids', '')))
            ->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->take(self::MAX_EXCLUDED_REEL_IDS)
            ->values()
            ->all();

        $reels = ReelsServiceProvider::forFeed($request->user(), $limit + 1, $offset, $randomSeed, $prioritizeUnseen, $excludeIds);
        $hasMore = $reels->count() > $limit;
        $reels = $reels->take($limit)->values();
        $initialReelId = (int) $request->get('reel_id');

        if ($initialReelId && $offset === 0) {
            $initialReel = Reel::query()
                ->whereHas('video')
                ->with(ReelsServiceProvider::defaultRelations())
                ->withCount(['views', 'comments', 'reactions', 'bookmarks'])
                ->find($initialReelId);

            if ($initialReel && ReelsServiceProvider::canViewReel($request->user(), $initialReel)) {
                $reels = collect([$initialReel])
                    ->merge($reels->reject(fn (Reel $reel) => (int) $reel->id === (int) $initialReel->id))
                    ->values();
            }
        }

        return response()->json(array_merge(
            ReelsServiceProvider::toFrontendPayload($reels, $request->user()),
            [
                'has_more' => $hasMore,
                'next_offset' => $offset + $reels->count(),
            ]
        ));
    }

    public function show(Request $request, int $reelId)
    {
        if (!getSetting('reels.reels_enabled')) abort(404);

        $reel = Reel::query()
            ->whereHas('video')
            ->with(ReelsServiceProvider::defaultRelations())
            ->withCount(['views', 'comments', 'reactions', 'bookmarks'])
            ->find($reelId);

        if (!$reel || !ReelsServiceProvider::canViewReel($request->user(), $reel)) {
            return response()->view('pages.reels.index', ['initialReelUnavailable' => true], 404);
        }

        return view('pages.reels.index', ['initialReelId' => (int) $reel->id]);
    }

    public function store(StoreReelRequest $request)
    {
        if (!getSetting('reels.reels_enabled')) abort(404);

        if (!$this->canCreateReel()) {
            return $this->identityVerificationRequiredResponse();
        }

        $user = $request->user();
        $data = $request->validated();

        if (!empty($data['is_public']) && !getSetting('reels.allow_public_reels')) {
            return response()->json(['message' => __('Public reels are disabled.')], 422);
        }

        $video = $this->unusedUserAttachment($data['video_attachment_id'], $user->id);
        if (!$video) {
            return response()->json(['message' => __('Please upload one video first.')], 422);
        }

        $video->loadMissing('transcoding');
        if (!$video->transcoding && AttachmentServiceProvider::getAttachmentType($video->type) !== 'video') {
            return response()->json(['message' => __('Please upload one video first.')], 422);
        }

        if ($video->transcoding && $video->transcoding->status !== AttachmentTranscoding::STATUS_COMPLETED) {
            return response()->json(['message' => __('Video is still processing or needs attention.')], 422);
        }

        $maxLength = (int) getSetting('reels.max_video_length_seconds');
        if ($maxLength > 0) {
            $videoLength = $this->resolveVideoLength($video);

            if ($videoLength === null && $this->requiresServerSideVideoLength($video)) {
                return response()->json(['message' => __('Video duration could not be verified.')], 422);
            }

            if ($videoLength !== null && $videoLength > $maxLength) {
                return response()->json(['message' => __('Video is too long.')], 422);
            }
        }

        $cover = null;
        if (!empty($data['cover_attachment_id'])) {
            $cover = $this->unusedUserAttachment($data['cover_attachment_id'], $user->id);
            if (!$cover || AttachmentServiceProvider::getAttachmentType($cover->type) !== 'image') {
                return response()->json(['message' => __('Cover must be an image.')], 422);
            }
        }

        $soundId = $data['sound_id'] ?? null;
        if ($soundId && !getSetting('reels.allow_sounds')) {
            return response()->json(['message' => __('Sounds are disabled.')], 422);
        }

        if ($soundId && !Sound::query()->available()->whereKey($soundId)->exists()) {
            return response()->json(['message' => __('Invalid sound.')], 422);
        }

        $reel = DB::transaction(function () use ($user, $data, $video, $cover, $soundId) {
            $reel = Reel::create([
                'user_id' => (int) $user->id,
                'caption' => $data['caption'] ?? null,
                'is_public' => !empty($data['is_public']),
                'overlay' => $data['overlay'] ?? null,
                'sound_id' => $soundId,
            ]);

            $attachmentIds = [$video->id];
            if ($cover) {
                $attachmentIds[] = $cover->id;
            }

            app(\App\Services\Media\AttachmentPlacementService::class)->sync($reel, $attachmentIds, (int) $user->id);
            app(PublicationReviewService::class)->submit($reel);

            return $reel;
        });
        $reel->refresh();

        return response()->json([
            'success' => true,
            'reel_id' => (int) $reel->id,
            'message' => PublicationReviewService::isApproved($reel) ? __('Reel published.') : __('Submitted for review.'),
            'publication_review' => PublicationReviewService::presentation($reel),
            'redirect_url' => route('reels.get', ['reel_id' => (int) $reel->id]),
        ]);
    }

    public function markView(Request $request)
    {
        if (!getSetting('reels.reels_enabled')) abort(404);

        $data = $request->validate([
            'reel_id' => ['required', 'integer', 'exists:reels,id'],
        ]);

        $reel = $this->findViewableReel($request, (int) $data['reel_id']);
        if (!$reel) {
            return $this->forbiddenResponse();
        }

        if ((int) $reel->user_id !== (int) $request->user()->id) {
            ReelView::updateOrCreate(
                [
                    'reel_id' => (int) $reel->id,
                    'user_id' => (int) $request->user()->id,
                ],
                ['seen_at' => now()]
            );
        }

        return response()->json([
            'success' => true,
            'views' => ReelView::where('reel_id', (int) $reel->id)->count(),
        ]);
    }

    public function comments(Request $request)
    {
        if (!getSetting('reels.reels_enabled')) abort(404);

        $data = $request->validate([
            'reel_id' => ['required', 'integer', 'exists:reels,id'],
        ]);

        $reel = $this->findViewableReel($request, (int) $data['reel_id']);
        if (!$reel) {
            return $this->forbiddenResponse();
        }

        return response()->json(ReelsServiceProvider::commentsPayload($reel, $request->user()));
    }

    public function addComment(SaveReelCommentRequest $request)
    {
        if (!getSetting('reels.reels_enabled')) abort(404);

        $data = $request->validated();
        $reel = $this->findViewableReel($request, (int) $data['reel_id']);
        if (!$reel) {
            return $this->forbiddenResponse();
        }

        $gif = app(GifService::class)->validateSelection($data['gif'] ?? null, 'reel_comments');
        $message = (string) ($data['message'] ?? '');
        if (trim($message) === '' && $gif === null) {
            throw ValidationException::withMessages(['message' => __('Write a message or choose a GIF.')]);
        }

        $comment = DB::transaction(function () use ($request, $data, $reel, $gif, $message) {
            $targetId = $data['reply_to_id'] ?? $data['parent_id'] ?? null;
            $replyTarget = null;
            $rootId = null;

            if ($targetId) {
                $replyTarget = ReelComment::query()
                    ->where('reel_id', (int) $reel->id)
                    ->find((int) $targetId);

                if (!$replyTarget) {
                    throw ValidationException::withMessages(['reply_to_id' => __('Invalid comment.')]);
                }

                $rootId = (int) ($replyTarget->parent_id ?: $replyTarget->id);
                $root = ReelComment::query()
                    ->where('reel_id', (int) $reel->id)
                    ->whereNull('parent_id')
                    ->lockForUpdate()
                    ->find($rootId);
                // Recheck the exact target after locking its thread: deletion may have won the race.
                $replyTarget = ReelComment::query()
                    ->where('reel_id', (int) $reel->id)
                    ->lockForUpdate()
                    ->find((int) $targetId);

                if (!$root || !$replyTarget
                    || (int) ($replyTarget->parent_id ?: $replyTarget->id) !== $rootId
                    || (!empty($data['reply_to_id']) && !empty($data['parent_id']) && (int) $data['parent_id'] !== $rootId)) {
                    throw ValidationException::withMessages(['reply_to_id' => __('Invalid comment.')]);
                }
            }

            return ReelComment::create([
                'reel_id' => (int) $reel->id,
                'user_id' => (int) $request->user()->id,
                'parent_id' => $rootId,
                'reply_to_id' => $replyTarget?->id,
                'message' => $message,
                'gif' => $gif,
            ]);
        });
        $comment->load([
            'user:id,name,username,avatar,birthdate,email_verified_at,role_id',
            'user.verification:id,user_id,status',
        ]);
        $comment->loadCount(['reactions', 'replies']);

        try {
            NotificationServiceProvider::createReelCommentNotifications($comment);
        } catch (Throwable $exception) {
            // A persisted comment must not look like a failed submission and invite duplicates.
            report($exception);
        }

        return response()->json([
            'success' => true,
            'comment_id' => (int) $comment->id,
            'parent_id' => $comment->parent_id ? (int) $comment->parent_id : null,
            'thread_replies' => $comment->parent_id
                ? ReelComment::where('reel_id', (int) $reel->id)->where('parent_id', $comment->parent_id)->count()
                : 0,
            'comment' => ReelsServiceProvider::commentPayload($comment, $request->user(), $reel),
            'comments' => ReelComment::where('reel_id', (int) $reel->id)->count(),
        ]);
    }

    public function commentReplies(Request $request)
    {
        if (!getSetting('reels.reels_enabled')) abort(404);

        $data = $request->validate([
            'reel_id' => ['required', 'integer', 'exists:reels,id'],
            'root_id' => ['required', 'integer'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);

        $reel = $this->findViewableReel($request, (int) $data['reel_id']);
        if (!$reel) {
            return $this->forbiddenResponse();
        }

        $root = ReelComment::query()
            ->where('reel_id', (int) $reel->id)
            ->whereNull('parent_id')
            ->find((int) $data['root_id']);
        if (!$root) {
            return response()->json(['message' => __('Invalid comment.')], 404);
        }

        return response()->json(ReelsServiceProvider::repliesPayload($reel, $root, $request->user(), (int) ($data['offset'] ?? 0)));
    }

    public function deleteComment(Request $request)
    {
        if (!getSetting('reels.reels_enabled')) abort(404);

        $data = $request->validate([
            'comment_id' => ['required', 'integer', 'exists:reel_comments,id'],
        ]);

        $comment = ReelComment::with('reel')->findOrFail((int) $data['comment_id']);
        $userId = (int) $request->user()->id;

        if ((int) $comment->user_id !== $userId && (int) $comment->reel->user_id !== $userId) {
            return $this->forbiddenResponse();
        }

        $reelId = (int) $comment->reel_id;
        $parentId = $comment->parent_id ? (int) $comment->parent_id : null;
        $comment->delete();

        return response()->json([
            'success' => true,
            'comments' => ReelComment::where('reel_id', $reelId)->count(),
            'parent_id' => $parentId,
            'thread_replies' => $parentId
                ? ReelComment::where('reel_id', $reelId)->where('parent_id', $parentId)->count()
                : 0,
        ]);
    }

    public function reaction(Request $request)
    {
        if (!getSetting('reels.reels_enabled')) abort(404);

        $data = $request->validate([
            'type' => ['required', 'in:reel,comment'],
            'action' => ['required', 'in:add,remove'],
            'id' => ['required', 'integer'],
        ]);

        $target = null;
        $reel = null;
        $where = [
            'reaction_type' => Reaction::LIKE_TYPE,
            'user_id' => (int) $request->user()->id,
        ];

        if ($data['type'] === 'reel') {
            $target = Reel::find((int) $data['id']);
            $reel = $target;
            $where['reel_id'] = (int) $data['id'];
        } else {
            $target = ReelComment::with('reel')->find((int) $data['id']);
            $reel = $target ? $target->reel : null;
            $where['reel_comment_id'] = (int) $data['id'];
        }

        if (!$target || !$reel) {
            return response()->json(['message' => __('Not found')], 404);
        }

        if (!ReelsServiceProvider::canViewReel($request->user(), $reel)) {
            return $this->forbiddenResponse();
        }

        if ($data['action'] === 'add') {
            Reaction::firstOrCreate($where);
        } else {
            Reaction::where($where)->delete();
        }

        $countColumn = $data['type'] === 'reel' ? 'reel_id' : 'reel_comment_id';

        return response()->json([
            'success' => true,
            'reactions' => Reaction::where($countColumn, (int) $data['id'])->count(),
        ]);
    }

    public function bookmark(Request $request)
    {
        if (!getSetting('reels.reels_enabled')) abort(404);

        $data = $request->validate([
            'id' => ['required', 'integer', 'exists:reels,id'],
            'action' => ['required', 'in:add,remove'],
        ]);

        $reel = $this->findViewableReel($request, (int) $data['id']);
        if (!$reel) {
            return $this->forbiddenResponse();
        }

        $where = [
            'user_id' => (int) $request->user()->id,
            'reel_id' => (int) $reel->id,
        ];

        if ($data['action'] === 'add') {
            UserBookmark::firstOrCreate($where, ['post_id' => null]);
            $message = __('Reel saved.');
        } else {
            UserBookmark::where($where)->delete();
            $message = __('Reel removed from bookmarks.');
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'bookmarks' => UserBookmark::where('reel_id', (int) $reel->id)->count(),
        ]);
    }

    public function delete(Request $request)
    {
        if (!getSetting('reels.reels_enabled')) abort(404);

        $data = $request->validate([
            'reel_id' => ['required', 'integer', 'exists:reels,id'],
        ]);

        $reel = Reel::where('id', (int) $data['reel_id'])
            ->where('user_id', (int) $request->user()->id)
            ->with('attachments')
            ->first();

        if (!$reel) {
            return $this->forbiddenResponse();
        }

        app(\App\Services\Media\AttachmentLifecycleService::class)->deleteParent($reel);

        return response()->json(['success' => true]);
    }

    protected function canCreateReel(): bool
    {
        return app(CreatorAccessService::class)->canPublish(Auth::user());
    }

    protected function identityVerificationRequiredResponse()
    {
        $message = app(CreatorAccessService::class)->restrictionMessage(Auth::user());

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => ['permissions' => $message],
        ], 403);
    }

    protected function forbiddenResponse()
    {
        return response()->json(['message' => __('Forbidden')], 403);
    }

    protected function findViewableReel(Request $request, int $reelId): ?Reel
    {
        $reel = Reel::find($reelId);

        if (!$reel || !ReelsServiceProvider::canViewReel($request->user(), $reel)) {
            return null;
        }

        return $reel;
    }

    protected function unusedUserAttachment(string $id, int $userId): ?Attachment
    {
        return Attachment::query()
            ->where('id', $id)
            ->where('user_id', $userId)
            ->whereNotNull('vaulted_at')
            ->whereNull('vault_archived_at')
            ->whereNull('sound_id')
            ->first();
    }

    protected function resolveVideoLength(Attachment $video): ?int
    {
        $length = (int) ($video->length ?? 0);
        if ($length > 0) {
            return $length;
        }

        if (!$video->filename) {
            $this->logVideoLengthFailure($video, 'missing_filename');

            return null;
        }

        try {
            $duration = FFMpeg::fromDisk(AttachmentServiceProvider::getStorageProviderName($video->driver))
                ->open($video->filename)
                ->getDriver()
                ->getDurationInSeconds();

            if ($duration && (float) $duration > 0) {
                $length = (int) ceil((float) $duration);
                $video->forceFill(['length' => $length])->save();

                return $length;
            }

            $this->logVideoLengthFailure($video, 'invalid_probe_duration');
        } catch (Throwable $exception) {
            $this->logVideoLengthFailure($video, 'probe_exception', $exception);

            return null;
        }

        return null;
    }

    protected function logVideoLengthFailure(Attachment $video, string $reason, ?Throwable $exception = null): void
    {
        try {
            if (!$this->requiresServerSideVideoLength($video)) {
                return;
            }

            $context = [
                'attachment_id' => $video->getKey(),
                'attachment_driver' => $video->driver,
                'transcoding_driver' => (string) getSetting('media.transcoding_driver'),
                'transcoding_status' => $video->transcoding?->status,
                'reason' => $reason,
            ];

            if ($exception) {
                $message = preg_replace('/\s+/', ' ', $exception->getMessage()) ?: 'No exception message was provided.';
                if ($video->filename) {
                    $message = str_replace((string) $video->filename, '[media-file]', $message);
                }

                $context['exception_type'] = get_class($exception);
                $context['exception_code'] = $exception->getCode();
                $context['exception_message'] = Str::limit($message, 500);
            }

            Log::warning('Reel video duration verification failed.', $context);
        } catch (Throwable $loggingException) {
            // Diagnostics must never replace the validation response with a server error.
        }
    }

    protected function requiresServerSideVideoLength(Attachment $video): bool
    {
        return AttachmentServiceProvider::requiresVerifiedVideoDuration($video);
    }
}
