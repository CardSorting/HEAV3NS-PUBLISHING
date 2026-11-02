<?php

namespace App\Http\Controllers;

use App\Services\CreatorAccessService;
use App\Http\Requests\StoreStoryRequest;
use App\Model\Attachment;
use App\Model\Sound;
use App\Model\Story;
use App\Model\StoryView;
use App\Model\User;
use App\Providers\AttachmentServiceProvider;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\StoriesServiceProvider;
use App\Model\AttachmentTranscoding;
use App\Services\Moderation\PublicationReviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use JavaScript;

class StoriesController extends Controller
{
    /**
     * Main story create layout.
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Http\RedirectResponse
     */
    public function create()
    {
        if (!getSetting('stories.stories_enabled')) abort(404);

        $creatorAccess = app(CreatorAccessService::class);
        if (!$creatorAccess->canViewCreationPage(Auth::user())) {
            return redirect($creatorAccess->onboardingUrl(Auth::user()))
                ->with('error', $creatorAccess->restrictionMessage(Auth::user()));
        }
        $canPost = $creatorAccess->canPublish(Auth::user());

        JavaScript::put(
            [
                'isAllowedToPost' => $canPost,
                'mediaSettings' => [
                    'allowed_file_extensions' => '.'.str_replace(',', ',.', AttachmentServiceProvider::getAllowedUploadExtensions('story')),
                    'max_file_upload_size' => (int) getSetting('media.max_file_upload_size'),
                    'transcoding_driver' => getSetting('media.transcoding_driver'),
                    'enforce_mp4_conversion' => (bool)getSetting('media.enforce_mp4_conversion'),
                    'coconut_enforce_mp4_conversion' => (bool)getSetting('media.coconut_enforce_mp4_conversion'),
                    'manual_payments_file_extensions' => '.'.str_replace(',', ',.', AttachmentServiceProvider::filterExtensions('manualPayments')),
                    'manual_payments_excel_icon' => asset('/img/excel-preview.svg'),
                    'manual_payments_pdf_icon' => asset('/img/pdf-preview.svg'),
                    'initUploader' => $canPost,
                ],
            ]
        );

        return view('pages.stories.create', []);
    }

    /**
     * User for feed stories.
     * @param StoreStoryRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function feed(Request $request)
    {
        if (!getSetting('stories.stories_enabled')) abort(404);

        $viewer = $request->user();
        $stories = StoriesServiceProvider::forFeed($viewer); // stays User $viewer
        $payload = StoriesServiceProvider::toFrontendPayload($stories);

        return response()->json($payload);
    }

    /**
     * Used for feed/individual user stories.
     * @param StoreStoryRequest $request
     * @param string $username
     * @return \Illuminate\Http\JsonResponse
     */
    public function profile(Request $request, string $username)
    {
        if (!getSetting('stories.stories_enabled')) abort(404);

        $viewer = $request->user(); // can be null

        $owner = User::query()
            ->where('username', $username)
            ->firstOrFail();

        $stories = StoriesServiceProvider::forProfile($viewer, $owner); // provider must accept ?User
        $payload = StoriesServiceProvider::toFrontendPayload($stories);

        return response()->json($payload);
    }

    /**
     * Profile page highligts/pinned stories.
     * @param Request $request
     * @param string $username
     * @return \Illuminate\Http\JsonResponse
     */
    public function highlights(Request $request, string $username)
    {
        if (!getSetting('stories.stories_enabled')) abort(404);

        $viewer = $request->user(); // may be null

        $owner = User::query()
            ->where('username', $username)
            ->firstOrFail();

        $stories = StoriesServiceProvider::forHighlights($viewer, $owner);
        $payload = StoriesServiceProvider::toFrontendPayload($stories);

        return response()->json($payload);
    }

