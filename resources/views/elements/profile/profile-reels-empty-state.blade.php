@include('elements.empty-state', [
    'illustration' => asset('/img/no-content-available.svg'),
    'title' => $title,
    'copy' => $copy,
    'primaryAction' => $showCreateAction ? GenericHelper::getCreationAction('reels.create', __('Create your reel')) : null,
])
