<a href="{{ \App\Filament\Resources\MediaModerationItems\MediaModerationItemResource::getUrl('view', ['record' => $getRecord()]) }}">
    @include('filament.partials.attachment-table-preview', [
        'media' => \App\Filament\Resources\Attachments\AttachmentPresentation::moderationData($getRecord()),
    ])
</a>
