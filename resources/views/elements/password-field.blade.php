@php
    $inputId = isset($id) ? $id : (isset($name) ? $name : 'password');
    $inputName = isset($name) ? $name : $inputId;
    $inputErrorName = isset($errorName) ? $errorName : $inputName;
    $inputClasses = trim('form-control password-reveal-input ' . (isset($classes) ? $classes : '') . ($errors->has($inputErrorName) ? ' is-invalid' : ''));
@endphp

<div class="password-reveal-field {{ isset($groupClasses) ? $groupClasses : '' }}">
    <input
        id="{{ $inputId }}"
        type="password"
        class="{{ $inputClasses }}"
        name="{{ $inputName }}"
        @if(isset($value)) value="{{ $value }}" @endif
        @if(isset($placeholder)) placeholder="{{ $placeholder }}" @endif
        @if(isset($autocomplete)) autocomplete="{{ $autocomplete }}" @endif
        @if(isset($required) && $required) required @endif
        @if(isset($autofocus) && $autofocus) autofocus @endif
    >
    <button type="button" class="password-reveal-toggle" data-bs-toggle="tooltip" data-bs-trigger="hover" data-bs-placement="top" title="{{__('Show password')}}" aria-label="{{__('Show password')}}">
        <span class="password-reveal-hide d-none">
            @include('elements.icon',['icon'=>'eye-off-outline', 'variant' => 'medium'])
        </span>
        <span class="password-reveal-show">
            @include('elements.icon',['icon'=>'eye-outline', 'variant' => 'medium'])
        </span>
    </button>
</div>
