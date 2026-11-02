<?php

namespace App\Http\Controllers;

use App\Events\NewStreamChatMessage;
use App\Http\Requests\SaveNewStreamRequest;
use App\Http\Requests\StreamCoverUploadRequest;
use App\Model\Stream;
use App\Model\StreamMessage;
use App\Model\User;
use App\Providers\AttachmentServiceProvider;
use App\Providers\EmailsServiceProvider;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\ListsHelperServiceProvider;
use App\Providers\PostsHelperServiceProvider;
use App\Providers\StreamsServiceProvider;
use App\Services\CreatorAccessService;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use JavaScript;
use Pusher\Pusher;
use Ramsey\Uuid\Uuid;
use View;

class StreamsController extends Controller
{
    private const LIVEKIT_JOIN_TOKEN_TTL = 300;

    /**
     * Streams management endpoint.
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function index(Request $request) {
        $action = false;
        if(getSetting('streams.allow_streams') === 'none') {
            abort(404);
        }
        if($request->get('action')){
            $action = $request->get('action');
        }
        $creatorAccess = app(CreatorAccessService::class);
        $canPublish = $creatorAccess->canPublish($request->user());
        $currentStream = StreamsServiceProvider::getUserInProgressStream();
        $editingExistingStream = $action === 'edit' && $currentStream;
        if (in_array($action, ['create', 'edit'], true) && !$editingExistingStream && !$creatorAccess->canViewCreationPage($request->user())) {
            return redirect($creatorAccess->onboardingUrl($request->user()))
                ->with('error', $creatorAccess->restrictionMessage($request->user()));
        }
        JavaScript::put([
            'isAllowedToPost' => $canPublish,
            'openCreateDialog' => $action == 'create' ? true : false,
            'openEditDialog' => $action == 'edit' ? true : false,
            'openDetailsDialog' => $action == 'details' ? true : false,
            'hasActiveStream' => $currentStream ? true : false,
            'inProgressStreamCover' => $currentStream && asset($currentStream->poster) !== asset('/img/live-stream-cover.svg') ? $currentStream->getOriginal('poster') : '',
            'mediaSettings' => [
                'allowed_file_extensions' => '.'.str_replace(',', ',.', AttachmentServiceProvider::filterExtensions('imagesOnly')),
                'max_file_upload_size' => (int) getSetting('media.max_file_upload_size'),
                'manual_payments_file_extensions' => '.'.str_replace(',', ',.', AttachmentServiceProvider::filterExtensions('manualPayments')),
            ],
            'stream' => ['id' => $currentStream ? $currentStream->id : null],
        ]);
        return view('pages.streams', [
            'activeStream' => StreamsServiceProvider::getUserInProgressStream(),
            'previousStreams' => StreamsServiceProvider::getUserStreams(),
        ]);
    }

    /**
     * Stream actual page.
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getStream(Request $request) {
        $streamID = $request->route('streamID');
        $streamSlug = $request->route('slug');

        $stream = Stream::where('id', $streamID)
            ->where('slug', $streamSlug)
            ->where('status', Stream::IN_PROGRESS_STATUS)
            ->first();

        if (!$stream) {
            abort(404);
        }

        $data = StreamsServiceProvider::determineAccess($stream);
        $data['stream'] = $stream;

        JavaScript::put([
            'streamVars' => [
                'canWatchStream' => $stream->getAttribute('canWatchStream'),
                'streamId' => $stream->id,
                'pusherDebug' => (bool) config('app.debug'),
                'pusherCluster' => config('broadcasting.connections.pusher.options.cluster'),
                'streamOwnerId' => $stream->user_id,
                'streamPoster' => $stream->poster,
                'isLiveStream' => true,
            ],
        ]);

        return view('pages.stream', $data);
    }

    /**
     * Vod page rendering endpoint.
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function getVod(Request $request) {
        $streamID = $request->route('streamID');
        $streamSlug = $request->route('slug');

        $stream = Stream::where('id', $streamID)
            ->where('slug', $streamSlug)
            ->where('status', Stream::ENDED_STATUS)
            ->first();

        if (!$stream) {
            abort(404);
        }

        // Determine access state via shared logic
        $accessResult = StreamsServiceProvider::determineAccess($stream);
        $canWatch = empty($accessResult);

        $stream->setAttribute('canWatchStream', $canWatch);
        $data['stream'] = $stream;
        $data['streamEnded'] = true;

        // If denied, pass lock reasons to the view
        if (!$canWatch) {
            if (!empty($accessResult['subLocked'])) {
                $data['subLocked'] = true;
            }
            if (!empty($accessResult['priceLocked'])) {
                $data['priceLocked'] = true;
            }
        }

        JavaScript::put([
            'streamVars' => [
                'canWatchStream' => $canWatch,
                'streamId' => $stream->id,
                'pusherDebug' => (bool) config('app.debug'),
                'pusherCluster' => config('broadcasting.connections.pusher.options.cluster'),
                'streamOwnerId' => $stream->user_id,
                'isLiveStream' => false,
            ],
        ]);

        return view('pages.stream', $data);
    }

    /**
     * Initiate live streaming by creator.
     * @param SaveNewStreamRequest $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function initStream(SaveNewStreamRequest $request)
    {
        $streamName = $request->get('name');
        $requires_subscription = $request->get('requires_subscription');
        $is_public = $request->get('is_public');
        $price = $request->get('price');
        $poster = $request->get('poster');

        $creatorAccess = app(CreatorAccessService::class);
        if (!$creatorAccess->canPublish($request->user())) {
            return response()->json([
                'success' => false,
                'message' => $creatorAccess->restrictionMessage($request->user()),
            ], 403);
        }

        // TODO: Check type
        // TODO: Init stream accordingly
        // TODO: Return a redirect to the streaming page if using livekit
//        return response()->json(['success' => true]);

        $streaming = StreamsServiceProvider::initiateStreamingByUser(['name' => $streamName, 'requires_subscription' => $requires_subscription, 'is_public' => $is_public, 'price' => $price, 'poster' => $poster]);
        if($streaming['success']){
            $responseData = [
                'success' => true,
                'data' => $streaming['data'],
                'html' => View::make('elements.streams.stream-element')->with('stream', $streaming['data'])->with('isLive', true)->render(),
            ];

            StreamsServiceProvider::sendLiveStreamNotification();

        }
        else{
            $responseData = [
                'success' => false,
                'message' => $streaming['message'],
            ];
        }
        return response()->json($responseData);
    }

    /**
     * (Re)saves stream details when updating.
     * @param SaveNewStreamRequest $request
     * @return array
     */
    public function saveStreamDetails(SaveNewStreamRequest $request) {
        try{
            $stream = Stream::query()
                ->where([
                    'user_id' => Auth::user()->id,
                    'status' => Stream::IN_PROGRESS_STATUS,
                    'id' => $request->get('id'),
                ])
                ->first();

            if (!$stream) {
                return ['success' => false, 'message' => __('Stream not found or not editable')];
            }

            $oldPoster = $stream->getOriginal('poster');

            // Deleting old poster
            if($oldPoster && $oldPoster !== $request->get('poster')){
                $storage = Storage::disk(config('filesystems.defaultFilesystemDriver'));
                $storage->delete($oldPoster);
            }

            $stream->update([
                'name' => $request->get('name'),
                'price' => $request->get('price'),
                'requires_subscription' => $request->get('requires_subscription') == 'true' ? 1 : 0,
                'is_public' => $request->get('is_public') == 'true' ? 1 : 0,
                'poster' => $request->get('poster'),
            ]);
            return ['success' => true, 'message' => __('Stream updated successfully.'), 'data' => ['poster' => $stream->poster]];

        }
        catch (\Exception $exception){
            return ['success' => false, 'message' => $exception->getMessage()];
        }
    }

