@php
    $files = is_array($record?->files)
        ? $record->files
        : (is_string($record?->files) ? json_decode($record->files, true) : []);
    $files = is_array($files)
        ? array_values(array_filter($files, fn ($file) => is_string($file) && $file !== ''))
        : [];
    $reviewedVerification = $record instanceof \App\Model\UserVerify
        && in_array($record->status, [\App\Model\UserVerify::APPROVED_STATUS, \App\Model\UserVerify::REJECTED_STATUS], true);
@endphp

<div class="admin-document-previews">
    <div class="admin-document-previews-heading">
        <h3>{{ __('Uploaded documents') }}</h3>
        @if ($files)
            <x-filament::badge color="gray" size="sm">
                {{ count($files) }}
            </x-filament::badge>
        @endif
    </div>

    @if ($files)
        <div class="admin-document-grid">
            @foreach ($files as $file)
                @include('filament.partials.file-preview-box', [
                    'asset' => $file,
                    'documentNumber' => $loop->iteration,
                ])
            @endforeach
        </div>
    @else
        <div class="admin-document-empty-state" aria-live="polite">
            <x-filament::icon icon="heroicon-o-document-text" class="admin-document-empty-state-icon" />
            <div>
                <p class="admin-document-empty-state-title">
                    {{ $reviewedVerification ? __('No documents retained') : __('No documents uploaded') }}
                </p>
                <p class="admin-document-empty-state-copy">
                    {{ $reviewedVerification ? __('The verification decision remains saved.') : __('Uploaded documents will appear here.') }}
                </p>
            </div>
        </div>
    @endif
</div>
