@foreach($fields as $field)
    @php
        $key = $field['key'];
        $fieldId = $idPrefix.'-'.$key;
        $errorKey = $prefix.'.'.$key;
        $value = $values[$key] ?? '';
        $type = $field['type'] ?? 'text';
        $maxLength = $field['max_length'] ?? ($type === 'textarea' ? 2000 : 255);
        $fullWidth = count($fields) === 1 || in_array($type, ['textarea', 'email']) || in_array($key, ['account_holder_name', 'iban', 'account_number', 'wallet_address', 'destination', 'pix_key', 'pix_key_type', 'country_name']);
    @endphp
    <div class="{{ !empty($grid) ? ($fullWidth ? 'col-12' : 'col-12 col-sm-6') : 'mb-3 w-100' }}">
        <label class="form-label" for="{{ $fieldId }}">{{ $field['label'] }}{{ !empty($field['required']) ? ' *' : '' }}</label>
        @if($type === 'file' && $prefix === 'submitted_fields')
            @include('elements.settings.manual-payment-upload', ['methodId' => $method->id])
        @elseif($type === 'select')
            <select id="{{ $fieldId }}" name="{{ $prefix }}[{{ $key }}]" data-field-key="{{ $key }}" class="form-select @error($errorKey) is-invalid @enderror" @if(!empty($field['required'])) required @endif>
                <option value="">{{ __('Select an option') }}</option>
                @foreach($field['options'] ?? [] as $option)
                    <option value="{{ $option['value'] }}" @selected((string) $value === (string) $option['value'])>{{ $option['label'] }}</option>
                @endforeach
            </select>
        @elseif($type === 'textarea')
            <textarea id="{{ $fieldId }}" name="{{ $prefix }}[{{ $key }}]" data-field-key="{{ $key }}" class="form-control @error($errorKey) is-invalid @enderror" rows="2" maxlength="{{ $maxLength }}" @if(!empty($field['required'])) required @endif>{{ $value }}</textarea>
        @else
            <input id="{{ $fieldId }}" name="{{ $prefix }}[{{ $key }}]" data-field-key="{{ $key }}" type="{{ $type === 'email' ? 'email' : ($type === 'phone' ? 'tel' : 'text') }}" class="form-control @error($errorKey) is-invalid @enderror" value="{{ $value }}" maxlength="{{ $maxLength }}" autocomplete="off" @if(!empty($field['required'])) required @endif>
        @endif
        @if(!empty($field['help_text']))
            <small class="form-text text-muted">{{ $field['help_text'] }}</small>
        @endif
        <span class="invalid-feedback" role="alert">{{ $errors->first($errorKey) ?: __('Please check this field.') }}</span>
    </div>
@endforeach
