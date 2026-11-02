@include('elements.standard-dialog',[
    'dialogName' => 'reel-delete-dialog',
    'title' => __('Delete reel'),
    'content' => __('Are you sure you want to delete this reel?'),
    'actionLabel' => __('Delete'),
    'actionFunction' => 'ReelsPlayer.confirmDeleteReel();',
])

@include('elements.standard-dialog',[
    'dialogName' => 'reel-comment-delete-dialog',
    'title' => __('Delete comment'),
    'content' => __('Are you sure you want to delete this comment?'),
    'actionLabel' => __('Delete'),
    'actionFunction' => 'ReelsPlayer.confirmDeleteComment();',
])
