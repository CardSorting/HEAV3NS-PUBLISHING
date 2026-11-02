@if($error)
    <div class="media-moderation-estimate is-error">
        <div class="media-moderation-estimate-kicker">Current estimate</div>
        <p class="media-moderation-estimate-error">{{ $error }}</p>
    </div>
@else
    @php
        $isText = ($estimate['scan_type'] ?? 'media') === \App\Model\MediaModerationRun::TYPE_TEXT;
        $hasSelection = $hasSelection ?? true;
        $needsSelection = !$hasSelection;
        $budgetReduced = $hasSelection && $operationCap < $requestedOperations;
        $noEligibleItems = $hasSelection && $fileCap < 1;
        $hasWarning = $needsSelection || $forceRescan || $budgetShortfall || $budgetReduced || $noEligibleItems;
    @endphp
    <div class="media-moderation-estimate">
        <div class="media-moderation-estimate-header">
            <div>
                <div class="media-moderation-estimate-kicker">Live estimate</div>
                <h3 class="media-moderation-estimate-title">Run estimate</h3>
            </div>
            <span class="media-moderation-estimate-mode">{{ $estimate['mode_label'] }}</span>
        </div>

        <div class="media-moderation-estimate-metrics">
            <div class="media-moderation-estimate-metric">
                <span class="media-moderation-estimate-label">Eligible items</span>
                <strong class="media-moderation-estimate-value">{{ number_format($estimate['items']) }}</strong>
                <span class="media-moderation-estimate-detail">
                    @if($isText)
                        {{ collect($estimate['source_counts'] ?? [])->filter()->map(fn ($count, $source) => number_format($count).' '.str($source)->replace('_', ' '))->implode(' · ') ?: 'No matching text' }}
                    @else
                        {{ number_format($estimate['images']) }} images · {{ number_format($estimate['videos']) }} videos
                        @if($estimate['videos'] > 0)
                            · {{ $estimate['video_processing_label'] }}
                            @if($estimate['video_processing_mode'] !== \App\Settings\ModerationSettings::VIDEO_PROCESSING_SYNC)
                                · {{ number_format($estimate['max_concurrent_video_jobs']) }} at a time
                            @endif
                        @endif
                    @endif
                </span>
            </div>

            <div class="media-moderation-estimate-metric">
                <span class="media-moderation-estimate-label">Items selected</span>
                <strong class="media-moderation-estimate-value">Up to {{ number_format($fileCap) }}</strong>
                <span class="media-moderation-estimate-detail">
                    Limit {{ number_format($requestedItems) }} · worker maximum {{ number_format($estimate['mode_item_limit']) }}
                </span>
            </div>

            <div class="media-moderation-estimate-metric">
                <span class="media-moderation-estimate-label">Estimated provider work</span>
                <strong class="media-moderation-estimate-value">≈ {{ number_format($estimatedRunOperations) }} ops</strong>
                <span class="media-moderation-estimate-detail">Before saved-result reuse</span>
            </div>
        </div>

        <div class="media-moderation-estimate-budget">
            <span class="media-moderation-estimate-budget-label">
                Run cap: <strong>{{ number_format($operationCap) }} ops</strong>
            </span>
            <span class="media-moderation-estimate-budget-item">
                Today: <strong>{{ number_format($estimate['daily_remaining']) }}</strong> left
            </span>
            <span class="media-moderation-estimate-budget-item">
                Month: <strong>{{ number_format($estimate['monthly_remaining']) }}</strong> left
            </span>
        </div>

        @if($needsSelection)
            <div class="media-moderation-estimate-alert">
                <strong>Choose what to scan.</strong>
                Select at least one {{ $isText ? 'text source' : 'content source and media type' }}. The inventory above is only a preview and no provider operations are selected yet.
            </div>
        @endif

        @if($budgetShortfall)
            <div class="media-moderation-estimate-alert">
                <strong>Budget covers {{ number_format($operationCap) }} of about {{ number_format($estimatedRunOperations) }} estimated operations.</strong>
                Uncached items beyond it will pause and can be resumed later.
            </div>
        @endif

        @if($noEligibleItems)
            <div class="media-moderation-estimate-alert">
                <strong>No eligible items match this scope.</strong>
                Adjust the selected sources{{ $isText ? '' : ' or media types' }}. Unchanged items remain excluded unless a fresh provider scan is enabled.
            </div>
        @endif

        @if($forceRescan)
            <div class="media-moderation-estimate-alert">
                <strong>Fresh provider scan enabled.</strong>
                Saved results will be ignored and eligible items will spend provider operations again.
            </div>
        @endif

        @if($budgetReduced)
            <div class="media-moderation-estimate-alert">
                <strong>Requested operation budget reduced.</strong>
                The remaining daily or monthly allowance is lower than the requested {{ number_format($requestedOperations) }} operations.
            </div>
        @endif

        @if(!$hasWarning)
            <p class="media-moderation-estimate-note">
                Saved results are reused when possible, so actual provider usage may be lower than this upper bound.
            </p>
        @endif
    </div>
@endif