    /**
     * Stream end endpoint.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function stopStream(Request $request)
    {
        try {
            $stream = StreamsServiceProvider::getUserInProgressStream(false);

            if (!$stream) {
                return response()->json([
                    'success' => false,
                    'message' => __('No active streams available.'),
                ]);
            }

            // Handle Pushr-specific DVR logic only if this is a Pushr stream
            if ($stream->driver === Stream::PUSHR_DRIVER) {
                if (!empty($stream->settings['dvr']) && $stream->pushr_id) {
                    $dvrDetails = StreamsServiceProvider::getPushrStreamingDvr($stream->pushr_id);
                    if ($dvrDetails && isset($dvrDetails[$stream->pushr_id][0]['dvr_url'])) {
                        $stream->vod_link = $dvrDetails[$stream->pushr_id][0]['dvr_url'];
                    }
                }
            }

            $stream->ended_at = Carbon::now();
            $stream->status = Stream::ENDED_STATUS;
            $stream->save();

            return response()->json([
                'success' => true,
                'message' => __('The stream has been queued to be stopped.'),
            ]);
        } catch (\Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Stream delete endpoint.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteStream(Request $request) {
        try {
            $streamId = $request->get('id');
            $stream = Stream::where('user_id', Auth::user()->id)->where('status', Stream::ENDED_STATUS)->where('id', $streamId)->withCount('streamPurchases')->first();

            if(getSetting('compliance.disable_creators_ppv_delete')){
                if($stream->stream_purchases_count > 0){
                    return response()->json(['success' => false, 'message' => __('The stream has been bought and can not be deleted.')]);
                }
            }

            if ($stream) {
                $stream->status = Stream::DELETED_STATUS;
                $stream->save();
            }
            else{
                return response()->json([
                    'success' => false,
                    'message' => __('Stream could not be found.'),
                ]);
            }
            return response()->json([
                'success' => true,
                'message' => __('The stream has been deleted successfully.'),
            ]);
        }
        catch (\Exception $exception){
            return response()->json(['success' => false, 'errors' => [$exception->getMessage()]]);
        }
    }

    /**
     * Pusher init method for stream live counter.
     * @param Request $request
     * @return array|\Illuminate\Http\JsonResponse
     * @throws \Pusher\PusherException
     */
    public function authorizeUser(Request $request)
    {
        $envVars['PUSHER_APP_KEY'] = config('broadcasting.connections.pusher.key');
        $envVars['PUSHER_APP_SECRET'] = config('broadcasting.connections.pusher.secret');
        $envVars['PUSHER_APP_ID'] = config('broadcasting.connections.pusher.app_id');
        $envVars['PUSHER_APP_CLUSTER'] = config('broadcasting.connections.pusher.options.cluster');
        $pusher = new Pusher(
            $envVars['PUSHER_APP_KEY'],
            $envVars['PUSHER_APP_SECRET'],
            $envVars['PUSHER_APP_ID'],
            [
                'cluster' => $envVars['PUSHER_APP_CLUSTER'],
                'encrypted' => true,
            ]
        );

        $out = [];

        try {
            foreach ((array) $request->input('channel_name') as $channel) {
                if (str_starts_with($channel, 'presence-')) {
                    $auth = $pusher->presence_auth($channel, $request->input('socket_id'), (string) auth()->id(), []);
                } elseif (str_starts_with($channel, 'private-')) {
                    $auth = $pusher->socket_auth($channel, $request->input('socket_id'));
                } else {
                    $auth = json_encode(['auth' => true]); // public channel
                }
                $out[$channel] = ['status' => 200, 'data' => json_decode($auth, true)];
            }
            return $out;
        } catch (\Exception $exception) {
            return response()->json([
                'code' => '403',
                'data' => [
                    'errors' => [__($exception->getMessage())],
                ], ]);
        }
    }

