@include('elements.empty-state', [
    'illustration' => asset('/img/no-content-available.svg'),
    'title' => $title,
    'copy' => $copy,
    'primaryAction' => $exploreUrl ? [
        'url' => $exploreUrl,
        'label' => __('Explore'),
        'icon' => 'compass-outline',
        'style' => 'primary',
    ] : null,
])
