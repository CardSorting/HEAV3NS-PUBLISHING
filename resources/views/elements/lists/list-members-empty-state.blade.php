@include('elements.empty-state', [
    'illustration' => asset('/img/no-content-available.svg'),
    'title' => $title,
    'copy' => $copy,
    'primaryAction' => $actionUrl ? [
        'url' => $actionUrl,
        'label' => $actionLabel,
        'icon' => $actionLabel === __('Clear search') ? 'close-outline' : 'people-outline',
        'style' => $actionLabel === __('Clear search') ? 'outline-primary' : 'primary',
    ] : null,
])
