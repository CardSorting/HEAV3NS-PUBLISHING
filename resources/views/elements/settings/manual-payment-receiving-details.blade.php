@if(count($details))
    <div class="border rounded p-3 mb-3" data-manual-receiving-details>
        <div class="d-flex flex-column gap-3">
            @foreach($details as $detail)
                @if($detail['type'] === 'image')
                    <div>
                        <div class="small text-body-secondary mb-1">{{ $detail['label'] }}</div>
                        <a href="{{ $detail['url'] }}" target="_blank" rel="noopener noreferrer" class="d-inline-block mw-100">
                            <img src="{{ $detail['url'] }}" width="240" loading="lazy" class="img-fluid bg-white border rounded p-2" alt="{{ $detail['label'] }}">
                        </a>
                    </div>
                @else
                    <div class="d-flex align-items-start justify-content-between gap-2">
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="small text-body-secondary mb-1">{{ $detail['label'] }}</div>
                            <div class="text-break fw-semibold">{{ $detail['value'] }}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary border-0 p-1 lh-1 mb-0 flex-shrink-0"
                                data-manual-receiving-copy data-copy-value="{{ $detail['value'] }}"
                                aria-label="{{ __('Copy to Clipboard') }}: {{ $detail['label'] }}" title="{{ __('Copy to Clipboard') }}">
                            @include('elements.icon', ['icon' => 'copy-outline', 'variant' => 'small'])
                        </button>
                    </div>
                @endif
            @endforeach
        </div>
    </div>
@endif
