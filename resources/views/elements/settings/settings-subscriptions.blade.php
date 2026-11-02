@if($subscribersCount)
    <div class="mt-3 mb-0 inline-border-tabs">
        <nav class="nav nav-pills nav-justified">
            @foreach(['subscriptions', 'subscribers'] as $tab)
                <a class="nav-item nav-link {{$activeSubsTab == $tab ? 'active' : ''}}" href="{{route('my.settings',['type' => 'subscriptions', 'active' => $tab])}}">
                    <div class="d-flex align-items-center justify-content-center">
                        @if($tab == 'subscriptions')
                            @include('elements.icon',['icon'=>'people','variant'=>'medium','classes'=>'me-2'])
                        @else
                            @include('elements.icon',['icon'=>'logo-usd','variant'=>'medium','classes'=>'me-2'])
                        @endif
                        {{ucfirst(__($tab))}}
                    </div>
                </a>
            @endforeach
        </nav>
    </div>
@endif

<div class="settings-data-page settings-subscriptions-page">
    <div class="settings-data-overview mb-0">
        <div class="settings-data-summary row g-3 mb-0">
            <div class="col-6 col-xl-3">
                <div class="settings-data-stat-card d-flex flex-column">
                    <div class="d-flex align-items-center justify-content-between gap-1">
                        <div class="settings-data-stat-label text-truncate flex-grow-1 mb-0">{{ __('Total') }}</div>
                        <span class="settings-data-stat-icon">@include('elements.icon',['icon'=>'people-outline','variant'=>'small','centered'=>true])</span>
                    </div>
                    <div class="settings-data-stat-value mt-1">{{ number_format((int) $subscriptionSummary['total_entries']) }}</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="settings-data-stat-card d-flex flex-column">
                    <div class="d-flex align-items-center justify-content-between gap-1">
                        <div class="settings-data-stat-label text-truncate flex-grow-1 mb-0">{{ __('Active') }}</div>
                        <span class="settings-data-stat-icon">@include('elements.icon',['icon'=>'checkmark-circle-outline','variant'=>'small','centered'=>true])</span>
                    </div>
                    <div class="settings-data-stat-value mt-1">{{ number_format((int) $subscriptionSummary['active_entries']) }}</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="settings-data-stat-card d-flex flex-column">
                    <div class="d-flex align-items-center justify-content-between gap-1">
                        <div class="settings-data-stat-label text-truncate flex-grow-1 mb-0">{{ __('Ending soon') }}</div>
                        <span class="settings-data-stat-icon">@include('elements.icon',['icon'=>'time-outline','variant'=>'small','centered'=>true])</span>
                    </div>
                    <div class="settings-data-stat-value mt-1">{{ number_format((int) $subscriptionSummary['ending_soon_entries']) }}</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="settings-data-stat-card d-flex flex-column">
                    <div class="d-flex align-items-center justify-content-between gap-1">
                        <div class="settings-data-stat-label text-truncate flex-grow-1 mb-0">{{ $activeSubsTab == 'subscriptions' ? __('Active spend') : __('Active revenue') }}</div>
                        <span class="settings-data-stat-icon">@include('elements.icon',['icon'=>'cash-outline','variant'=>'small','centered'=>true])</span>
                    </div>
                    <div class="settings-data-stat-value mt-1">{{ \App\Providers\SettingsServiceProvider::getWebsiteFormattedAmount(number_format((float) $subscriptionSummary['active_amount'], 2, '.', ',')) }}</div>
                </div>
            </div>
        </div>
    </div>

    @include('elements/message-alert', ['classes' =>'p-2'])

    @if($subscriptions->count())
        <div class="table-wrapper settings-data-table settings-subscriptions-table table-hover-primary-soft">
            <div class="settings-data-table-head">
                <div class="d-grid align-items-center border-bottom fw-semibold settings-data-header-row">
                    <div class="text-truncate">{{$activeSubsTab == 'subscriptions' ? __('To') : __('From')}}</div>
                    <div class="text-truncate">{{__('Status')}}</div>
                    <div class="text-truncate d-none d-lg-block">{{__('Paid with')}}</div>
                    <div class="text-truncate">{{__('Renews')}}</div>
                    <div class="text-truncate d-none d-lg-block">{{__('Expires at')}}</div>
                    <div class="settings-data-action-cell"></div>
                </div>
            </div>

            <div class="settings-data-table-body">
                @foreach($subscriptionPresentations as $subscriptionPresentation)
                    <div class="d-grid align-items-center border-bottom settings-data-row settings-subscriptions-row table-hover-item">
                        <div class="d-flex align-items-center overflow-hidden">
                            @if($subscriptionPresentation['profileUrl'])
                                <a href="{{ $subscriptionPresentation['profileUrl'] }}" class="d-flex align-items-center overflow-hidden text-dark-r settings-data-party-link" title="{{ $subscriptionPresentation['profileName'] }}">
                                    <img src="{{ $subscriptionPresentation['profileAvatar'] ?: asset('img/default-avatar.jpg') }}" data-fallback-src="{{ asset('img/default-avatar.jpg') }}" class="rounded-circle user-avatar object-fit-cover flex-shrink-0 me-2" width="36" height="36" alt="">
                                    <span class="text-truncate">{{ $subscriptionPresentation['profileName'] }}</span>
                                </a>
                            @else
                                <span class="settings-data-cell-main text-truncate">{{ $subscriptionPresentation['profileName'] }}</span>
                            @endif
                        </div>

                        <div>
                            <span class="badge bg-{{ $subscriptionPresentation['statusClass'] }} settings-data-status-badge">
                                {{ $subscriptionPresentation['statusLabel'] }}
                            </span>
                        </div>

                        <div class="text-truncate d-none d-lg-block text-muted small">
                            {{ $subscriptionPresentation['providerLabel'] }}
                        </div>

                        <div>
                            <div class="text-muted small">
                                {{ $subscriptionPresentation['renewsLabel'] }}
                            </div>
                        </div>

                        <div class="d-none d-lg-block">
                            <div class="text-muted small">
                                {{ $subscriptionPresentation['expiresLabel'] }}
                            </div>
                        </div>

                        <div class="d-flex justify-content-center settings-data-action-cell">
                            @if($subscriptionPresentation['canCancel'])
                                <div class="dropdown dropstart">
                                    <a class="btn dropdown-toggle ellipsis-dropdown-toggle settings-data-action-btn m-0" data-bs-toggle="dropdown" href="#" role="button" aria-label="{{__('Subscription actions')}}" aria-haspopup="true" aria-expanded="false">
                                        @include('elements.icon',['icon'=>'ellipsis-horizontal-outline','centered'=>false])
                                    </a>
                                    <div class="dropdown-menu">
                                        @if($subscriptionPresentation['provider'] !== 'ccbill' || \App\Providers\SettingsServiceProvider::providedCCBillSubscriptionCancellingCredentials())
                                            <a class="dropdown-item d-flex align-items-center" href="javascript:void(0)" onclick="SubscriptionsSettings.confirmSubCancelation({{$subscriptionPresentation['id']}},{{$activeSubsTab == 'subscriptions' ? '"subscriptions"' : '"subscribers"'}})">
                                                @include('elements.icon',['icon'=>'trash-outline','centered'=>false,'classes'=>'me-2']) {{__('Cancel subscription')}}
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="settings-data-pagination mt-3 px-0 px-md-2 pb-2">
            {{ $subscriptions->withQueryString()->links() }}
        </div>
    @else
        @include('elements.empty-state', [
            'icon' => $activeSubsTab == 'subscriptions' ? 'people-outline' : 'person-add-outline',
            'title' => $activeSubsTab == 'subscriptions' ? __('No subscriptions yet') : __('No subscribers yet'),
            'copy' => $activeSubsTab == 'subscriptions' ? __('Creators you support will show up here.') : __('Members who subscribe to you will show up here.'),
            'primaryAction' => $activeSubsTab == 'subscriptions' ? [
                'url' => route('search.get', ['filter' => 'people']),
                'label' => __('Explore creators'),
                'icon' => 'people-outline',
                'style' => 'primary',
            ] : null,
            'classes' => 'settings-subscriptions-empty-state',
        ])
    @endif
</div>

@include('elements.settings.transaction-cancel-dialog')
