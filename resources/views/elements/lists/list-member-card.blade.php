<div class="col-12 col-md-6 col-xl-4 px-2 py-2">
    @include('elements.feed.suggestion-card',[
        'profile' => $member,
        'isListMode' => true,
        'isListManageable' => ($list->type == \App\Model\UserList::FOLLOWERS_TYPE ? false : true),
        'cardRadius' => 'rounded'
    ])
</div>
