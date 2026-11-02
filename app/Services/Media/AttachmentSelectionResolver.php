<?php

namespace App\Services\Media;

use App\Model\Attachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttachmentSelectionResolver
{
    /** Resolve known draft IDs, without exposing an enumerable media library. */
    public function resolve(Request $request, AttachmentTranscodingService $transcoding): JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|max:100',
            'ids.*' => ['required', 'string', 'max:36', 'distinct', 'regex:/\A(?:[a-f0-9]{32}|[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})\z/i'],
        ]);
        $attachments = Attachment::query()->where('user_id', $request->user()->id)
            ->whereNotNull('vaulted_at')->whereIn('id', $data['ids'])->with('transcoding')->get()->keyBy('id');

        // Saved selections may contain archived media; neither persisted URLs nor ownership are trusted.
        $payloads = collect($data['ids'])->filter(fn ($id) => $attachments->has($id))
            ->map(function ($id) use ($attachments, $transcoding) {
                $attachment = $attachments->get($id);
                return array_merge($transcoding->payload($attachment), [
                    'vault_archived_at' => $attachment->vault_archived_at?->toIso8601String(),
                    'vaulted_at' => $attachment->vaulted_at?->toIso8601String(),
                    'placements_count' => $attachment->getAttribute('placements_count'),
                ]);
            })->values();

        return response()->json(['attachments' => $payloads])->header('Cache-Control', 'private, no-store');
    }
}
