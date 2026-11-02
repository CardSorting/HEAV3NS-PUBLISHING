<div class="d-flex flex-row mb-2 social-link-row">
    <div class="w-50">
        <select
            class="form-select social-platform"
            name="social_links[{{ $index }}][platform]"
        >
            <option value="">{{ __('Select platform') }}</option>

            @foreach($allowed as $key => $meta)
                <option
                    value="{{ $key }}"
                    data-base-url="{{ $meta['base_url'] ?? '' }}"
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
            name="social_links[{{ $index }}][value]"
            placeholder="{{ __('Paste full URL') }}"
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
