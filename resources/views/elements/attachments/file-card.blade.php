<div class="file-attachment-card d-flex align-items-center gap-3 border rounded p-3 w-100">
    <span class="text-primary flex-shrink-0" aria-hidden="true">
        @include('elements.icon', ['icon' => ($file['extension'] ?? '') === 'zip' ? 'archive-outline' : 'document-text-outline', 'variant' => 'medium'])
    </span>
    <div class="file-attachment-details">
        <div class="file-attachment-name text-break fw-semibold" title="{{ $file['original_name'] }}" aria-label="{{ $file['original_name'] }}">{{ $file['original_name'] }}</div>
        <div class="small text-body-secondary">{{ strtoupper($file['extension']) }}@if($file['size_label'] ?? null) · {{ $file['size_label'] }}@endif</div>
    </div>
    @if($file['download_url'] ?? null)
        <a class="btn btn-outline-primary btn-sm mb-0 flex-shrink-0" href="{{ $file['download_url'] }}" aria-label="{{ __('Download :filename', ['filename' => $file['original_name']]) }}">{{ __('Download') }}</a>
    @endif
</div>
