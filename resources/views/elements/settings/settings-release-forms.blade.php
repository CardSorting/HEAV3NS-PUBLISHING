<div class="settings-data-page settings-release-forms-page">
@if(session('success'))
    <div class="alert alert-dismissible alert-success text-white fw-bold mt-2" role="alert">
        {{session('success')}}
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-dismissible alert-warning text-white fw-bold mt-2" role="alert">
        {{session('error')}}
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div>
    @if(getSetting('compliance.release_forms_custom_message_box'))
        @include('elements.settings.custom-info-box', [
            'message' => getSetting('compliance.release_forms_custom_message_box'),
            'classes' => 'mt-3 mb-3',
        ])
    @endif

    <form class="release-forms-form verify-form mb-4" action="{{route('my.settings.release-forms.save')}}" method="POST">
        @csrf
        <div class="mb-3">
            <label for="release-form-title">{{__('Title')}}</label>
            <input id="release-form-title" name="title" type="text" class="form-control @error('title') is-invalid @enderror" value="{{old('title')}}" placeholder="{{__('Example: Partner release form')}}">
            @error('title')
                <span class="invalid-feedback" role="alert"><strong>{{$message}}</strong></span>
            @enderror
        </div>

        <div class="mb-3">
            <label for="release-form-notes">{{__('Notes')}}</label>
            <textarea id="release-form-notes" name="notes" class="form-control @error('notes') is-invalid @enderror" rows="3" placeholder="{{__('Optional notes for admins')}}">{{old('notes')}}</textarea>
            @error('notes')
                <span class="invalid-feedback" role="alert"><strong>{{$message}}</strong></span>
            @enderror
        </div>

        <div class="mb-3">
            <label>{{__('Files')}}</label>
            <div class="dropzone-previews dropzone w-100 ppl-0 pe-0 pt-1 pb-1 border rounded"></div>
            <small class="form-text text-muted mb-2">{{__("Allowed file types")}}: {{str_replace(',', ', ', AttachmentHelper::filterExtensions('manualPayments'))}}. {{__("Max size")}}: 4 {{__("MB")}}.</small>
        </div>

        <div class="d-flex flex-row-reverse">
            <button class="btn btn-primary mt-2">{{__("Submit for review")}}</button>
        </div>
    </form>
</div>

