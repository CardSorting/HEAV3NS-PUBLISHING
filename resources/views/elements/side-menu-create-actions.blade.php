@if(GenericHelper::isEmailEnforcedAndValidated() && count($createOptions))
    <li class="nav-item">
        <div class="btn-group w-100 side-menu-create-group">
            <a role="button" class="btn btn-primary side-menu-action-btn mb-0 {{count($createOptions) > 1 ? 'side-menu-create-main' : 'btn-round w-100'}}" href="{{$createOptions[0]['url']}}" aria-label="{{ GenericHelper::canViewCreationPage() ? __('Create') : $createOptions[0]['label'] }}">
                <span class="d-none d-md-flex d-xl-flex d-lg-flex align-items-center justify-content-center text-truncate new-post-label side-menu-action-content">
                    <span class="side-menu-action-icon">
                        @include('elements.icon',['icon'=>'add-outline','variant'=>'medium','classes'=>'flex-shrink-0'])
                    </span>
                    <span class="side-menu-action-text">{{ GenericHelper::canViewCreationPage() ? __('Create') : $createOptions[0]['label'] }}</span>
                </span>
                <span class="d-block d-md-none d-flex align-items-center justify-content-center">@include('elements.icon',['icon'=>'add-circle-outline','variant'=>'medium','classes'=>'flex-shrink-0'])</span>
            </a>

            @if(count($createOptions) > 1)
                <button type="button"
                        class="btn btn-primary dropdown-toggle dropdown-toggle-split side-menu-create-toggle mb-0"
                        data-bs-toggle="dropdown"
                        aria-haspopup="true"
                        aria-expanded="false">
                    <span class="visually-hidden">{{__('Toggle create menu')}}</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end side-menu-create-dropdown">
                    @foreach($createOptions as $option)
                        <a class="dropdown-item d-flex align-items-center" href="{{$option['url']}}">
                            <span class="side-menu-create-option-icon">
                                @include('elements.icon',['icon'=>$option['icon'],'variant'=>'medium','centered'=>false])
                            </span>
                            <span class="side-menu-create-option-label">{{__('New')}} {{$option['label']}}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </li>
@endif
