@php
    $fileLimit = \App\Services\ManualPayments\ManualPaymentDocuments::maxSizeKb($field);
    $fileIds = old('submitted_fields.'.$field['key'], []);
    $files = is_array($fileIds) ? \App\Model\ManualPaymentDocument::query()
        ->whereIn('id', array_filter($fileIds, fn ($id) => is_scalar($id)))
        ->where('user_id', Auth::id())->where('manual_method_id', $methodId)->where('field_key', $field['key'])
        ->whereNull('submitted_at')->where('created_at', '>', now()->subDay())->get() : collect();
@endphp
<div class="manual-document-field" data-method-id="{{ $methodId }}" data-field-key="{{ $field['key'] }}"
     data-required="{{ !empty($field['required']) ? '1' : '0' }}" data-max-files="{{ $field['max_files'] }}"
     data-max-size="{{ $fileLimit * 1024 }}" data-form="{{ $uploadForm ?? '' }}"
     data-upload-url="{{ route('manual-payment-documents.store') }}">
    <input id="{{ $fieldId }}" type="file" class="form-control manual-document-picker manual-request-field"
           data-field-key="{{ $field['key'] }}"
           accept="{{ implode(',', array_map(fn ($ext) => '.'.$ext, $field['allowed_extensions'])) }}"
           multiple @disabled(!empty($uploadDisabled)) aria-describedby="{{ $fieldId }}-limits {{ $fieldId }}-status">
    <div class="form-text" id="{{ $fieldId }}-limits">@if(!empty($minimumReceiptHint)){{ $minimumReceiptHint }} @endif{{ trans_choice('{1} Up to :count file, :size MB each.|[2,*] Up to :count files, :size MB each.', $field['max_files'], ['count' => $field['max_files'], 'size' => round($fileLimit / 1024, 2)]) }} {{ strtoupper(implode(', ', $field['allowed_extensions'])) }}</div>
    <ul class="list-unstyled mb-0 manual-document-list">
        @foreach($files as $document)
            <li class="d-flex align-items-center gap-2 border rounded p-2 mt-2 manual-document-item" data-document-id="{{ $document->id }}" data-delete-url="{{ route('manual-payment-documents.destroy', $document) }}">
                <a class="small text-break flex-grow-1" href="{{ route('manual-payment-documents.download', $document) }}">{{ $document->original_name }}</a>
                <input type="hidden" class="manual-document-id manual-request-field" data-field-key="{{ $field['key'] }}" name="submitted_fields[{{ $field['key'] }}][]" value="{{ $document->id }}" @if(!empty($uploadForm)) form="{{ $uploadForm }}" @endif @disabled(!empty($uploadDisabled))>
                <button class="btn-close flex-shrink-0 ms-2 manual-document-remove" type="button"
                        title="{{ __('Remove') }}" aria-label="{{ __('Remove :filename', ['filename' => $document->original_name]) }}"></button>
            </li>
        @endforeach
    </ul>
    <div id="{{ $fieldId }}-status" class="manual-document-status small text-danger mt-1" role="status" aria-live="polite">{{ $errors->first('submitted_fields.'.$field['key']) }}</div>
</div>
