@include('filament.partials.attachment-table-preview', [
    'media' => \App\Filament\Resources\Attachments\AttachmentPresentation::data($getRecord()),
])
