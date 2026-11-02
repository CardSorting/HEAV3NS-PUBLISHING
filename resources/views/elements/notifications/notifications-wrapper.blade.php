<div class="notifications-wrapper pt-4">
    @if(count($notifications))
    @foreach($notifications as $notification)
            @include('elements.notifications.notification-box', ['notification' => $notification])
            @if(!$loop->last)
                <hr class="my-2 ">
            @endif
        @endforeach
        @if($notifications->hasPages())
            <div class="mt-3 mb-3 px-4">
                {{ $notifications->onEachSide(1)->links() }}
            </div>
        @endif
    @else
        @include('elements.empty-state', [
            'illustration' => asset('/img/no-notifications.svg'),
            'illustrationClasses' => 'app-empty-state-illustration-notifications',
            'title' => __('Missing notifications'),
            'copy' => __('New activity will appear here.'),
        ])
    @endif
</div>
