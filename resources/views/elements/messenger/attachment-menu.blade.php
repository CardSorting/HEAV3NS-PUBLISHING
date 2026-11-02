<div class="dropdown dropup messenger-attachment-menu">
    <button type="button" id="messenger-attachment-toggle" class="btn btn-outline-primary btn-rounded-icon messenger-button d-inline-flex align-items-center justify-content-center mx-0 mx-md-1" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-haspopup="true" aria-expanded="false" aria-controls="messenger-attachment-options" aria-label="{{ __('Attach file') }}" title="{{ __('Attach file') }}">
        @include('elements.icon', ['icon' => 'add-outline', 'variant' => '', 'centered' => true])
    </button>
    <div id="messenger-attachment-options" class="dropdown-menu" aria-labelledby="messenger-attachment-toggle">
        <button type="button" class="dropdown-item attach-file file-upload-button">
            @include('elements.icon', ['icon' => 'document-outline', 'variant' => 'medium', 'centered' => true])
            <span>{{ __('Attach file') }}</span>
        </button>
        @include('elements.vault.button', ['vaultButtonClass' => 'dropdown-item', 'vaultButtonLabel' => 'Add from vault'])
        <button type="button" class="dropdown-item voice-record-toggle d-none">
            @include('elements.icon', ['icon' => 'mic-outline', 'variant' => 'medium', 'centered' => true])
            <span>{{ __('Record audio') }}</span>
        </button>
    </div>
</div>
