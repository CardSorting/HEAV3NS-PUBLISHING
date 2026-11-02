@php
    $fieldId = 'manual-deposit-'.$methodId.'-'.$field['key'];
    $fieldType = $field['type'] ?? 'text';
@endphp
<div class="mb-3">
    <label for="{{ $fieldId }}">{{ $field['label'] }}@if(!empty($field['required'])) <span aria-hidden="true">*</span>@endif</label>
    @if($fieldType === 'file')
        @include('elements.settings.manual-payment-upload', ['uploadForm' => 'wallet-deposit', 'uploadDisabled' => true])
    @elseif($fieldType === 'textarea')
        <textarea id="{{ $fieldId }}" name="submitted_fields[{{ $field['key'] }}]" form="wallet-deposit"
                  class="form-control manual-request-field" rows="3" maxlength="{{ $field['max_length'] ?? 2000 }}" disabled
                  @if(!empty($field['required'])) required @endif>{{ old('submitted_fields.'.$field['key']) }}</textarea>
    @elseif($fieldType === 'select')
        <select id="{{ $fieldId }}" name="submitted_fields[{{ $field['key'] }}]" form="wallet-deposit"
                class="form-select manual-request-field" disabled @if(!empty($field['required'])) required @endif>
            <option value="">{{ __('Select an option') }}</option>
            @foreach($field['options'] ?? [] as $option)
                <option value="{{ $option['value'] }}" @selected(old('submitted_fields.'.$field['key']) == $option['value'])>{{ $option['label'] }}</option>
            @endforeach
        </select>
    @else
        <input id="{{ $fieldId }}" name="submitted_fields[{{ $field['key'] }}]" form="wallet-deposit"
               type="{{ $fieldType === 'email' ? 'email' : ($fieldType === 'phone' ? 'tel' : 'text') }}"
               class="form-control manual-request-field" maxlength="{{ $field['max_length'] ?? 255 }}" disabled
               value="{{ old('submitted_fields.'.$field['key']) }}" @if(!empty($field['required'])) required @endif>
    @endif
    @if(!empty($field['help_text']))<small class="form-text text-muted">{{ $field['help_text'] }}</small>@endif
    <span class="invalid-feedback">{{ __('Please enter a valid value.') }}</span>
</div>
