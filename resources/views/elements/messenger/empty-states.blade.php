<template id="messenger-no-conversation-template">
    @include('elements.empty-state', [
        'illustration' => asset('/img/no-content-available.svg'),
        'illustrationClasses' => 'messenger-empty-state-illustration',
        'title' => __('Start a conversation'),
        'copy' => __('Choose a conversation or send a new message.'),
        'classes' => 'messenger-empty-state messenger-empty-state-main h-100',
    ])
</template>

<template id="messenger-no-messages-template">
    @include('elements.empty-state', [
        'title' => __('No messages yet'),
        'copy' => __('Send the first message.'),
        'classes' => 'messenger-empty-state messenger-empty-state-conversation h-100',
    ])
</template>

<template id="messenger-new-conversation-template">
    @include('elements.empty-state', [
        'title' => __('New message'),
        'copy' => __('Select a recipient to start a conversation.'),
        'classes' => 'messenger-empty-state messenger-empty-state-conversation new-conversation-placeholder h-100',
    ])
</template>

<template id="messenger-no-contacts-template">
    @include('elements.empty-state', [
        'title' => __('No conversations yet'),
        'copy' => __('Start a new message using the compose button.'),
        'classes' => 'messenger-empty-state messenger-empty-state-compact h-100',
    ])
</template>

<template id="messenger-no-contacts-search-template">
    @include('elements.empty-state', [
        'title' => __('No conversations found'),
        'copy' => __('Try another name or username.'),
        'classes' => 'messenger-empty-state messenger-empty-state-compact h-100',
    ])
</template>

<template id="messenger-no-message-search-template">
    @include('elements.empty-state', [
        'title' => __('No messages found'),
        'copy' => __('Try another search term.'),
        'classes' => 'messenger-empty-state messenger-empty-state-conversation h-100',
    ])
</template>
