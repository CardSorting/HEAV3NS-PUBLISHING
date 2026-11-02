<?php

namespace App\Http\Controllers;

use App\Events\NewUserMessage;
use App\Http\Requests\SaveNewMessageRequest;
use App\Model\Attachment;
use App\Model\MessageTemplate;
use App\Model\Notification;
use App\Model\Story;
use App\Model\Subscription;
use App\Model\Transaction;
use App\Model\UserMessage;
use App\Providers\AttachmentServiceProvider;
use App\Providers\EmailsServiceProvider;
use App\Providers\GenericHelperServiceProvider;
use App\Providers\ListsHelperServiceProvider;
use App\Providers\NotificationServiceProvider;
use App\Providers\ProfileMonetizationServiceProvider;
use App\Providers\PostsHelperServiceProvider;
use App\Providers\PaymentsServiceProvider;
use App\Providers\StoriesServiceProvider;
use App\Services\Media\AttachmentTranscodingService;
use App\Services\Media\AttachmentPlacementService;
use App\Services\Media\AttachmentLifecycleService;
use App\Services\Media\FileAttachmentService;
use App\Services\UserStaffBadge;
use App\Services\GifService;
use App\Services\MessagePins;
use App\Model\User;
use Carbon\Carbon;
use App\Rules\AllowedHyperlinks;
use App\Rules\PPVMinMax;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Laracasts\Utilities\JavaScript\JavaScriptFacade as JavaScript;
use Pusher\Pusher;
use Ramsey\Uuid\Uuid;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;

