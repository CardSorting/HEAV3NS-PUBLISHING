@php
    $rows = $profileSocialForm['rows'] ?? [];
    $allowed = $profileSocialForm['allowedPlatforms'] ?? [];
@endphp

<div class="mb-0">

    <div id="social-links-wrapper">
        @forelse($rows as $i => $link)
            <div class="d-flex flex-row mb-2 social-link-row">
                <div class="w-50">
                    <select
                        class="form-select social-platform"
                        name="social_links[{{ $i }}][platform]"
                    >
                        <option value="">{{ __('Select platform') }}</option>

                        @foreach($allowed as $key => $meta)
                            <option
                                value="{{ $key }}"
                                data-base-url="{{ $meta['base_url'] ?? '' }}"
                                @selected(($link['platform'] ?? '') === $key)
                            >
                                {{ $meta['label'] }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="w-50 ps-2 d-flex align-items-center">
                    <input
                        type="url"
                        class="form-control social-value"
                        name="social_links[{{ $i }}][value]"
                        placeholder="{{ __('Paste full URL') }}"
                        value="{{ $link['value'] ?? '' }}"
                    >

                    <div class="ps-2 pt-1">
                        <button
                            type="button"
                            class="social-remove-btn remove-social-link"
                            aria-label="{{ __('Remove') }}"
                        >
                            <ion-icon name="close"></ion-icon>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            @include('elements.settings.social-link-row', [
                'index' => 0,
                'allowed' => $allowed
            ])
        @endforelse
    </div>

    <button
        type="button"
        class="btn btn-outline-primary mt-2 mb-0 btn-sm"
        id="add-social-link"
    >
        {{ __('Add another link') }}
    </button>
</div>

<script type="text/template" id="social-link-template">
    @include('elements.settings.social-link-row', [
        'index' => 0,
        'allowed' => $allowed
    ])
</script>
