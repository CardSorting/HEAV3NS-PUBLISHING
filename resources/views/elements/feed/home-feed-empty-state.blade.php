@include('elements.empty-state', [
    'illustration' => asset('/img/no-content-available.svg'),
    'title' => __('Your feed is empty'),
    'copy' => __('Follow creators to see their latest posts here.'),
    'primaryAction' => [
        'url' => route('search.get', ['filter' => 'people']),
        'label' => __('Explore creators'),
        'icon' => 'people-outline',
        'style' => 'primary',
    ],
    'secondaryAction' => GenericHelper::isEmailEnforcedAndValidated()
        ? GenericHelper::getCreationAction('posts.create', __('Create post'), 'outline-primary') : null,
])