class MessengerController extends Controller
{
    /**
     * Renders the main messenger view / layout
     * Rest of the messenger elements are mostly loaded via JS.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        $lastContactID = false;
        $lastContact = $this->fetchContacts(true);
        if ($lastContact) {
            $lastContactID = $lastContact[0]->receiverID == Auth::user()->id ? $lastContact[0]->senderID : $lastContact[0]->receiverID;
        }
        // handles messenger tips
        if(!empty($request->get('tip')) || !empty($request->get('messageUnlock'))) {
            $transaction = Transaction::query()
                ->where('sender_user_id', Auth::user()->id)
                ->whereIn('type', [Transaction::CHAT_TIP_TYPE, Transaction::MESSAGE_UNLOCK])
                ->orderBy('id', 'desc')
                ->first();
            if($transaction) {
                $lastContactID = $transaction->recipient_user_id;
            }
        }

        $availableContacts = $this->getUserSearch($request);

        $followingListID = Auth::user()->lists->firstWhere('type', 'following')->id;
        JavaScript::put([
            'messengerVars' => [
                'userAvatarPath' =>  ($request->getHost() == 'localhost' ? 'http://localhost' : 'https://'.$request->getHost()).$request->getBaseUrl().'/uploads/users/avatars/',
                'lastContactID' => (int) $lastContactID,
                'pusherCluster' => config('broadcasting.connections.pusher.options.cluster'),
                'bootFullMessenger' => true,
                'lockedMessageSVGPath' => asset('/img/post-locked.svg'),
                'defaultAvatarPath' => asset('/img/default-avatar.jpg'),
                'verifiedBadgeHtml' => '<span class="messenger-verified-badge ms-1" data-bs-toggle="tooltip" data-bs-placement="top" title="'.e(__('Verified user')).'">'.view('elements.icon', ['icon' => 'verified', 'centered' => true, 'classes' => '', 'variant' => 'small'])->render().'</span>',
                'staffBadgeHtml' => view('elements.icon', ['icon' => 'staff', 'centered' => true, 'classes' => '', 'variant' => 'small'])->render(),
                'minimumPostsLimit' => getSetting('compliance.minimum_posts_until_creator'),
                'availableContacts' => $availableContacts,
                'followingContacts' => ListsHelperServiceProvider::getListMembers($followingListID),
            ],
            'mediaSettings' => [
                'allowed_file_extensions' => '.'.str_replace(',', ',.', AttachmentServiceProvider::getAllowedUploadExtensions('message')),
                'max_file_upload_size' => (int) getSetting('media.max_file_upload_size'),
                'use_chunked_uploads' => (bool)getSetting('media.use_chunked_uploads'),
                'upload_chunk_size' => (int)getSetting('media.upload_chunk_size'),
                'voice_messages_enabled' => AttachmentServiceProvider::areVoiceMessagesEnabled(),
                'voice_recorder_formats' => AttachmentServiceProvider::getVoiceRecorderFormats(),
                'transcoding_driver' => getSetting('media.transcoding_driver'),
                'enforce_mp4_conversion' => (bool) getSetting('media.enforce_mp4_conversion'),
                'coconut_enforce_mp4_conversion' => (bool) getSetting('media.coconut_enforce_mp4_conversion'),
            ],
            'user' => [
                'username' => Auth::user()->username,
                'user_id' => Auth::user()->id,
                'lists' => [
                    'blocked'=>Auth::user()->lists->firstWhere('type', 'blocked')->id,
                    'following'=> $followingListID,
                ],
                'billingData' => [
                    'first_name' => Auth::user()->first_name,
                    'last_name' => Auth::user()->last_name,
                    'billing_address' => Auth::user()->billing_address,
                    'country' => Auth::user()->country,
                    'city' => Auth::user()->city,
                    'state' => Auth::user()->state,
                    'postcode' => Auth::user()->postcode,
                    'credit' => Auth::user()->wallet->total,
                ],
                'is_mobile' => GenericHelperServiceProvider::isMobileDevice(),
            ],
        ]);

        $unseenMessages = UserMessage::where('receiver_id', Auth::user()->id)->where('isSeen', 0)->count();
        $data = [
            'lastContactID' => $lastContactID,
            'unseenMessages' => $unseenMessages,
            'availableContacts' => $availableContacts,
        ];

        $additionalAssets = ['js' => [], 'css' => []];
        if(getSetting('stories.stories_enabled')){
            $additionalAssets['js'][] = '/js/stories/stories-player.js';
            $additionalAssets['js'][] = '/js/stories/stories-swiper.js';
            $additionalAssets['js'][] = '/libs/swiper/swiper-bundle.min.js';
            $additionalAssets['js'][] = '/js/messenger/messenger-modal-dm.js';
            $additionalAssets['css'][] = '/css/stories.css';
        }
        $data['additionalAssets'] = $additionalAssets;

        return view('pages.messenger', $data);
    }

    /**
     * Method used for fetching available contacts/conversations.
     *
     * @param bool $returnRawContacts
     * @return \Illuminate\Http\JsonResponse|list<object>
     */
    public function fetchContacts(bool $returnRawContacts = false)
    {
        $userID = Auth::user()->id;
        $query = '
        SELECT *
         FROM (
            SELECT
             t1.sender_id as lastMessageSenderID,
             t1.message as lastMessage,
             CASE WHEN t1.gif IS NOT NULL THEN 1 ELSE 0 END as lastMessageHasGif,
             t1.tip_transaction_id as lastMessageTipID,
             t1.isSeen,
             null as created_at, #hack around laravel orm behaviour
             t1.created_at as messageDate,
             senderDetails.id as senderID,
             senderDetails.name as senderName,
             senderDetails.username as senderUsername,
             senderDetails.avatar as senderAvatar,
             senderDetails.role_id as senderRole,
             receiverDetails.id as receiverID,
             receiverDetails.name as receiverName,
             receiverDetails.username as receiverUsername,
             receiverDetails.avatar as receiverAvatar,
             receiverDetails.role_id as receiverRole,
             IF(receiverDetails.id = '.$userID.', senderDetails.id, receiverDetails.id) as contactID
            FROM user_messages AS t1
            INNER JOIN
            (
                SELECT
                    LEAST(receiver_id, sender_id) AS receiverID,
                    GREATEST(receiver_id, sender_id) AS senderID,
                    MAX(id) AS max_id
                FROM user_messages
                GROUP BY
                    LEAST(receiver_id, sender_id),
                    GREATEST(receiver_id, sender_id)
            ) AS t2
                ON LEAST(t1.receiver_id, t1.sender_id) = t2.receiverID AND
                   GREATEST(t1.receiver_id, t1.sender_id) = t2.senderID AND
                   t1.id = t2.max_id
            INNER JOIN users senderDetails ON t1.sender_id = senderDetails.id #AND senderDetails.level <> 3
            INNER JOIN users receiverDetails ON t1.receiver_id = receiverDetails.id #AND receiverDetails.level <> 3
            WHERE  (t1.receiver_id = ? OR t1.sender_id = ?)
                ) as contactsData
                ORDER BY contactsData.messageDate DESC
            ';
        $contacts = DB::select($query, [$userID, $userID]);

        $userIds = collect($contacts)
            ->flatMap(fn ($contact) => [(int) $contact->senderID, (int) $contact->receiverID])
            ->unique()
            ->values()
            ->all();
        $contactUsers = User::query()
            ->with('verification')
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');
        $staffBadges = app(UserStaffBadge::class);
        $staffBadges->preload($contactUsers);

        // Avatar & timestamp altering
        foreach ($contacts as $contact) {
            if($contact->messageDate){
                $contact->created_at = Carbon::createFromTimeStamp(strtotime($contact->messageDate))->diffForHumans(null, Carbon::DIFF_ABSOLUTE, true);
            }
            $contact->senderAvatar = GenericHelperServiceProvider::getStorageAvatarPath($contact->senderAvatar);
            $contact->receiverAvatar = GenericHelperServiceProvider::getStorageAvatarPath($contact->receiverAvatar);
            $sender = $contactUsers->get((int) $contact->senderID);
            $receiver = $contactUsers->get((int) $contact->receiverID);
            $contact->senderVerified = $sender ? GenericHelperServiceProvider::isUserVerified($sender) : false;
            $contact->receiverVerified = $receiver ? GenericHelperServiceProvider::isUserVerified($receiver) : false;
            $contact->senderStaffBadge = $staffBadges->forUser($sender);
            $contact->receiverStaffBadge = $staffBadges->forUser($receiver);
        }

        // Removing blocked contacts
        $contactIds = array_values(array_unique(array_map(fn ($c) => (int)$c->contactID, $contacts)));

        $myBlocked = DB::table('user_lists as l')
            ->join('user_list_members as ulm', 'ulm.list_id', '=', 'l.id')
            ->where('l.type', 'blocked')
            ->where('l.user_id', Auth::user()->id)
            ->whereIn('ulm.user_id', $contactIds)
            ->pluck('ulm.user_id')->all();

        $blockedMe = DB::table('user_lists as l')
            ->join('user_list_members as ulm', 'ulm.list_id', '=', 'l.id')
            ->where('l.type', 'blocked')
            ->whereIn('l.user_id', $contactIds)
            ->where('ulm.user_id', Auth::user()->id)
            ->pluck('l.user_id')->all();

        $myBlockedSet = array_flip($myBlocked);
        $blockedMeSet = array_flip($blockedMe);

        $contacts = array_values(array_filter($contacts, function ($c) use ($myBlockedSet, $blockedMeSet) {
            $cid = (int)$c->contactID;
            return !isset($myBlockedSet[$cid]) && !isset($blockedMeSet[$cid]);
        }));

        // Additional (proper) messenger accces check function, applied to contacts as well
        // TODO: This could use some further improvements
        $contacts = array_filter($contacts, function ($contact) {
            if(self::checkMessengerAccess($contact->senderID, $contact->receiverID) || self::checkMessengerAccess($contact->receiverID, $contact->senderID)){
                return $contact;
            }
        });
        $contacts = array_values($contacts);

        // Filtering unique contactIDs
        // TODO: This could have been done within the initial query - can be inspected for later on, was causing dupe on mass messages
        $filteredContacts = [];
        $uniqueContacts = array_unique(array_map(function ($v) {
            return $v->contactID;
        }, $contacts));
        foreach($uniqueContacts as $uniqueContact){
            foreach($contacts as $contact){
                if($contact->contactID === $uniqueContact){
                    $filteredContacts[] = $contact;
                    break;
                }
            }
        }
        $contacts = $filteredContacts;

        if ($returnRawContacts) {
            return $contacts;
        }

        $searchQuery = mb_strtolower(trim((string) request()->query('query', '')));
        if ($searchQuery !== '') {
            $contacts = array_values(array_filter($contacts, function ($contact) use ($searchQuery, $userID) {
                $isOwnReceiver = (int) $contact->receiverID === (int) $userID;
                $name = $isOwnReceiver ? $contact->senderName : $contact->receiverName;
                $username = $isOwnReceiver ? $contact->senderUsername : $contact->receiverUsername;
                $haystack = mb_strtolower(trim(($name ?? '').' '.($username ?? '')));

                return mb_strpos($haystack, $searchQuery) !== false;
            }));
        }

        $requestedLimit = max(1, min(50, (int) request()->query('limit', 15)));
        $requestedOffset = max(0, (int) request()->query('offset', 0));
        $totalContacts = count($contacts);
        $contacts = array_slice($contacts, $requestedOffset, $requestedLimit);

        return response()->json([
            'status'=>'success',
            'data'=>[
                'contacts' => $contacts,
                'hasMore' => ($requestedOffset + count($contacts)) < $totalContacts,
                'offset' => $requestedOffset,
                'limit' => $requestedLimit,
                'total' => $totalContacts,
            ],
        ]);
    }

