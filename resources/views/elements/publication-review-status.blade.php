@if($review)
    <div class="d-flex align-items-center small text-muted pe-3">
        @if($review['rejection_reason'])
            <button type="button" class="badge rounded-pill bg-gradient-faded-secondary border-0 focus-ring"
                    data-bs-toggle="tooltip" data-bs-trigger="hover focus" data-bs-placement="bottom"
                    data-bs-custom-class="publication-review-tooltip" data-bs-html="false" data-tooltip-touch="true"
                    title="{{$review['rejection_reason']}}">{{$review['label']}}</button>
        @else
            <span class="badge rounded-pill bg-gradient-faded-secondary">{{$review['label']}}</span>
        @endif
    </div>
@endif
