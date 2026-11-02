<?php

namespace App\Services\Moderation;

use App\Model\Post;
use App\Model\PostComment;
use App\Model\Reel;
use App\Model\ReelComment;
use App\Model\Story;
use App\Model\Stream;
use App\Model\StreamMessage;
use App\Model\TextModerationItem;
use App\Model\User;
use App\Model\UserMessage;
use App\Services\Moderation\Data\TextModerationCandidate;
use Carbon\CarbonInterface;
use DomainException;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class TextModerationSourceService
{
    public const SOURCE_POSTS = 'posts';
    public const SOURCE_REELS = 'reels';
    public const SOURCE_STORIES = 'stories';
    public const SOURCE_POST_COMMENTS = 'post_comments';
    public const SOURCE_REEL_COMMENTS = 'reel_comments';
    public const SOURCE_PROFILE_BIOS = 'profile_bios';
    public const SOURCE_STREAM_TITLES = 'stream_titles';
    public const SOURCE_STREAM_CHAT = 'stream_chat';
    public const SOURCE_MESSAGES = 'messages';

    /**
     * @return array{sources: list<string>, force_rescan: bool}
     */
    public function normalizeScope(array $scope): array
    {
        $sources = array_values(array_intersect(
            array_keys($this->definitions()),
            array_map('strval', Arr::wrap($scope['sources'] ?? [])),
        ));

        return [
            'sources' => $sources,
            'force_rescan' => (bool) ($scope['force_rescan'] ?? false),
        ];
    }

    /**
     * @return Generator<int, TextModerationCandidate>
     */
    public function candidates(
        array $scope,
        string $provider,
        string $modelSignature,
        string $policySignature,
    ): Generator {
        $scope = $this->normalizeScope($scope);

        foreach ($scope['sources'] as $source) {
            $definition = $this->definitions()[$source];
            /** @var Model $model */
            $model = new $definition['model'];
            $table = $model->getTable();
            $columns = array_values(array_unique(array_filter([
                'id',
                $definition['field'],
                $definition['owner_field'],
                'updated_at',
            ])));
            /** @var Builder<Model> $query */
            $query = $definition['model']::query()
                ->select($columns)
                ->whereNotNull($definition['field'])
                ->where($definition['field'], '!=', '')
                ->orderBy($table.'.id');

            if (!$scope['force_rescan']) {
                $query->whereNotExists(function ($query) use (
                    $definition,
                    $table,
                    $provider,
                    $modelSignature,
                    $policySignature,
                ): void {
                    $query->selectRaw('1')
                        ->from('text_moderation_items as prior_scan')
                        ->where('prior_scan.source_type', $definition['source_type'])
                        ->where('prior_scan.source_field', $definition['field'])
                        ->whereColumn('prior_scan.source_id', $table.'.id')
                        ->whereColumn('prior_scan.source_updated_at', $table.'.updated_at')
                        ->where('prior_scan.provider', $provider)
                        ->where('prior_scan.model_signature', $modelSignature)
                        ->where('prior_scan.policy_signature', $policySignature)
                        ->where(function ($query): void {
                            $query->where('prior_scan.scan_status', TextModerationItem::STATUS_COMPLETED)
                                ->orWhere(function ($query): void {
                                    $query->where('prior_scan.scan_status', TextModerationItem::STATUS_FAILED)
                                        ->where('prior_scan.retryable', false);
                                })
                                ->orWhere(function ($query): void {
                                    $query->where('prior_scan.scan_status', TextModerationItem::STATUS_SKIPPED)
                                        ->where(function ($query): void {
                                            $query->whereNull('prior_scan.error_type')
                                                ->orWhere('prior_scan.error_type', '!=', 'cancelled');
                                        });
                                });
                        });
                });
            }

            foreach ($query->lazyById(500, $table.'.id', 'id') as $record) {
                $text = trim((string) $record->getRawOriginal($definition['field']));

                if ($text === '') {
                    continue;
                }

                $recordId = (int) $record->getKey();
                $ownerId = (int) $record->getAttribute($definition['owner_field']);
                $updatedAt = $record->getAttribute('updated_at');

                yield new TextModerationCandidate(
                    sourceType: $definition['source_type'],
                    sourceId: (string) $recordId,
                    sourceField: $definition['field'],
                    ownerUserId: $ownerId > 0 ? $ownerId : null,
                    subjectType: $definition['subject_type'],
                    subjectId: $recordId,
                    text: $text,
                    updatedAt: $updatedAt instanceof CarbonInterface ? $updatedAt : null,
                );
            }
        }
    }

    public function isCurrent(TextModerationItem $item): bool
    {
        $text = $this->currentText($item);

        return $text !== null && hash_equals($item->source_hash, hash('sha256', $text));
    }

    public function currentText(TextModerationItem $item): ?string
    {
        $record = $this->sourceRecord($item);

        if (!$record) {
            return null;
        }

        $text = trim((string) $record->getRawOriginal($item->source_field));

        return $text === '' ? null : $text;
    }

    public function deleteCurrent(TextModerationItem $item): void
    {
        $record = $this->sourceRecord($item);

        if (!$record || !$this->isCurrent($item)) {
            throw new DomainException('This text was edited or removed after it was scanned. Refresh the review item before taking action.');
        }

        if (in_array($item->source_type, [
            TextModerationItem::SOURCE_PROFILE_BIO,
            TextModerationItem::SOURCE_STREAM_TITLE,
        ], true)) {
            $record->update([$item->source_field => null]);

            return;
        }

        $record->delete();
    }

    public function sourceRecord(TextModerationItem $item): ?Model
    {
        $definition = collect($this->definitions())
            ->firstWhere('source_type', $item->source_type);

        if (!$definition) {
            return null;
        }

        return $definition['model']::query()->find($item->source_id);
    }

    /**
     * @return list<string>
     */
    public function defaultSources(): array
    {
        return [
            self::SOURCE_POSTS,
            self::SOURCE_REELS,
            self::SOURCE_STORIES,
            self::SOURCE_POST_COMMENTS,
            self::SOURCE_REEL_COMMENTS,
            self::SOURCE_PROFILE_BIOS,
            self::SOURCE_STREAM_TITLES,
        ];
    }

    /**
     * @return array<string, array{
     *     model: class-string<Model>,
     *     source_type: string,
     *     field: string,
     *     owner_field: string,
     *     subject_type: string
     * }>
     */
    public function definitions(): array
    {
        return [
            self::SOURCE_POSTS => [
                'model' => Post::class,
                'source_type' => TextModerationItem::SOURCE_POST,
                'field' => 'text',
                'owner_field' => 'user_id',
                'subject_type' => 'post',
            ],
            self::SOURCE_REELS => [
                'model' => Reel::class,
                'source_type' => TextModerationItem::SOURCE_REEL,
                'field' => 'caption',
                'owner_field' => 'user_id',
                'subject_type' => 'reel',
            ],
            self::SOURCE_STORIES => [
                'model' => Story::class,
                'source_type' => TextModerationItem::SOURCE_STORY,
                'field' => 'text',
                'owner_field' => 'user_id',
                'subject_type' => 'story',
            ],
            self::SOURCE_POST_COMMENTS => [
                'model' => PostComment::class,
                'source_type' => TextModerationItem::SOURCE_POST_COMMENT,
                'field' => 'message',
                'owner_field' => 'user_id',
                'subject_type' => 'post_comment',
            ],
            self::SOURCE_REEL_COMMENTS => [
                'model' => ReelComment::class,
                'source_type' => TextModerationItem::SOURCE_REEL_COMMENT,
                'field' => 'message',
                'owner_field' => 'user_id',
                'subject_type' => 'reel_comment',
            ],
            self::SOURCE_PROFILE_BIOS => [
                'model' => User::class,
                'source_type' => TextModerationItem::SOURCE_PROFILE_BIO,
                'field' => 'bio',
                'owner_field' => 'id',
                'subject_type' => 'user',
            ],
            self::SOURCE_STREAM_TITLES => [
                'model' => Stream::class,
                'source_type' => TextModerationItem::SOURCE_STREAM_TITLE,
                'field' => 'name',
                'owner_field' => 'user_id',
                'subject_type' => 'stream',
            ],
            self::SOURCE_STREAM_CHAT => [
                'model' => StreamMessage::class,
                'source_type' => TextModerationItem::SOURCE_STREAM_CHAT,
                'field' => 'message',
                'owner_field' => 'user_id',
                'subject_type' => 'stream_message',
            ],
            self::SOURCE_MESSAGES => [
                'model' => UserMessage::class,
                'source_type' => TextModerationItem::SOURCE_MESSAGE,
                'field' => 'message',
                'owner_field' => 'sender_id',
                'subject_type' => 'message',
            ],
        ];
    }
}
