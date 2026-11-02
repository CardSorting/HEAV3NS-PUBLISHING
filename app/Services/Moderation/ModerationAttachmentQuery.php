<?php

namespace App\Services\Moderation;

use App\Model\Attachment;
use App\Model\MediaModerationItem;
use App\Providers\AttachmentServiceProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class ModerationAttachmentQuery
{
    public const SOURCE_POSTS = 'posts';
    public const SOURCE_STORIES = 'stories';
    public const SOURCE_REELS = 'reels';
    public const SOURCE_MESSAGES = 'messages';
    public const SOURCE_STANDALONE = 'standalone';
    public const SOURCE_PROFILE_AVATARS = 'profile_avatars';
    public const SOURCE_PROFILE_COVERS = 'profile_covers';
    public const SOURCE_STREAM_POSTERS = 'stream_posters';

    /**
     * @return array{sources: list<string>, media_types: list<string>, force_rescan: bool}
     */
    public function normalizeScope(array $scope): array
    {
        $allowedSources = [
            self::SOURCE_POSTS,
            self::SOURCE_STORIES,
            self::SOURCE_REELS,
            self::SOURCE_MESSAGES,
            self::SOURCE_STANDALONE,
            self::SOURCE_PROFILE_AVATARS,
            self::SOURCE_PROFILE_COVERS,
            self::SOURCE_STREAM_POSTERS,
        ];
        $sources = array_values(array_intersect(
            $allowedSources,
            array_map('strval', Arr::wrap($scope['sources'] ?? [])),
        ));
        $mediaTypes = array_values(array_intersect(
            ['image', 'video'],
            array_map('strval', Arr::wrap($scope['media_types'] ?? [])),
        ));

        return [
            'sources' => $sources,
            'media_types' => $mediaTypes,
            'force_rescan' => (bool) ($scope['force_rescan'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return Builder<Attachment>
     */
    public function query(
        array $scope,
        string $provider,
        string|array $modelSignatures,
        ?string $policySignature = null,
    ): Builder
    {
        $scope = $this->normalizeScope($scope);
        $attachmentSources = array_values(array_intersect($scope['sources'], [
            self::SOURCE_POSTS,
            self::SOURCE_STORIES,
            self::SOURCE_REELS,
            self::SOURCE_MESSAGES,
            self::SOURCE_STANDALONE,
        ]));
        $extensions = [];

        if (in_array('image', $scope['media_types'], true)) {
            $extensions = array_merge($extensions, AttachmentServiceProvider::getTypeByExtension('images'));
        }

        if (in_array('video', $scope['media_types'], true)) {
            $extensions = array_merge($extensions, AttachmentServiceProvider::getTypeByExtension('video'));
        }

        $query = Attachment::query()
            ->whereIn('type', array_values(array_unique($extensions)))
            ->when($attachmentSources === [], fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->where(function (Builder $query) use ($attachmentSources) {
                foreach ($attachmentSources as $source) {
                    match ($source) {
                        self::SOURCE_POSTS => $query->orWhereHas('placements', fn (Builder $placements) => $placements->whereNotNull('post_id')),
                        self::SOURCE_STORIES => $query->orWhereHas('placements', fn (Builder $placements) => $placements->whereNotNull('story_id')),
                        self::SOURCE_REELS => $query->orWhereHas('placements', fn (Builder $placements) => $placements->whereNotNull('reel_id')),
                        self::SOURCE_MESSAGES => $query->orWhereHas('placements', fn (Builder $placements) => $placements->whereNotNull('message_id')),
                        self::SOURCE_STANDALONE => $query->orWhere(function (Builder $query) {
                            $query->whereDoesntHave('placements')
                                ->where('filename', 'not like', 'payment-request/%')
                                ->where('filename', 'not like', '/payment-request/%')
                                ->whereNull('sound_id');
                        }),
                        default => null,
                    };
                }
            });

        if (!$scope['force_rescan']) {
            $query->whereNotExists(function ($query) use ($provider, $modelSignatures, $policySignature) {
                $query->selectRaw('1')
                    ->from('media_moderation_items as prior_scan')
                    ->whereColumn('prior_scan.attachment_id', 'attachments.id')
                    ->whereColumn('prior_scan.source_updated_at', 'attachments.updated_at')
                    ->where('prior_scan.provider', $provider)
                    ->where(function ($query) {
                        $query->where('prior_scan.scan_status', MediaModerationItem::STATUS_COMPLETED)
                            ->orWhere(function ($query) {
                                $query->where('prior_scan.scan_status', MediaModerationItem::STATUS_FAILED)
                                    ->where('prior_scan.retryable', false);
                            })
                            ->orWhere(function ($query) {
                                $query->where('prior_scan.scan_status', MediaModerationItem::STATUS_SKIPPED)
                                    ->where(function ($query) {
                                        $query->whereNull('prior_scan.error_type')
                                            ->orWhere('prior_scan.error_type', '!=', 'cancelled');
                                    });
                            });
                    })
                    ->whereNotExists(function ($query) {
                        $query->selectRaw('1')
                            ->from('media_moderation_items as newer_scan')
                            ->whereColumn('newer_scan.attachment_id', 'prior_scan.attachment_id')
                            ->whereColumn('newer_scan.source_updated_at', 'prior_scan.source_updated_at')
                            ->whereColumn('newer_scan.provider', 'prior_scan.provider')
                            ->whereColumn('newer_scan.model_signature', 'prior_scan.model_signature')
                            ->whereColumn('newer_scan.id', '>', 'prior_scan.id')
                            ->where('newer_scan.scan_status', MediaModerationItem::STATUS_COMPLETED);
                    });

                if ($policySignature !== null) {
                    $query->where('prior_scan.policy_signature', $policySignature);
                }

                if (is_array($modelSignatures)) {
                    $imageExtensions = AttachmentServiceProvider::getTypeByExtension('images');
                    $videoExtensions = AttachmentServiceProvider::getTypeByExtension('video');
                    $query->where(function ($query) use ($modelSignatures, $imageExtensions, $videoExtensions) {
                        $query->where(function ($query) use ($modelSignatures, $imageExtensions) {
                            $query->whereIn('attachments.type', $imageExtensions)
                                ->where('prior_scan.model_signature', $modelSignatures['image']);
                        })->orWhere(function ($query) use ($modelSignatures, $videoExtensions) {
                            $query->whereIn('attachments.type', $videoExtensions)
                                ->where('prior_scan.model_signature', $modelSignatures['video']);
                        });
                    });
                } else {
                    $query->where('prior_scan.model_signature', $modelSignatures);
                }
            });
        }

        return $query->orderBy('created_at')->orderBy('id');
    }
}
