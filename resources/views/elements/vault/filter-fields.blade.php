<div class="vault-filter-field">
    <label for="{{ $filterPrefix }}-type" @class(['visually-hidden' => $inline])>{{ __('Media type') }}</label>
    <select id="{{ $filterPrefix }}-type" class="form-select form-select-sm vault-type-filter">
        <option value="all">{{ __('All media') }}</option>
        <option value="image">{{ __('Photos') }}</option>
        <option value="video">{{ __('Videos') }}</option>
        <option value="audio">{{ __('Audio') }}</option>
        <option value="file">{{ __('Files') }}</option>
    </select>
</div>
@if($archiveFilter)
    <div class="vault-filter-field">
        <label for="vault-library-state" @class(['visually-hidden' => $inline])>{{ __('Library') }}</label>
        <select id="vault-library-state" class="form-select form-select-sm vault-archive-filter">
            <option value="0">{{ __('Active') }}</option>
            <option value="1">{{ __('Archived') }}</option>
        </select>
    </div>
@endif
<div class="vault-filter-field">
    <label for="{{ $filterPrefix }}-sort" @class(['visually-hidden' => $inline])>{{ __('Sort by') }}</label>
    <select id="{{ $filterPrefix }}-sort" class="form-select form-select-sm vault-sort-filter">
        <option value="newest">{{ __('Newest first') }}</option>
        <option value="oldest">{{ __('Oldest first') }}</option>
    </select>
</div>
