@php($ai = (array) (Auth::user()->settings['ai'] ?? []))
@php($traits = (array) old('ai.traits', $ai['traits'] ?? []))

<div class="row">
    <div class="col-md-6 mb-3">
        <label for="ai_tone">{{ __('Tone') }}</label>
        @php($tone = old('ai.tone', $ai['tone'] ?? 'neutral'))
        <select class="form-select" id="ai_tone" name="ai[tone]">
            @foreach(['neutral','playful','flirty','classy','bold','mysterious'] as $opt)
                <option value="{{ $opt }}" {{ $tone === $opt ? 'selected' : '' }}>{{ __(ucfirst($opt)) }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-6 mb-3">
        <label for="ai_length">{{ __('Length') }}</label>
        @php($length = old('ai.length', $ai['length'] ?? 'short'))
        <select class="form-select" id="ai_length" name="ai[length]">
            <option value="short" {{ $length === 'short' ? 'selected' : '' }}>{{ __('Short') }}</option>
            <option value="medium" {{ $length === 'medium' ? 'selected' : '' }}>{{ __('Medium') }}</option>
        </select>
    </div>
</div>

<div class="mb-3">
    <label for="ai_traits">{{ __('Traits') }}</label>
    <select class="form-select" id="ai_traits" name="ai[traits][]" multiple>
        @foreach($traits as $t)
            <option value="{{ $t }}" selected>{{ $t }}</option>
        @endforeach
    </select>
    <small class="text-muted d-block mt-2">
        {{ __('Add up to 5 traits (e.g. fitness, cosplay, chill, confident).') }}
    </small>
</div>

<div class="form-check form-switch">
    @php($share = (bool) old('ai.share_profile', $ai['share_profile'] ?? false))
    <input type="checkbox"
           class="form-check-input"
           id="ai_share_profile"
           name="ai[share_profile]"
           value="1"
        {{ $share ? 'checked' : '' }}>
    <label class="form-check-label" for="ai_share_profile">
        {{ __('Share profile info for better suggestions') }}
    </label>
</div>

<small class="text-muted d-block mt-2">
    {{ __('If enabled, we may include some profile details (like gender and age range) to improve AI results.') }}
</small>
