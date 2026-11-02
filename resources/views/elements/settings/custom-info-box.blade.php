@php
    $rawMessage = is_string($message ?? null) ? $message : '';
    $hasHtml = $rawMessage !== strip_tags($rawMessage);
    $cleanMessage = trim(
        $hasHtml
            ? $rawMessage
            : nl2br(e($rawMessage))
    );
    $hasMessage = trim(strip_tags($cleanMessage)) !== '';
    $classes = $classes ?? 'mt-3';
@endphp

@if($hasMessage)
    <div class="alert alert-primary text-white custom-info-alert {{ $classes }}" role="alert">
        <div class="fw-bold">
            {!! $cleanMessage !!}
        </div>
    </div>
@endif
