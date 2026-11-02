@include('elements.empty-state', [
    'illustration' => asset('/img/no-content-available.svg'),
    'title' => $title,
    'copy' => $copy,
    'classes' => $classes ?? '',
    'primaryAction' => $showCreateAction ? GenericHelper::getCreationAction('posts.create', __('Create post')) : null,
])