    /**
     * Fetches the current user's messenger automation templates.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchMessageTemplates()
    {
        $templates = MessageTemplate::with('attachments')
            ->where('user_id', Auth::id())
            ->whereIn('trigger_type', MessageTemplate::TRIGGER_TYPES)
            ->get()
            ->keyBy('trigger_type');

        $payload = [];
        foreach (MessageTemplate::TRIGGER_TYPES as $triggerType) {
            $template = $templates->get($triggerType);
            $payload[$triggerType] = $template
                ? $this->serializeMessageTemplate($template)
                : $this->emptyMessageTemplatePayload($triggerType);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'templates' => $payload,
            ],
        ]);
    }

    /**
     * Creates or updates a messenger automation template.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveMessageTemplate(Request $request, AttachmentTranscodingService $transcodings)
    {
        $validated = $request->validate([
            'trigger_type' => ['required', Rule::in(MessageTemplate::TRIGGER_TYPES)],
            'enabled' => ['nullable', 'boolean'],
            'message' => ['nullable', 'string', 'max:800', new AllowedHyperlinks()],
            'price' => ['nullable', new PPVMinMax('message')],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['array'],
            'attachments.*.attachmentID' => ['nullable', 'string'],
            'attachments.*.id' => ['nullable', 'string'],
        ]);

        $attachmentIds = collect($request->get('attachments') ?? [])
            ->map(fn ($attachment) => $attachment['attachmentID'] ?? $attachment['id'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->assertCanAssignMessageMedia($attachmentIds, Auth::user());

        $ownedAttachmentCount = Attachment::query()
            ->whereIn('id', $attachmentIds)
            ->where('user_id', Auth::id())
            ->count();

        if ($ownedAttachmentCount !== count($attachmentIds)) {
            return response()->json([
                'success' => false,
                'message' => __('One or more attachments are no longer available. Please remove them and try again.'),
            ], 422);
        }

        if ($transcodings->hasUnavailableAttachments($attachmentIds, (int) Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => __('A video is still processing or needs attention. Wait for it to finish, retry it, or remove it.'),
            ], 422);
        }

        if (($validated['enabled'] ?? false) && !trim((string) ($validated['message'] ?? '')) && !count($attachmentIds)) {
            return response()->json([
                'success' => false,
                'message' => __('Please add a message or attachment before enabling this automation.'),
            ], 422);
        }

        if (
            ($validated['enabled'] ?? false) &&
            (float) ($request->get('price') ?: 0) > 0 &&
            !count($attachmentIds) &&
            !getSetting('compliance.allow_text_only_ppv')
        ) {
            return response()->json([
                'success' => false,
                'message' => __('Please upload at least one file'),
            ], 422);
        }

        $template = DB::transaction(function () use ($validated, $request, $attachmentIds) {
            $template = MessageTemplate::query()->where('user_id', Auth::id())
                ->where('trigger_type', $validated['trigger_type'])->lockForUpdate()->first()
                ?? new MessageTemplate(['user_id' => Auth::id(), 'trigger_type' => $validated['trigger_type']]);
            $template->fill([
                'enabled' => (bool) ($validated['enabled'] ?? false),
                'message' => $validated['message'] ?? null,
                'price' => $request->get('price') ?: 0,
            ]);
            $template->save();
            $this->syncMessageTemplateAttachments($template, $attachmentIds);

            return $template->load('attachments');
        }, 3);

        return response()->json([
            'success' => true,
            'data' => [
                'template' => $this->serializeMessageTemplate($template),
            ],
            'message' => __('Message automation saved.'),
        ]);
    }

    /**
     * Method used for fetching the conversation messages.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetchMessages(Request $request)
    {
        $senderID = Auth::user()->id;
        $receiverIdentifier = (string) $request->route('userID');
        $receiverQuery = function () {
            return User::query()
                ->select(['id', 'name', 'username', 'avatar', 'birthdate', 'email_verified_at', 'role_id'])
                ->with('verification')
                ->withCount('posts');
        };

        $contact = null;
        if (ctype_digit($receiverIdentifier)) {
            $contact = $receiverQuery()->find((int) $receiverIdentifier);
        }
        if (!$contact) {
            $contact = $receiverQuery()->where('username', $receiverIdentifier)->first();
        }
        if (!$contact) {
            return response()->json(['success' => false, 'errors' => [__('Conversation not found')], 'message'=> __('Conversation not found')], 404);
        }

        $receiverID = (int) $contact->id;

        // Checking access
        if(!self::checkMessengerAccess($senderID, $receiverID)){
            return response()->json(['success' => false, 'errors' => [__('Not authorized')], 'message'=> __('Not authorized')], 403);
        }

        if(GenericHelperServiceProvider::hasUserBlocked($receiverID, $senderID)){
            return response()->json(['success' => false, 'errors' => [__('This user has blocked you')], 'message'=> __('This user has blocked you')], 403);
        }

        $requestedLimit = max(1, min(80, (int) $request->query('limit', 30)));
        $beforeId = (int) $request->query('before_id', 0);
        $searchQuery = trim((string) $request->query('query', ''));
        $context = validator($request->query(), ['around_id' => ['nullable', 'integer', 'min:1']])->validate();
        $aroundId = (int) ($context['around_id'] ?? 0);

        $conversationQuery = $this->conversationMessagesQuery($senderID, $receiverID);

        if ($searchQuery !== '') {
            $conversationQuery->where('user_messages.message', 'LIKE', '%'.str_replace(['%', '_'], ['\%', '\_'], $searchQuery).'%');
        }

        if ($beforeId > 0 && !$aroundId) {
            $conversationQuery->where('user_messages.id', '<', $beforeId);
        }

        $hasNewer = false;
        if ($aroundId) {
            // Resolve only inside this authorized conversation; never scan or return its entire history.
            abort_unless((clone $conversationQuery)->where('user_messages.id', $aroundId)->exists(), 404);
            $beforeLimit = intdiv($requestedLimit, 2);
            $afterLimit = $requestedLimit - $beforeLimit;
            $earlier = (clone $conversationQuery)->where('user_messages.id', '<', $aroundId)->limit($beforeLimit + 1)->get();
            $newer = (clone $conversationQuery)->where('user_messages.id', '>=', $aroundId)
                ->reorder('user_messages.id')->limit($afterLimit + 1)->get();
            $hasMore = $earlier->count() > $beforeLimit;
            $hasNewer = $newer->count() > $afterLimit;
            $conversation = $newer->take($afterLimit)->reverse()->concat($earlier->take($beforeLimit));
        } else {
            $conversation = $conversationQuery->limit($requestedLimit + 1)->get();
            $hasMore = $conversation->count() > $requestedLimit;
            $conversation = $conversation->take($requestedLimit);
        }

        app(UserStaffBadge::class)->preload($conversation->flatMap(fn ($message) => [$message->sender, $message->receiver]));

        $conversation = $conversation
            ->reverse()
            ->values()
            ->map(function ($message) {
                $message->setAttribute('hasUserUnlockedMessage', (bool) $message->getAttribute('hasUserUnlockedMessage'));
                $message->sender->setAttribute('profileUrl', route('profile', ['username'=> $message->sender->username]));
                $message->receiver->setAttribute('profileUrl', route('profile', ['username'=> $message->receiver->username]));
                $message = self::cleanUpMessageData($message);
                $message = self::appendStoryReference($message, Auth::user());

                return $message;
            });

        $contactData = [
            'id' => $contact->id,
            'name' => $contact->name,
            'username' => $contact->username,
            'avatar' => $contact->avatar,
            'profileUrl' => route('profile', ['username' => $contact->username]),
            'canEarnMoney' => GenericHelperServiceProvider::creatorCanEarnMoney($contact),
            'verified' => GenericHelperServiceProvider::isUserVerified($contact),
            'staff_badge' => app(UserStaffBadge::class)->forUser($contact),
        ];

        return response()->json([
            'status'=>'success',
            'data'=>[
                'messages' => $conversation,
                'hasMore' => $hasMore,
                'hasNewer' => $hasNewer,
                'pins' => $beforeId > 0 ? null : $this->pinnedMessages($senderID, $receiverID),
                'oldestMessageId' => $conversation->count() ? $conversation->first()->id : null,
                'limit' => $requestedLimit,
                'contact' => $contactData,
            ], ]);
    }

    /** @return Builder<UserMessage> */
    protected function conversationMessagesQuery(int $viewerId, int $contactId): Builder
    {
        $unlockColumn = DB::connection()->getQueryGrammar()->wrap('transactions.id');

        return app(MessagePins::class)->conversation($viewerId, $contactId)
            ->with(['sender', 'receiver', 'attachments', 'story.attachments', 'tipTransaction'])
            ->leftJoin('transactions', function ($join) use ($viewerId) {
                $join->on('transactions.user_message_id', '=', 'user_messages.id')
                    ->where('transactions.sender_user_id', $viewerId)
                    ->where('transactions.type', Transaction::MESSAGE_UNLOCK)
                    ->where('transactions.status', Transaction::APPROVED_STATUS);
            })
            ->select(['user_messages.*', DB::raw('COALESCE('.$unlockColumn.',NULL) as hasUserUnlockedMessage')])
            ->orderByDesc('user_messages.id');
    }