<div class="release-forms-history mb-4">
    <div class="settings-data-table d-flex align-items-center justify-content-between border-top border-bottom py-2 px-2 px-md-4">
        <h6 class="fw-semibold mb-0 ps-1 ps-md-2">{{__('Your release forms')}}</h6>
        @if(($releaseForms ?? collect())->count())
            <span class="text-muted small pe-1 pe-md-2">{{trans_choice(':count form|:count forms', ($releaseForms ?? collect())->count(), ['count' => ($releaseForms ?? collect())->count()])}}</span>
        @endif
    </div>

    @if(($releaseForms ?? collect())->count())
        <div class="table-wrapper settings-data-table table-hover-primary-soft">
            <div class="settings-data-table-head">
                <div class="col d-flex align-items-center border-bottom fw-semibold settings-data-header-row">
                    <div class="col-6 col-md-4 text-truncate">{{__('Form')}}</div>
                    <div class="col-3 col-md-2 text-truncate">{{__('Status')}}</div>
                    <div class="col-md-2 text-truncate d-none d-md-block">{{__('Submitted')}}</div>
                    <div class="col-md-3 text-truncate d-none d-md-block">{{__('Files')}}</div>
                    <div class="col-3 col-md-1 text-truncate settings-data-action-cell"></div>
                </div>
            </div>

            <div class="settings-data-table-body">
            @foreach(($releaseForms ?? collect()) as $releaseForm)
                @php
                    $statusClass = match ($releaseForm->status) {
                        \App\Model\ReleaseForm::APPROVED_STATUS => 'success',
                        \App\Model\ReleaseForm::REJECTED_STATUS => 'danger',
                        default => 'warning',
                    };
                    $files = is_array($releaseForm->files) ? $releaseForm->files : [];
                @endphp

                <div class="col d-flex align-items-center border-bottom settings-data-row table-hover-item">
                    <div class="col-6 col-md-4 text-truncate">
                        <div class="settings-data-cell-main text-truncate" title="{{ $releaseForm->title ?: __('Release form') }}">
                            {{$releaseForm->title ?: __('Release form')}}
                        </div>
                        @if($releaseForm->notes)
                            <div class="settings-data-meta text-muted small text-truncate" title="{{$releaseForm->notes}}">{{$releaseForm->notes}}</div>
                        @endif
                        @if($releaseForm->rejection_reason && $releaseForm->status === \App\Model\ReleaseForm::REJECTED_STATUS)
                            <div class="settings-data-meta text-danger small text-truncate" title="{{$releaseForm->rejection_reason}}">
                                {{__('Rejected reason')}}: {{$releaseForm->rejection_reason}}
                            </div>
                        @endif
                    </div>
                    <div class="col-3 col-md-2">
                        <span class="badge bg-{{$statusClass}} settings-data-status-badge">{{__(ucfirst($releaseForm->status))}}</span>
                    </div>
                    <div class="col-md-2 text-muted d-none d-md-block">
                        {{$releaseForm->created_at->format('M j, Y')}}
                    </div>
                    <div class="col-md-3 text-muted d-none d-md-block">
                        {{trans_choice(':count file|:count files', count($files), ['count' => count($files)])}}
                    </div>
                    <div class="col-3 col-md-1 d-flex justify-content-center settings-data-action-cell">
                        <div class="dropdown dropstart">
                            <a class="btn dropdown-toggle ellipsis-dropdown-toggle settings-data-action-btn m-0" data-bs-toggle="dropdown" href="#" role="button" aria-label="{{__('Release form actions')}}" aria-haspopup="true" aria-expanded="false">
                                @include('elements.icon',['icon'=>'ellipsis-horizontal-outline','centered'=>false])
                            </a>
                            <div class="dropdown-menu">
                                @foreach($files as $index => $file)
                                    <a href="{{Storage::url($file)}}" target="_blank" rel="noopener" class="dropdown-item d-flex align-items-center">
                                        @include('elements.icon', ['icon' => 'document-text-outline', 'centered' => false, 'classes' => 'me-2'])
                                        {{__('File')}} {{$index + 1}}
                                    </a>
                                @endforeach
                                @if(!empty($files))
                                    <div class="dropdown-divider"></div>
                                @endif
                                <button type="button" class="dropdown-item d-flex align-items-center text-danger" data-bs-toggle="modal" data-bs-target="#release-form-delete-dialog-{{$releaseForm->id}}">
                                    @include('elements.icon', ['icon' => 'trash-outline', 'centered' => false, 'classes' => 'me-2'])
                                    {{__('Delete')}}
                                </button>
                            </div>
                        </div>

                        <form id="release-form-delete-form-{{$releaseForm->id}}" action="{{route('my.settings.release-forms.delete', ['releaseForm' => $releaseForm->id])}}" method="POST" class="d-none">
                            @csrf
                            @method('DELETE')
                        </form>
                    </div>
                </div>

            @endforeach
            </div>
        </div>

        @foreach(($releaseForms ?? collect()) as $releaseForm)
            @include('elements.standard-dialog', [
                'dialogName' => 'release-form-delete-dialog-'.$releaseForm->id,
                'title' => __('Delete release form'),
                'content' => __('Are you sure you want to delete this release form?'),
                'actionLabel' => __('Delete'),
                'actionFunction' => "document.getElementById('release-form-delete-form-".$releaseForm->id."').submit();",
            ])
        @endforeach
    @else
        <div class="settings-data-empty-state text-center">
            <div class="settings-data-empty-title">{{__('No release forms yet')}}</div>
            <div class="settings-data-empty-copy text-muted">{{__('Uploaded release forms and their review status will appear here.')}}</div>
        </div>
    @endif
</div>
</div>

@include('elements.uploaded-file-preview-template')
