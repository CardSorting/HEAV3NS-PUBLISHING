<?php

namespace App\Http\Controllers;

use App\Model\Attachment;
use App\Model\AttachmentPlacement;
use App\Providers\AttachmentServiceProvider;
use App\Services\Media\AttachmentLifecycleService;
use App\Services\Media\AttachmentSelectionResolver;
use App\Services\Media\AttachmentTranscodingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use JavaScript;

class VaultController extends Controller
{
    public function index(Request $request)
    {
        $this->assertAccess();
        JavaScript::put(['mediaSettings' => [
            'allowed_file_extensions' => '.'.str_replace(',', ',.', AttachmentServiceProvider::getAllowedUploadExtensions('vault')),
            'max_file_upload_size' => (int) getSetting('media.max_file_upload_size'),
            'use_chunked_uploads' => (bool) getSetting('media.use_chunked_uploads'),
            'upload_chunk_size' => (int) getSetting('media.upload_chunk_size'),
            'transcoding_driver' => getSetting('media.transcoding_driver'),
            'enforce_mp4_conversion' => (bool) getSetting('media.enforce_mp4_conversion'),
            'coconut_enforce_mp4_conversion' => (bool) getSetting('media.coconut_enforce_mp4_conversion'),
        ]]);

        return response()->view('pages.vault')->header('Cache-Control', 'private, no-store');
    }

    public function items(Request $request, AttachmentTranscodingService $transcoding)
    {
        $this->assertAccess();
        $filters = $request->validate([
            'type' => 'nullable|in:all,image,video,audio,file',
            'context' => 'nullable|in:post,message,message-template,story,reel,vault',
            'archived' => 'nullable|boolean',
            'picker' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
            'sort' => 'nullable|in:newest,oldest',
        ]);
        $picker = $request->boolean('picker');
        $query = $this->owned($request)->with('transcoding')->withCount('placements');
        if (!$picker && $request->boolean('archived')) {
            $query->whereNotNull('vault_archived_at');
        } else {
            $query->whereNull('vault_archived_at');
        }
        $type = $filters['type'] ?? 'all';
        if ($picker && in_array($filters['context'] ?? 'vault', ['story', 'reel'], true)) {
            $query->where('is_file_attachment', false);
        }
        if ($type === 'file') {
            $query->where('is_file_attachment', true);
        } elseif ($type !== 'all') {
            $extensions = AttachmentServiceProvider::getTypeByExtension($type);
            if ($type === 'image') $extensions[] = 'gif';
            $query->where('is_file_attachment', false)->whereIn('type', $extensions);
        }
        $direction = ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';
        $attachments = $query->orderBy('vaulted_at', $direction)->orderBy('id', $direction)->paginate(24);
        $payloads = $attachments->getCollection()->mapWithKeys(fn (Attachment $attachment) => [
            (string) $attachment->id => $this->payload($attachment, $transcoding),
        ]);

        return response()->json([
            'html' => view('elements.vault.cards', compact('attachments', 'payloads', 'picker'))->render(),
            'attachments' => $payloads->values(),
            'page' => $attachments->currentPage(),
            'last_page' => $attachments->lastPage(),
            'total' => $attachments->total(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function resolve(Request $request, AttachmentTranscodingService $transcoding)
    {
        $this->assertAccess();
        return app(AttachmentSelectionResolver::class)->resolve($request, $transcoding);
    }

    public function usage(Request $request, string $attachment)
    {
        $this->assertAccess();
        $asset = $this->owned($request)->findOrFail($attachment);
        $placements = $asset->placements()->with(['post', 'message.receiver', 'messageTemplate', 'story', 'reel'])
            ->orderBy('id')->paginate(50);
        $uses = $placements->getCollection()->map(function ($placement) {
            if (!$placement instanceof AttachmentPlacement) {
                return [];
            }
            if ($placement->post_id) return [
                'label' => __('Post').' #'.$placement->post_id,
                'url' => route('posts.edit', ['post_id' => $placement->post_id]),
            ];
            if ($placement->message_id) return [
                'label' => __('Message').' #'.$placement->message_id.($placement->message?->receiver ? ' · @'.$placement->message->receiver->username : ''),
                'url' => $placement->message?->receiver ? route('my.messenger.get', ['conversation' => $placement->message->receiver->username, 'token' => $placement->message_id]) : null,
            ];
            if ($placement->message_template_id) return [
                'label' => __('Message automation').' · '.($placement->messageTemplate?->trigger_type === 'subscription_created' ? __('New subscribers') : __('New followers')),
                'enabled' => (bool) $placement->messageTemplate?->enabled,
                'url' => route('my.messenger.get'),
            ];
            if ($placement->story_id) return [
                'label' => __('Story').' #'.$placement->story_id,
                'url' => route('stories.share', ['story' => $placement->story_id]),
            ];
            return ['label' => __('Reel').' #'.$placement->reel_id, 'url' => route('reels.get', ['reel_id' => $placement->reel_id])];
        });

        return response()->json([
            'uses' => $uses, 'total' => $placements->total(),
            'page' => $placements->currentPage(), 'last_page' => $placements->lastPage(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function archive(Request $request, string $attachment, AttachmentLifecycleService $lifecycle)
    {
        $this->assertAccess();
        $lifecycle->archive($this->owned($request)->findOrFail($attachment), (int) $request->user()->id);
        return response()->json(['success' => true]);
    }

    public function restore(Request $request, string $attachment, AttachmentLifecycleService $lifecycle)
    {
        $this->assertAccess();
        $lifecycle->restore($this->owned($request)->findOrFail($attachment), (int) $request->user()->id);
        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, string $attachment, AttachmentLifecycleService $lifecycle)
    {
        $this->assertAccess();
        $lifecycle->deleteUnused($this->owned($request)->findOrFail($attachment), (int) $request->user()->id);
        return response()->json(['success' => true]);
    }

    /** @return Builder<Attachment> */
    protected function owned(Request $request): Builder
    {
        return Attachment::query()->where('user_id', $request->user()->id)->whereNotNull('vaulted_at');
    }

    protected function assertAccess(): void
    {
        abort_unless(AttachmentServiceProvider::isVaultEnabled(), 404);
    }

    protected function payload(Attachment $attachment, AttachmentTranscodingService $transcoding): array
    {
        return array_merge($transcoding->payload($attachment), [
            'vault_archived_at' => $attachment->vault_archived_at?->toIso8601String(),
            'vaulted_at' => $attachment->vaulted_at?->toIso8601String(),
            'placements_count' => $attachment->getAttribute('placements_count'),
        ]);
    }
}
