@include('elements.standard-dialog', [
    'dialogName' => 'vault-archive-dialog',
    'title' => __('Archive media'),
    'content' => __('Archive this media? Existing posts, messages and enabled automations will continue to use it.'),
    'actionLabel' => __('Archive'),
    'actionFunction' => "MediaVault.confirmAction('archive');",
])

@include('elements.standard-dialog', [
    'dialogName' => 'vault-delete-dialog',
    'title' => __('Delete media'),
    'content' => __('Permanently delete this unused media? This cannot be undone.'),
    'actionLabel' => __('Delete permanently'),
    'actionFunction' => "MediaVault.confirmAction('delete');",
])