    protected function pinnedMessages(int $viewerId, int $contactId): array
    {
        return $this->conversationMessagesQuery($viewerId, $contactId)->setEagerLoads([])
            ->whereNotNull('pinned_at')->reorder()->orderByDesc('pinned_at')->orderByDesc('user_messages.id')
            ->limit(MessagePins::LIMIT)->get()->map(function (UserMessage $message) use ($viewerId): array {
                $locked = $message->price > 0 && (int) $message->sender_id !== $viewerId && !$message->getAttribute('hasUserUnlockedMessage');
                $text = Str::squish(strip_tags((string) $message->message));
                $preview = $locked ? __('Paid message') : ($message->tip_transaction_id ? __('Tip') :
                    ($text !== '' ? Str::limit($text, 120) : __($message->getAttribute('gif') ? 'GIF' : 'Attachment')));

                return [
                    'id' => $message->id,
                    'pinned_at' => $message->pinned_at?->toISOString(),
                    'pinned_by' => $message->pinned_by,
                    'preview' => $preview,
                ];
            })->all();
    }

    protected function authorizeMessagePins(int $viewerId, int $contactId): void
    {
        abort_unless($viewerId !== $contactId && User::whereKey($contactId)->exists()
            && self::checkMessengerAccess($viewerId, $contactId), 403);
        abort_if(GenericHelperServiceProvider::hasUserBlocked($contactId, $viewerId)
            || GenericHelperServiceProvider::hasUserBlocked($viewerId, $contactId), 403);
    }

    public function fetchPinnedMessages(int $userID)
    {
        $viewerId = (int) Auth::id();
        $this->authorizeMessagePins($viewerId, $userID);

        return response()->json(['pins' => $this->pinnedMessages($viewerId, $userID)]);
    }

    public function setMessagePin(Request $request, int $messageID)
    {
        $data = validator($request->all(), ['pinned' => ['required', 'boolean']])->validate();
        $message = UserMessage::findOrFail($messageID);
        $viewerId = (int) Auth::id();
        abort_if($viewerId !== (int) $message->sender_id && $viewerId !== (int) $message->receiver_id, 403);
        $contactId = (int) $message->sender_id === $viewerId ? (int) $message->receiver_id : (int) $message->sender_id;
        $this->authorizeMessagePins($viewerId, $contactId);
        app(MessagePins::class)->set($message, $viewerId, (bool) $data['pinned']);

        return response()->json(['success' => true]);
    }

