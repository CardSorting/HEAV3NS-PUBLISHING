@if($postMediaData['fileCount'] && $postMediaData['filesUnlocked'])
    <div class="post-files px-3 mt-3">
        <h6 class="small fw-semibold mb-2">{{ __('Files') }}</h6>
        <div class="d-flex flex-column gap-2">
            @foreach($postMediaData['fileAttachments'] as $file)
                @include('elements.attachments.file-card', ['file' => $file])
            @endforeach
        </div>
    </div>
@endif
