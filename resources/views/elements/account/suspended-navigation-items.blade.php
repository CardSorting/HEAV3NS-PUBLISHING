<a class="dropdown-item d-flex align-items-center" href="{{ route('account.suspension') }}">
    @include('elements.icon', ['icon' => 'shield-outline', 'variant' => 'small', 'classes' => 'me-2 text-muted'])
    {{ __('Account status') }}
</a>
<a class="dropdown-item d-flex align-items-center" href="{{ route('my.settings', ['type' => 'account']) }}">
    @include('elements.icon', ['icon' => 'settings-outline', 'variant' => 'small', 'classes' => 'me-2 text-muted'])
    {{ __('Account settings') }}
</a>
<a class="dropdown-item d-flex align-items-center" href="{{ route('my.settings', ['type' => 'subscriptions']) }}">
    @include('elements.icon', ['icon' => 'repeat-outline', 'variant' => 'small', 'classes' => 'me-2 text-muted'])
    {{ __('Subscriptions') }}
</a>
<a class="dropdown-item d-flex align-items-center" href="{{ route('my.settings', ['type' => 'payments']) }}">
    @include('elements.icon', ['icon' => 'receipt-outline', 'variant' => 'small', 'classes' => 'me-2 text-muted'])
    {{ __('Payment history') }}
</a>
<a class="dropdown-item d-flex align-items-center" href="{{ route('contact') }}">
    @include('elements.icon', ['icon' => 'help-circle-outline', 'variant' => 'small', 'classes' => 'me-2 text-muted'])
    {{ __('Contact support') }}
</a>