    /**
     * Messenger individual story on-demand.
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function payload($id)
    {
        $viewer = Auth::user();

        $story = Story::query()
            ->with([
                'user:id,name,username,avatar,role_id',
                'attachments',
                'sound.attachments',
            ])
            ->findOrFail((int)$id);

        if (!StoriesServiceProvider::canViewStory($viewer, $story)) {
            return response()->json(['success' => false, 'message' => __('Unauthorized')], 403);
        }

        $payload = StoriesServiceProvider::toFrontendPayload(collect([$story]));

        // payload is: { stories: [ bubble ] }
        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    /**
     * Saves the actual story.
     * @param StoreStoryRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreStoryRequest $request)
    {
        if (!getSetting('stories.stories_enabled')) abort(404);

        $creatorAccess = app(CreatorAccessService::class);
        if (!$creatorAccess->canPublish($request->user())) {
            $message = $creatorAccess->restrictionMessage($request->user());
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => ['permissions' => $message],
            ], 403);
        }

        $user = $request->user();

        $data = $request->validated();

        // Normalize / safety
        $linkUrl = trim((string)($data['link_url'] ?? ''));
        if ($linkUrl !== '' && !preg_match('~^https?://~i', $linkUrl)) {
            return response()->json(['message' => __('Invalid link URL')], 422);
        }

        // If link_text provided without link_url -> ignore text
        $linkText = trim((string)($data['link_text'] ?? ''));
        if ($linkUrl === '') {
            $linkUrl = null;
            $linkText = null;
        }

        // If url exists but no label, set default
        if ($linkUrl && $linkText === '') {
            $linkText = __('Learn more');
        }

        // Mode guards
        if ($data['mode'] === 'media' && empty($data['attachmentID'])) {
            abort(422, __('Please upload a photo or video first.'));
        }
        if ($data['mode'] === 'text' && empty(trim((string)($data['text'] ?? '')))) {
            abort(422, __('Please write something first.'));
        }

        // Optional: enforce preset only for text mode
        if ($data['mode'] === 'text' && empty($data['bg_preset'])) {
            $data['bg_preset'] = 'solid_black';
        }

        $soundId = $data['sound_id'] ?? null;

        if ($soundId) {
            $isActive = Sound::query()->available()->whereKey($soundId)->exists();
            if (!$isActive) {
                return response()->json(['message' => __('Invalid sound.')], 422);
            }
        }

        $story = DB::transaction(function () use ($user, $data, $linkUrl, $linkText, $soundId) {

            $expiryHours = (int) getSetting('stories.story_expires_hours');
            if ($expiryHours <= 0) {
                $expiryHours = 24; // safe fallback
            }

            $story = Story::create([
                'user_id'     => $user->id,
                'expires_at'  => now()->addHours($expiryHours),
                'is_highlight'=> false,
                'is_public'   => !empty($data['is_public']) ? 1 : 0,

                'mode'        => $data['mode'],
                'text'        => $data['text'] ?? null,
                'overlay' => [
                    'x' => (float) ($data['overlay_x']),
                    'y' => (float) ($data['overlay_y']),
                ],
                'bg_preset'   => $data['mode'] === 'text' ? ($data['bg_preset'] ?? 'solid_black') : null,
                'link_url'   => $linkUrl,
                'link_text'  => $linkText,
                'sound_id' => $soundId,
            ]);

            // Link attachment only in media mode
            if ($data['mode'] === 'media') {
                app(\App\Services\Media\AttachmentPlacementService::class)->sync($story, [$data['attachmentID']], (int) $user->id);
            }

            app(PublicationReviewService::class)->submit($story);

            return $story;
        });
        $story->refresh();

        return response()->json([
            'success' => true,
            'story_id' => $story->id,
            'message' => PublicationReviewService::isApproved($story) ? __('Your story published.') : __('Submitted for review.'),
            'publication_review' => PublicationReviewService::presentation($story),
            'redirect_url' => route('feed'),
        ]);
    }

    public function delete(Request $request)
    {
        if (!getSetting('stories.stories_enabled')) abort(404);
        $user = $request->user();

        $data = $request->validate([
            'story_id' => ['required', 'exists:stories,id'],
        ]);

        $story = Story::where('id', $data['story_id'])
            ->where('user_id', $user->id)
            ->first();

        if (!$story) {
            abort(403, __('Unauthorized.'));
        }

        app(\App\Services\Media\AttachmentLifecycleService::class)->deleteParent($story);

        return response()->json([
            'success' => true,
            'message' => __('Story deleted'),
        ]);
    }

    public function pinToggle(Request $request)
    {
        if (!(bool) getSetting('stories.allow_highlights')) {
            abort(403);
        }

        if (!getSetting('stories.stories_enabled')) abort(404);
        $user = $request->user();

        $data = $request->validate([
            'story_id' => ['required', 'exists:stories,id'],
        ]);

        $story = Story::where('id', $data['story_id'])
            ->where('user_id', $user->id)
            ->first();

        if (!$story) {
            abort(403, __('Unauthorized.'));
        }

        $story->is_highlight = !(bool) $story->is_highlight;
        $story->save();

        return response()->json([
            'success' => true,
            'pinned'  => (bool) $story->is_highlight,
            'message' => $story->is_highlight
                ? __('Story pinned')
                : __('Story unpinned'),
        ]);
    }

    public function share(Request $request, int $storyId)
    {
        if (!getSetting('stories.stories_enabled')) abort(404);

        $viewer = $request->user(); // may be null

        $story = Story::query()
            ->active()
            ->with('user:id,username')
            ->find($storyId);

        if (!$story) {
            abort(404);
        }

        if (!StoriesServiceProvider::canViewStory($viewer, $story)) {
            abort(404);
        }

        // Redirect to owner's profile, keeping story id in query
        return redirect()->to(app()->make('url')->to('/'.$story->user->username).'?story='.(int) $storyId);
    }

    public function view(Request $request)
    {
        if (!getSetting('stories.stories_enabled')) abort(404);
        $viewer = Auth::user();

        $storyId = (int) $request->input('story_id');
        if (!$storyId) {
            return response()->json(['message' => __('Missing story_id')], 422);
        }

        // Find story (active only; adjust if your "active()" scope already exists)
        $story = Story::query()
            ->with('user:id')
            ->active()
            ->find($storyId);

        if (!$story) {
            return response()->json(['message' => __('Story not available')], 404);
        }

        // Access control (defense in depth)
        if (!StoriesServiceProvider::canViewStory($viewer, $story)) {
            return response()->json(['message' => __('Forbidden')], 403);
        }

        // Owner viewing their own story usually shouldn't count as a "view"
        $isOwner = ((int) $story->user_id === (int) $viewer->id);

        if (!$isOwner) {
            StoryView::updateOrCreate(
                [
                    'story_id' => (int) $story->id,
                    'user_id'  => (int) $viewer->id,
                ],
                [
                    'seen_at'  => now(),
                ]
            );
        }

        // Total views (unique viewers)
        $views = StoryView::query()
            ->where('story_id', (int) $story->id)
            ->count();

        return response()->json([
            'success' => true,
            'story_id' => (int) $story->id,
            'seen' => true,
            'views' => (int) $views,
            'owner' => $isOwner,
        ]);
    }
}
