@php
    // required: $idPrefix e.g. "media" or "text"
    $wrapId   = $idPrefix . '-storySoundWrap';
    $inputId  = $idPrefix . '-storySoundSelect';
    $hiddenId = $idPrefix . '-storySoundId';
    $helpDef  = $idPrefix . '-storySoundHelpDefault';
    $helpVid  = $idPrefix . '-storySoundHelpVideo';
    $helpText = $helpText ?? __('Start typing to search. Select a sound to attach it to this story.');
    $videoUnavailableText = $videoUnavailableText ?? __('Sounds are not available for video stories.');
@endphp

<div class="mt-3 sound-select-theme {{ $classes ?? '' }}" id="{{ $wrapId }}">
    <label class="mb-1">{{ $label ?? __('Sound') }}</label>

    <input type="text"
           id="{{ $inputId }}"
           placeholder="{{ __('Search for a sound…') }}"
           autocomplete="off">

    <div class="mt-1" id="{{ $helpDef }}">
        <p class="mb-2"><small class="form-text text-muted">{{ $helpText }}</small></p>
    </div>

    @if($videoUnavailableText)
        <div class="mt-1 d-none" id="{{ $helpVid }}">
            <p class="mb-2"><small class="form-text text-muted">{{ $videoUnavailableText }}</small></p>
        </div>
    @endif

    <input type="hidden" name="sound_id" id="{{ $hiddenId }}" value="">
</div>
