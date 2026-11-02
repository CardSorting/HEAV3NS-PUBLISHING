@include('elements.standard-dialog',[
    'dialogName' => 'story-delete-dialog',
    'title' => __('Delete story'),
    'content' => __('Delete this story? This cannot be undone.'),
    'actionLabel' => __('Delete'),
    'actionFunction' => 'StoriesSwiper.confirmDeleteCurrentStory();',
])
