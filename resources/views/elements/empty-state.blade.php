<div class="app-empty-state d-flex flex-column align-items-center justify-content-center text-center px-4 py-5 {{$classes ?? ''}}">
    @if(!empty($illustration))
        <img src="{{$illustration}}" class="app-empty-state-illustration {{$illustrationClasses ?? ''}} mb-3" alt="">
    @elseif(!empty($icon))
        <div class="app-empty-state-icon d-flex align-items-center justify-content-center mb-3" aria-hidden="true">
            <ion-icon name="{{$icon}}"></ion-icon>
        </div>
    @endif
    <h5 class="app-empty-state-title fw-bold mb-2">{{$title}}</h5>
    @if(!empty($copy))
        <p class="app-empty-state-copy text-muted {{!empty($primaryAction) || !empty($secondaryAction) || !empty($emptyStateActions) ? 'mb-3' : 'mb-0'}}">{{$copy}}</p>
    @endif
    @if(!empty($primaryAction) || !empty($secondaryAction) || !empty($emptyStateActions))
        <div class="app-empty-state-actions d-flex flex-wrap justify-content-center {{$emptyStateActionsClasses ?? ''}}">
            {{$emptyStateActions ?? ''}}
            @foreach([$primaryAction ?? null, $secondaryAction ?? null] as $action)
                @if(!empty($action))
                    <a href="{{$action['url']}}" class="btn btn-sm btn-{{$action['style']}} d-flex align-items-center mb-0">
                        @if(!empty($action['icon']))
                            @include('elements.icon',['icon'=>$action['icon'],'variant'=>'small','classes'=>'me-1'])
                        @endif
                        <span>{{$action['label']}}</span>
                    </a>
                @endif
            @endforeach
        </div>
    @endif
</div>
