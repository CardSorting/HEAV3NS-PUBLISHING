@include('elements.empty-state', [
    'illustration' => asset('/img/no-content-available.svg'),
    'title' => $title,
    'copy' => $copy,
    'primaryAction' => $resetUrl ? [
        'url' => $resetUrl,
        'label' => __('Clear filters'),
        'icon' => 'refresh-outline',
        'style' => 'outline-primary',
    ] : null,
])