    /**
     * Sends the user message
     * Manages the assets
     * Sends the notifications.
     * @param $options
     * @return array
     */
    public function sendUserMessage($options) {

        $gif = app(GifService::class)->validateSelection($options['gif'] ?? null, 'messenger');
        if ($gif !== null && (!empty($options['attachmentIds']) || (float) ($options['messagePrice'] ?? 0) > 0)) {
            throw ValidationException::withMessages([
                'gif' => __('GIFs cannot be combined with uploads or paid messages.'),
            ]);
        }
        if ($gif !== null && !empty($options['sourceTemplate'])) {
            throw ValidationException::withMessages(['gif' => __('GIFs are not supported in message templates.')]);
        }
        if ($gif === null && empty($options['attachmentIds']) && trim((string) ($options['messageValue'] ?? '')) === '') {
            throw ValidationException::withMessages(['message' => __('Please write a message.')]);
        }
        $options['gif'] = $gif;

        $senderID = $options['senderID'];
        $receiverID = $options['receiverID'];
        $senderUser = $options['senderUser'] ?? User::where('id', $senderID)->first();
        if (User::query()->whereIn('id', [$senderID, $receiverID])->get()->contains(fn (User $user): bool => $user->isBanned())) {
            throw ValidationException::withMessages(['account' => __('Messaging is unavailable while this account is suspended.')]);
        }
        $viewerID = (int) ($options['viewerID'] ?? Auth::id() ?? $senderID);
        $this->assertCanAssignMessageMedia(
            $options['attachmentIds'] ?? [],
            $senderUser,
            (float) ($options['messagePrice'] ?? 0) > 0 || ($options['sourceTemplate'] ?? null) instanceof MessageTemplate,
        );

        $hasConversation = UserMessage::where(function ($q) use ($senderID, $receiverID) {
            $q->where('sender_id', $senderID)
                ->where('receiver_id', $receiverID);
        })->orWhere(function ($q) use ($senderID, $receiverID) {
            $q->where('sender_id', $receiverID)
                ->where('receiver_id', $senderID);
        })->exists();

        $isFirstMessage = $hasConversation ? 1 : 0;

        $created = $this->persistMessageWithAttachments($options);
        $message = $this->messageForViewer((int) $created->id, $viewerID);
        $viewer = $viewerID === (int) $senderID ? $senderUser : $message->receiver;
        $payload = self::appendStoryReference(self::cleanUpMessageData($message, $viewerID), $viewer);

        // Template dispatch may be nested in a subscription/follow transaction.
        // Publish only after its outermost transaction has committed.
        DB::afterCommit(function () use ($created, $senderID, $receiverID, $senderUser, $isFirstMessage): void {
            try {
                $this->notifyCommittedMessage((int) $created->id, (int) $senderID, (int) $receiverID, $senderUser, $isFirstMessage === 0);
            } catch (\Throwable $exception) {
                Log::error('A committed message notification could not be delivered.', [
                    'message_id' => $created->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        });

        $return = ['message' => $payload];
        if ($isFirstMessage === 0 && empty($options['skipContactFetch'])) {
            $return['contact'] = $this->fetchContacts(true);
        }

        return $return;
    }

    protected function assertCanAssignMessageMedia(array $attachmentIds, ?User $sender, bool $requiresIdentity = true): void
    {
        if ($requiresIdentity && $attachmentIds !== [] && getSetting('site.enforce_user_identity_checks')
            && (!$sender || !GenericHelperServiceProvider::isUserVerified($sender))) {
            abort(403, __('User not verified. Can not post content.'));
        }
    }

    protected function sendUserMessageBatch(array $receiverIds, array $options): array
    {
        return DB::transaction(function () use ($receiverIds, $options): array {
            $messages = [];
            foreach ($receiverIds as $receiverId) {
                $messages[] = $this->sendUserMessage(array_merge($options, ['receiverID' => (int) $receiverId]));
            }

            return $messages;
        }, 3);
    }

    protected function persistMessageWithAttachments(array $options): UserMessage
    {
        return DB::transaction(function () use ($options): UserMessage {
            $sourceTemplate = $options['sourceTemplate'] ?? null;
            $attributes = [
                'sender_id' => $options['senderID'],
                'receiver_id' => $options['receiverID'],
                'message' => $options['messageValue'] ?? '',
                'price' => $options['messagePrice'],
                'story_id' => $options['story_id'] ?? null,
                'message_template_id' => $sourceTemplate?->id,
            ];
            if (($options['gif'] ?? null) !== null) {
                $attributes['gif'] = $options['gif'];
            }
            $message = UserMessage::create($attributes);
            app(AttachmentPlacementService::class)->sync(
                $message,
                $options['attachmentIds'] ?? [],
                (int) $options['senderID'],
                $sourceTemplate,
            );

            return $message;
        }, 3);
    }

    protected function messageForViewer(int $messageId, int $viewerId): UserMessage
    {
        $message = UserMessage::with(['sender', 'receiver', 'attachments', 'story.attachments', 'tipTransaction'])
            ->where('user_messages.id', $messageId)
            ->where(fn ($query) => $query->where('user_messages.sender_id', $viewerId)->orWhere('user_messages.receiver_id', $viewerId))
            ->leftJoin('transactions', function ($join) use ($viewerId) {
                $join->on('transactions.user_message_id', '=', 'user_messages.id');
                $join->where('transactions.sender_user_id', '=', $viewerId)
                    ->where('transactions.type', Transaction::MESSAGE_UNLOCK)
                    ->where('transactions.status', Transaction::APPROVED_STATUS);
            })
            ->select(['user_messages.*', DB::raw('COALESCE('.DB::connection()->getQueryGrammar()->wrap('transactions.id').',NULL) as hasUserUnlockedMessage')])
            ->firstOrFail();
        $message->setAttribute('hasUserUnlockedMessage', (bool) $message->getAttribute('hasUserUnlockedMessage'));
        $message->sender->setAttribute('profileUrl', route('profile', ['username'=> $message->sender->username]));
        $message->receiver->setAttribute('profileUrl', route('profile', ['username'=> $message->receiver->username]));

        return $message;
    }

    public function broadcastTipMessage(int $messageId, bool $isNewConversation): void
    {
        $record = UserMessage::findOrFail($messageId);
        $senderId = (int) $record->sender_id;
        $receiverId = (int) $record->receiver_id;
        $message = $this->messageForViewer($messageId, $receiverId);
        $receiver = $message->receiver;
        $payload = self::cleanUpMessageData($message, $receiverId);
        broadcast(new NewUserMessage(json_encode($payload), $senderId, $receiverId));

        if ($isNewConversation) {
            NotificationServiceProvider::publishNotification((object) [
                'message' => 'new-messenger-conversation',
                'type' => 'new-messenger-conversation',
                'fromUserID' => $senderId,
            ], $receiver, 'messenger-actions');
        }
    }

    protected function notifyCommittedMessage(int $messageId, int $senderID, int $receiverID, ?User $senderUser, bool $isNewConversation): void
    {
        $message = $this->messageForViewer($messageId, $receiverID);
        $receiver = $message->receiver;

        // Sending the email
        if (
            isset($message->receiver->settings['notification_email_new_message']) &&
            $message->receiver->settings['notification_email_new_message'] === 'true'
        ) {
            $recentExists = Notification::whereNotNull('user_message_id')
                ->where('to_user_id', $receiverID)
                ->where('created_at', '>=', now()->subHours(6))
                ->exists();  // faster than count()

            if (!$recentExists) {
                $payload = [
                    'email'   => $message->receiver->email,
                    'subject' => __('New message received'),
                    'title'   => __('Hello, :name,', ['name' => $message->receiver->name]),
                    'content' => __('Email new message title', ['siteName' => getSetting('site.name')]),
                    'button'  => [
                        'text' => __('View your messages'),
                        'url'  => route('my.messenger.get'),
                    ],
                    // include locales once; do NOT read Auth later
                    'receiver_locale' => $message->receiver->settings['locale'] ?? app()->getLocale(),
                    'sender_locale'   => $senderUser ? ($senderUser->settings['locale'] ?? app()->getLocale()) : app()->getLocale(),
                ];

                // Run after response, no worker needed
                dispatch(function () use ($payload) {
                    App::setLocale($payload['receiver_locale']);
                    EmailsServiceProvider::sendGenericEmail($payload);
                    App::setLocale($payload['sender_locale']);
                })->afterResponse();
            }
        }
        NotificationServiceProvider::createNewUserMessageNotification($message);

        // Cleaning up the message
        $payload = self::appendStoryReference(self::cleanUpMessageData($message, $receiverID), $receiver);

        // Sending the message to the socket
        broadcast(new NewUserMessage(json_encode($payload), $senderID, $receiverID))->toOthers();

        if ($isNewConversation) {
            NotificationServiceProvider::publishNotification(
                (object)[
                    'message' => 'new-messenger-conversation',
                    'type' => 'new-messenger-conversation',
                    'fromUserID' => $senderID,
                ],
                User::where('id', $receiverID)->first(),
                'messenger-actions'
            );
        }
    }

    /**
     * Sends the user message.
     * @param SaveNewMessageRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendMessage(SaveNewMessageRequest $request, AttachmentTranscodingService $transcodings)
    {
        $request->validate([
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['array'],
            'attachments.*.attachmentID' => ['nullable', 'string'],
            'attachments.*.id' => ['nullable', 'string'],
        ]);
        $receiverIDs = array_map('intval', $request->validated('receiverIDs'));
        $isSingleRecipientRequest = count($receiverIDs) === 1;
        $storyID = $request->validated('story_id');

        if ($storyID !== null) {
            $story = Story::query()->find((int) $storyID);
            $storyReplyIsAuthorized = $story
                && StoriesServiceProvider::canViewStory($request->user(), $story)
                && count($receiverIDs) === 1
                && $receiverIDs[0] === (int) $story->user_id;

            if (!$storyReplyIsAuthorized) {
                return response()->json([
                    'success' => false,
                    'errors' => [__('Not authorized')],
                    'message' => __('Not authorized'),
                ], 403);
            }

            $storyID = (int) $story->id;
        }

        $senderID = (int) Auth::id();
        $recipientSelection = $this->resolveMessageRecipients($senderID, $receiverIDs);
        $receiverIDs = $recipientSelection['authorized'];
        $errors = $recipientSelection['errors'];

        if (empty($receiverIDs)) {
            return response()->json([
                'success' => false,
                'errors' => $errors,
                'message' => count($errors) === 1
                    ? $errors[0]
                    : __('None of the selected recipients can receive this message.'),
            ], 403);
        }

        $attachmentsPayload = $request->get('attachments') ?? [];

        $attachmentIds = collect($attachmentsPayload)
            ->map(fn ($v) => $v['attachmentID'] ?? $v['id'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->assertCanAssignMessageMedia($attachmentIds, Auth::user(), (float) $request->get('price') > 0);

        if ($transcodings->hasUnavailableAttachments($attachmentIds, (int) Auth::id())) {
            return response()->json([
                'success' => false,
                'message' => __('A video is still processing or needs attention. Wait for it to finish, retry it, or remove it.'),
            ], 422);
        }

        // Preflight the shared selection once; assignment rechecks it under lock.
        if (!empty($attachmentIds)) {
            $assets = Attachment::whereIn('id', $attachmentIds)
                ->where('user_id', Auth::id())
                ->whereNotNull('vaulted_at')->whereNull('vault_archived_at')
                ->whereNull('sound_id')
                ->get()
                ->keyBy('id'); // handy for quick lookups

            if ($assets->count() !== count($attachmentIds)) {
                return response()->json([
                    'success' => false,
                    'message' => __('One or more attachments are no longer available. Please remove them and try again.'),
                ], 422);
            }
        }

        try {
            $return = $this->sendUserMessageBatch($receiverIDs, [
                'senderID' => $senderID,
                'senderUser' => Auth::user(),
                'messageValue' => $request->get('message'),
                'messagePrice' => $request->get('price'),
                'story_id' => $storyID,
                'attachmentIds' => $attachmentIds,
                'gif' => $request->validated('gif'),
            ]);
        } catch (ValidationException $exception) {
            $errors[] = $exception->validator->errors()->first();

            return response()->json(['success' => false, 'errors' => $errors, 'message' => $errors[array_key_last($errors)]], 422);
        }
        // If single message, return the single message entry | keep ui as it was
        if($isSingleRecipientRequest) $return = $return[0];
        return response()->json([
            'status'=>'success',
            'data'=> $return,
            'errors' => count($errors) ? "Some of your messages couldn't be sent." : false,
        ]);
    }

    /**
     * @param array<int, int> $receiverIDs
     * @return array{authorized: array<int, int>, errors: array<int, string>}
     */
    protected function resolveMessageRecipients(int $senderID, array $receiverIDs): array
    {
        $authorized = [];
        $errors = [];

        foreach ($receiverIDs as $receiverID) {
            $receiverID = (int) $receiverID;
            $error = $this->getMessageRecipientError($senderID, $receiverID);

            if ($error !== null) {
                $errors[] = $error;
                continue;
            }

            $authorized[] = $receiverID;
        }

        return [
            'authorized' => $authorized,
            'errors' => $errors,
        ];
    }

    protected function getMessageRecipientError(int $senderID, int $receiverID): ?string
    {
        if (!self::checkMessengerAccess($senderID, $receiverID)) {
            return __('Not authorized');
        }

        if (GenericHelperServiceProvider::hasUserBlocked($receiverID, $senderID)) {
            return __('This user has blocked you');
        }

        return null;
    }

    /**
     * Marks message as being seen.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markSeen(Request $request)
    {
        $senderID = $request->get('userID');
        $unreadMessages = UserMessage::where('receiver_id', Auth::user()->id)->where('sender_id', $senderID)->where('isSeen', 0)->count();
        UserMessage::where('receiver_id', Auth::user()->id)->where('sender_id', $senderID)->where('isSeen', 0)->update(['isSeen'=>1]);

        return response()->json([
            'status'=>'success',
            'data'=>[
                'count' => $unreadMessages,
            ], ]);
    }

    /**
     * Authorize socket connections.
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

        try {
            $output = [];
            foreach ($request->get('channel_name') as $channelName) {
                $users = explode('-', $channelName);
                $users = array_slice($users, 3, 2);
                $users = array_map('intval', $users);
                if (in_array(Auth::user()->id, $users)) {
                    $auth = $pusher->socket_auth(
                        $channelName,
                        $request->input('socket_id')
                    );
                    $output[$channelName] = ['status'=>200, 'data'=>json_decode($auth)];
                } else {
                    $output[$channelName] = [
                        'code' => '403',
                        'data' => [
                            'errors' => ['Not authorized'],
                        ],
                    ];
                }
            }

            return $output;
        } catch (\Exception $exception) {
            return response()->json([
                'code' => '403',
                'data' => [
                    'errors' => [__($exception->getMessage())],
                ], ], 403);
        }
    }

    /**
     * available users to start a conversation with.
     * @param Request $request
     * @return array
     */
    public function getUserSearch(Request $request)
    {
        $users = GenericHelperServiceProvider::selectizeList(Auth::user()->id);
        $badgeUsers = User::query()
            ->select('id', 'role_id')
            ->whereIn('id', array_column($users, 'id'))
            ->get()
            ->keyBy('id');
        $staffBadges = app(UserStaffBadge::class);
        $staffBadges->preload($badgeUsers);
        $filteredData = [];
        foreach($users as $user){
            $user['staff_badge'] = $staffBadges->forUser($badgeUsers->get((int) $user['id']));
            $filteredData[] = $user;
        }
        return $filteredData;
    }

    /**
     * This method has two purposes
     *  - Remove sensitive data from the UI returned message json
     *  - Reduce websockets (pusher especially) payload.
     * @param $message
     * @return mixed
     */
    public static function cleanUpMessageData(UserMessage $message, ?int $viewerId = null)
    {
        $viewerId ??= Auth::id();
        $tip = $message->tip_transaction_id ? $message->tipTransaction : null;
        $message->setAttribute('tip', $tip
            && (int) $tip->sender_user_id === (int) $message->sender_id
            && (int) $tip->recipient_user_id === (int) $message->receiver_id
            && in_array($viewerId, [(int) $message->sender_id, (int) $message->receiver_id], true)
            ? [
                'amountLabel' => number_format(PaymentsServiceProvider::getTransactionAmountWithTaxesDeducted($tip), 2, '.', ',').' '.strtoupper((string) $tip->currency),
                'status' => $tip->status,
            ] : null);
        $message->unsetRelation('tipTransaction');
        $createdAt = $message->created_at ? $message->created_at->copy() : now();

        $message->setAttribute('dateKey', $createdAt->toDateString());
        $message->setAttribute('timeLabel', $createdAt->format('g:i a'));
        $message->setAttribute('dateAdded', $createdAt->diffForHumans(null, Carbon::DIFF_ABSOLUTE, true));

        if ($createdAt->isToday()) {
            $message->setAttribute('dateLabel', __('Today'));
        } elseif ($createdAt->isYesterday()) {
            $message->setAttribute('dateLabel', __('Yesterday'));
        } else {
            $message->setAttribute('dateLabel', $createdAt->format('M j, Y'));
        }

        $allowedUserFields = [
            'id',
            'username',
            'avatar',
            'name',
            'profileUrl',
            // Add more if needed
        ];

        $senderArray = $message->sender
            ? Arr::only($message->sender->toArray(), $allowedUserFields)
            : [];

        $receiverArray = $message->receiver
            ? Arr::only($message->receiver->toArray(), $allowedUserFields)
            : [];

        $staffBadges = app(UserStaffBadge::class);
        $staffBadges->preload([$message->sender, $message->receiver]);
        $senderArray['staff_badge'] = $staffBadges->forUser($message->sender);
        $receiverArray['staff_badge'] = $staffBadges->forUser($message->receiver);

        // 0) Remove sensitive attachments if message is locked for the current viewer,
        // but keep a safe blurred preview when the site/attachment supports it.
        $senderId = (int) ($message->sender_id ?? ($senderArray['id'] ?? 0));
        $isLockedForViewer = (
            !$message->getAttribute('hasUserUnlockedMessage') &&
            ($message->price && $message->price > 0) &&
            $senderId !== $viewerId
        );

        $message->setAttribute('lockedPreview', $isLockedForViewer ? self::getLockedMessagePreviewData($message) : null);

        if ($isLockedForViewer) {
            $message->setRelation('attachments', collect([]));
            $message->setAttribute('gif', null);
        } else {
            $isParticipant = in_array($viewerId, [(int) $message->sender_id, (int) $message->receiver_id], true);
            $message->setRelation('attachments', $message->attachments->map(function ($attachment) use ($message, $isParticipant) {
                if (!$attachment instanceof Attachment || !FileAttachmentService::isFile($attachment)) return $attachment;

                return $isParticipant ? app(FileAttachmentService::class)->payload($attachment, route('file-attachments.message', [
                    'message' => $message->id, 'attachment' => $attachment->id,
                ])) : null;
            })->filter()->values());
        }

        // 1) Add a custom field (e.g., canEarnMoney) to the sender
        //    First, figure out which user to pass to the helper:
        $canEarnMoney = ($viewerId === $senderId)
            ? GenericHelperServiceProvider::creatorCanEarnMoney($message->receiver)
            : GenericHelperServiceProvider::creatorCanEarnMoney($message->sender);

        // Re-inject that field back into the $senderArray
        $senderArray['canEarnMoney'] = $canEarnMoney;

        // 4) Unset the relationships so Eloquent won't re-serialize them
        //    or try to lazy-load the original data.
        $message->unsetRelation('sender');
        $message->unsetRelation('receiver');

        // 5) Store these arrays back on the Message model as attributes
        $message->setAttribute('sender', $senderArray);
        $message->setAttribute('receiver', $receiverArray);

        // Re-set the sender attribute so it includes canEarnMoney
        $message->setAttribute('sender', $senderArray);

        return $message;
    }

    protected static function appendStoryReference(UserMessage $message, ?User $viewer): UserMessage
    {
        $message->setAttribute('story_ref', null);

        if (!empty($message->story_id)) {
            $story = $message->story;

            if ($story && StoriesServiceProvider::canViewStory($viewer, $story)) {
                $preview = null;

                if ($story->attachments->count()) {
                    $first = $story->attachments->first();
                    $hasThumb = (bool) ($first->has_thumbnail ?? false);
                    $preview = ($hasThumb && !empty($first->thumbnail)) ? (string) $first->thumbnail : null;
                }

                $message->setAttribute('story_ref', [
                    'id' => (int) $message->story_id,
                    'owner_id' => (int) $story->user_id,
                    'preview' => $preview,
                ]);
            }
        }

        $message->unsetRelation('story');

        return $message;
    }

    protected static function getLockedMessagePreviewData(UserMessage $message): array
    {
        $attachments = $message->attachments;
        $attachment = $attachments->count()
            ? $attachments->first()
            : null;

        $hasBlurred = $attachment && AttachmentServiceProvider::hasBlurredPreview($attachment);
        $mediaCounts = self::getMessageAttachmentsTypesCount($attachments);

        return [
            'attachmentExists' => (bool) $attachment,
            'hasBlurred' => (bool) $hasBlurred,
            'mediaCounts' => $mediaCounts,
            'textLength' => array_sum($mediaCounts) === 0 ? mb_strlen((string) $message->message) : 0,
            'preview' => $hasBlurred
                ? ($attachment instanceof Attachment ? $attachment->blurred_preview : null)
                : asset('/img/post-locked.svg'),
        ];
    }

    protected static function getMessageAttachmentsTypesCount($attachments): array
    {
        $counts = [
            'image' => 0,
            'video' => 0,
            'audio' => 0,
            'document' => 0,
            'file' => 0,
        ];

        foreach ($attachments as $attachment) {
            $attachmentType = FileAttachmentService::isFile($attachment) ? 'file' : AttachmentServiceProvider::getAttachmentType($attachment->type);
            if (isset($counts[$attachmentType])) {
                $counts[$attachmentType] += 1;
            }
        }

        return $counts;
    }

    protected function syncMessageTemplateAttachments(MessageTemplate $template, array $attachmentIds): void
    {
        app(AttachmentPlacementService::class)->sync($template, $attachmentIds, (int) $template->user_id);
    }

    protected function serializeMessageTemplate(MessageTemplate $template): array
    {
        $template->loadMissing('attachments.transcoding');

        return [
            'id' => $template->id,
            'trigger_type' => $template->trigger_type,
            'enabled' => (bool) $template->enabled,
            'message' => $template->message,
            'price' => (float) ($template->price ?? 0),
            'attachments' => $template->attachments
                /* @phpstan-var \Illuminate\Database\Eloquent\Collection<int, Attachment> $templateAttachments */
                ->pipe(fn ($templateAttachments) => $templateAttachments)
                ->map(fn ($attachment) => $attachment instanceof Attachment
                    ? app(AttachmentTranscodingService::class)->payload($attachment)
                    : [])
                ->values(),
        ];
    }

    protected function emptyMessageTemplatePayload(string $triggerType): array
    {
        return [
            'id' => null,
            'trigger_type' => $triggerType,
            'enabled' => false,
            'message' => '',
            'price' => 0,
            'attachments' => [],
        ];
    }

    /**
     * Checks messenger access.
     * @param $viewerID
     * @param $contactId
     * @return bool
     */
    public static function checkMessengerAccess($viewerID, $contactId)
    {
        $viewerUser = User::where('id', $viewerID)->first();
        $contactUser = User::where('id', $contactId)->first();
        if ($viewerUser && $contactUser) {
            // Is subscribed to user
            if (PostsHelperServiceProvider::hasActiveSub($viewerUser->id, $contactUser->id)) {
                return true;
            }
            if ($viewerUser->id === $contactUser->id) {
                return true;
            }

            // handles chat access for creators so they can message their subscribers without subscribing back
            if (PostsHelperServiceProvider::hasActiveSub($contactUser->id, $viewerUser->id)) {
                return true;
            }

            // Contacted user has free profile
            if (!ProfileMonetizationServiceProvider::userHasPaidProfile($contactUser) && ListsHelperServiceProvider::isUserFollowing($viewerUser->id, $contactUser->id)) {
                return true;
            }

            // Contacted user has open profile
            if (ProfileMonetizationServiceProvider::userHasOpenProfile($contactUser) && ListsHelperServiceProvider::isUserFollowing($viewerUser->id, $contactUser->id)) {
                return true;
            }

            if ($viewerUser->role_id === 1 || $contactUser->role_id === 1) {
                return true;
            }
            // + If paid creator first created a conversation between him and a open/free profile, set sub = true for the free profile
            if (ProfileMonetizationServiceProvider::userHasFreeProfile($viewerUser) && ProfileMonetizationServiceProvider::userHasPaidProfile($contactUser)) {
                $senderID = $viewerUser->id;
                $receiverID = $contactUser->id;
                $conversation = UserMessage::with(['sender', 'receiver', 'attachments'])->where(function ($q) use ($senderID, $receiverID) {
                    $q->where('sender_id', $senderID)
                        ->where('receiver_id', $receiverID);
                })
                    ->orWhere(
                        function ($q) use ($senderID, $receiverID) {
                            $q->where('receiver_id', $senderID)
                                ->Where('sender_id', $receiverID);
                        }
                    )
                    ->orderBy('created_at', 'asc')
                    ->first();
                if ($conversation && $conversation->sender_id === $contactUser->id) {
                    return true;
                }
            }
            // Handling access when both profiles are either free or open an users have a follow relation from any of them
            if(
                ((ProfileMonetizationServiceProvider::userHasOpenProfile($viewerUser) && ProfileMonetizationServiceProvider::userHasOpenProfile($contactUser)) || (!ProfileMonetizationServiceProvider::userHasPaidProfile($viewerUser) && !ProfileMonetizationServiceProvider::userHasPaidProfile($contactUser)))
                &&
                (
                    ListsHelperServiceProvider::isUserFollowing($viewerID, $contactId) ||
                    ListsHelperServiceProvider::isUserFollowing($contactId, $viewerID)
                )
            ){
                return true;
            }
            // Creator is free/open & wants to message the follower
            if(ProfileMonetizationServiceProvider::userHasFreeProfile($viewerUser) && ListsHelperServiceProvider::isUserFollowing($contactId, $viewerID)){
                return true;
            }

        }
        return false;
    }

    /**
     * Method used for deleting messenger messages.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteMessage(Request $request) {
        $messageID = $request->route('commentID');
        $message = UserMessage::where('id', $messageID)->where('sender_id', Auth::user()->id)->withCount('messagePurchases')->first();
        if(!$message){
            return response()->json(['success' => false, 'errors' => [__('Not authorized')], 'message'=> __('Not authorized')], 403);
        }
        if ($message->tip_transaction_id) {
            return response()->json(['success' => false, 'message' => __('Tip events cannot be edited or deleted.')], 403);
        }
        // Checking if the deleted message is the last one
        $isLastMessage = UserMessage::where(function ($q) use ($message) {
            $q->where('sender_id', Auth::user()->id)
                ->where('receiver_id', $message->receiver_id);
        })->orWhere(
            function ($q) use ($message) {
                $q->where('receiver_id', Auth::user()->id)
                    ->Where('sender_id', $message->receiver_id);
            }
        )->count();

        if(getSetting('compliance.disable_creators_ppv_delete')){
            if($message->message_purchases_count > 0){
                return response()->json(['success' => false, 'message' => __('The message has been bought and can not be deleted.')], 500);
            }
        }

        try {
            app(AttachmentLifecycleService::class)->deleteParent($message);
            return response()->json([
                'status' => 'success',
                'isLastMessage' => $isLastMessage === 1 ? true : false,
            ]);
        } catch (\Exception $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 500);
        }
    }
}