    /**
     * Method that adds comments to stream chats.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addComment(Request $request)
    {
        $message = $request->get('message');
        $streamId = $request->get('streamId');

        $stream = Stream::where('id', $streamId)
            ->where('status', Stream::IN_PROGRESS_STATUS)
            ->first();

        if (!$stream) {
            return response()->json(['success' => false, 'message' => __('Invalid stream')], 500);
        }

        // Reuse shared access logic
        $accessResult = StreamsServiceProvider::hasViewerAccess($stream);
        $canWatch = $accessResult === true;

        if (!$canWatch) {
            return response()->json(['success' => false, 'message' => __('Stream access denied')], 403);
        }

        try {
            $message = StreamMessage::create([
                'message'   => $message,
                'stream_id' => $streamId,
                'user_id'   => Auth::user()->id,
            ]);

            $renderedMessage = View::make('elements.streams.stream-chat-message')
                ->with('message', $message)
                ->with('streamOwnerId', $stream->user_id)
                ->render();

            // Broadcast to others
            broadcast(new NewStreamChatMessage($streamId, $renderedMessage, Auth::user()->id))->toOthers();

            $bytes = strlen($renderedMessage);

            return response()->json([
                'status'    => 'success',
                'data'      => $message,
                'dataHtml'  => $renderedMessage,
            ]);

        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Method used for deleting stream messages.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteComment(Request $request) {
        $commentId = $request->get('id');
        $comment = StreamMessage::where('id', $commentId)->with(['stream'])->first();
        if(!$comment){
            return response()->json(['success' => false, 'message' => __('Invalid stream')], 500);
        }
        if($comment->stream->user_id !== Auth::user()->id){
            return response()->json(['success' => false, 'message' => __('Access denied')], 500);
        }
        try {
            $comment->delete();
            return response()->json([
                'status'=>'success',
            ]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }

    /**
     * Method used for uploading stream posters.
     * @param StreamCoverUploadRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function posterUpload(StreamCoverUploadRequest $request) {
        $creatorAccess = app(CreatorAccessService::class);
        abort_unless($creatorAccess->canPublish($request->user()), 403, $creatorAccess->restrictionMessage($request->user()));
        $file = $request->file('file');
        try {
            $directory = 'streams/posters';
            $s3 = Storage::disk(config('filesystems.defaultFilesystemDriver'));
            $fileId = Uuid::uuid4()->getHex();
            $filePath = $directory.'/'.$fileId.'.jpg';
            $img = Image::make($file);
            $coverWidth = 1920;
            $coverHeight = 960;
            $img->fit($coverWidth, $coverHeight)->orientate();
            // Resizing the asset
            $img->encode('jpg', 100);
            // Saving to disk
            $s3->put($filePath, $img, 'public');
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'errors' => ['file'=>$exception->getMessage()]]);
        }
        return response()->json([
            'success' => true,
            'assetSrc' => GenericHelperServiceProvider::getFilePathByActiveStorageDriver($filePath),
            'assetPath' => $filePath,
        ]);
    }

    public function generateToken(Request $request)
    {
        $validated = $request->validate([
            'channel' => ['required', 'string', 'regex:/^stream_[1-9][0-9]*$/D'],
            'identity' => ['required', 'string', 'max:200'],
        ]);
        $user = $request->user();
        if (!$user instanceof User || $user->isBanned()) {
            return response()->json(['success' => false, 'message' => __('Access denied')], 403);
        }

        $channel = $validated['channel'];
        $streamId = (int) substr($channel, strlen('stream_'));
        try {
            $stream = Stream::query()->with('user')->whereKey($streamId)
                ->where('driver', Stream::LIVEKIT_DRIVER)
                ->where('status', Stream::IN_PROGRESS_STATUS)->first();

            if (!$stream || !$stream->user) {
                return response()->json(['success' => false, 'message' => __('Stream could not be found.')], 404);
            }
            if ($stream->user->isBanned()) {
                return response()->json(['success' => false, 'message' => __('Access denied')], 403);
            }

            $isBroadcaster = Str::startsWith($validated['identity'], 'broadcaster');

            if ($isBroadcaster && (int) $stream->user_id !== (int) $user->id) {
                return response()->json(['success' => false, 'message' => __('Access denied')], 403);
            }

            if ($isBroadcaster && !app(CreatorAccessService::class)->canPublish($user)) {
                return response()->json(['success' => false, 'message' => app(CreatorAccessService::class)->restrictionMessage($user)], 403);
            }

            if (!$isBroadcaster && !StreamsServiceProvider::hasViewerAccess($stream)) {
                return response()->json(['success' => false, 'message' => __('Access denied')], 403);
            }

            $apiKey = getSetting('streams.livekit_api_key');
            $apiSecret = getSetting('streams.livekit_api_secret');
            if (!filled($apiKey) || !filled($apiSecret)) {
                return response()->json(['success' => false, 'message' => __('Streaming is not configured.')], 503);
            }

            // The caller selects its role; the server owns the participant identity.
            $identity = $isBroadcaster ? 'broadcaster-'.$user->id : 'viewer-'.$user->id.'-'.Str::random(16);
            $now = now()->timestamp;
            $claims = [
                'iss'      => $apiKey,
                'sub'      => $identity,
                'iat'      => $now,
                'nbf'      => $now,
                // Initial connection only: LiveKit refreshes active-session tokens.
                'exp'      => $now + self::LIVEKIT_JOIN_TOKEN_TTL,
                'video'    => [
                    'room'      => $channel,
                    'roomJoin'  => true,
                    'canSubscribe' => true,
                    'canPublish'=> $isBroadcaster,
                    'canPublishData' => $isBroadcaster,
                ],
                'metadata' => '',
            ];

            $token = JWT::encode($claims, $apiSecret, 'HS256');

            return response()->json([
                'token' => $token,
                'wsUrl' => getSetting('streams.livekit_ws_url'),
            ]);
        }
        catch (\Exception $exception){
            Log::warning('LiveKit access token could not be created.', ['stream_id' => $streamId]);

            return response()->json(['success' => false, 'message' => __('Unable to create a streaming access token.')], 500);
        }

    }

    public function liveKitBroadCast()
    {
        $creatorAccess = app(CreatorAccessService::class);
        abort_unless($creatorAccess->canPublish(Auth::user()), 403, $creatorAccess->restrictionMessage(Auth::user()));
        $stream = StreamsServiceProvider::getUserInProgressStream();

        if(!$stream){abort(404); }

        $accessResult = StreamsServiceProvider::determineAccess($stream);

        JavaScript::put([
            'stream' => ['id' => $stream->id],
            'hasActiveStream' => true,
            'inProgressStreamCover' => asset($stream->poster) !== asset('/img/live-stream-cover.svg') ? $stream->getOriginal('poster') : '',
            'mediaSettings' => [
                'allowed_file_extensions' => '.'.str_replace(',', ',.', AttachmentServiceProvider::filterExtensions('imagesOnly')),
                'max_file_upload_size' => (int) getSetting('media.max_file_upload_size'),
                'manual_payments_file_extensions' => '.'.str_replace(',', ',.', AttachmentServiceProvider::filterExtensions('manualPayments')),
            ],
            'streamVars' => [
                'canWatchStream' => true,
                'streamOwnerId' => $stream->user_id,
                'streamPoster' => $stream->poster,
                'streamId' => $stream->id,
                'pusherDebug' => (bool) config('app.debug'),
                'pusherCluster' => config('broadcasting.connections.pusher.options.cluster'),
            ],
        ]);
        return view('pages.broadcast', ['stream' => $stream]);
    }
}
