<?php

namespace App\Services\ManualPayments;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ManualPaymentFields
{
    public const TYPES = ['text', 'textarea', 'select', 'email', 'phone', 'file'];
    public const FILE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf', 'xlsx'];
    public const MAX_FILE_SIZE_KB = 5120;
    public const MAX_FILES = 3;
    public const MAX_FIELDS = 50;
    public const MAX_LENGTH = 5000;

    /** Validate configuration as well as customer input: admins cannot install arbitrary validation rules. */
    public static function validateDefinitions(array $definitions, string $prefix = 'fields', bool $allowFiles = false): array
    {
        $errors = [];
        $normalized = [];
        $keys = [];

        if (count($definitions) > self::MAX_FIELDS) {
            $errors[$prefix] = 'A method can contain at most '.self::MAX_FIELDS.' fields in each section.';
        }

        foreach (array_values($definitions) as $index => $field) {
            $base = $prefix.'.'.$index;
            if (!is_array($field)) {
                $errors[$base] = 'Enter a valid field definition.';
                continue;
            }

            $key = is_string($field['key'] ?? null) ? trim($field['key']) : '';
            $label = is_string($field['label'] ?? null) ? trim($field['label']) : '';
            $type = $field['type'] ?? 'text';
            $help = $field['help_text'] ?? $field['help'] ?? '';
            $maxLength = $field['max_length'] ?? ($type === 'textarea' ? 2000 : 255);

            if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $key)) {
                $errors[$base.'.key'] = 'Use a key starting with a lowercase letter, followed by lowercase letters, numbers or underscores.';
            } elseif (isset($keys[$key])) {
                $errors[$base.'.key'] = 'Each field must have a different key within its section.';
            }
            $keys[$key] = true;

            if ($label === '' || mb_strlen($label) > 150) {
                $errors[$base.'.label'] = 'Enter a field label of at most 150 characters.';
            }
            if (!in_array($type, self::TYPES, true) || ($type === 'file' && !$allowFiles)) {
                $errors[$base.'.type'] = 'Select a supported field type.';
            }
            if (!is_scalar($help) || mb_strlen((string) $help) > 1000) {
                $errors[$base.'.help_text'] = 'Help text must contain at most 1000 characters.';
                $help = '';
            }
            if (filter_var($maxLength, FILTER_VALIDATE_INT) === false || (int) $maxLength < 1 || (int) $maxLength > self::MAX_LENGTH) {
                $errors[$base.'.max_length'] = 'Set a maximum length between 1 and '.self::MAX_LENGTH.'.';
            }

            $options = [];
            if ($type === 'select') {
                $inputOptions = $field['options'] ?? [];
                if (!is_array($inputOptions) || count($inputOptions) < 1 || count($inputOptions) > 100) {
                    $errors[$base.'.options'] = 'Add between 1 and 100 choices.';
                    $inputOptions = [];
                }
                $optionValues = [];
                foreach ($inputOptions as $optionIndex => $option) {
                    $value = is_array($option) ? ($option['value'] ?? null) : $option;
                    $optionLabel = is_array($option) ? ($option['label'] ?? $value) : $option;
                    if (!is_scalar($value) || !is_scalar($optionLabel)
                        || trim((string) $value) === '' || trim((string) $optionLabel) === ''
                        || mb_strlen((string) $value) > (int) $maxLength || mb_strlen((string) $optionLabel) > 150) {
                        $errors[$base.'.options.'.$optionIndex] = 'Each choice needs a valid value and label.';
                        continue;
                    }
                    $value = trim((string) $value);
                    if (isset($optionValues[$value])) {
                        $errors[$base.'.options.'.$optionIndex.'.value'] = 'Choice values must be unique.';
                    }
                    $optionValues[$value] = true;
                    $options[] = ['value' => $value, 'label' => trim((string) $optionLabel)];
                }
            }

            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'required' => filter_var($field['required'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'help_text' => (string) $help,
                'max_length' => (int) $maxLength,
                'options' => $options,
            ];
            if ($type === 'file') {
                $extensions = $field['allowed_extensions'] ?? self::FILE_EXTENSIONS;
                if (!is_array($extensions) || !$extensions || count($extensions) > count(self::FILE_EXTENSIONS)
                    || array_filter($extensions, fn ($value) => !is_string($value) || !in_array($value, self::FILE_EXTENSIONS, true))) {
                    $errors[$base.'.allowed_extensions'] = 'Choose supported file formats: JPG, PNG, PDF or XLSX.';
                    $extensions = [];
                }
                $size = $field['max_size_kb'] ?? self::MAX_FILE_SIZE_KB;
                $count = $field['max_files'] ?? self::MAX_FILES;
                foreach (['max_size_kb' => [$size, self::MAX_FILE_SIZE_KB], 'max_files' => [$count, self::MAX_FILES]] as $setting => [$value, $maximum]) {
                    if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1 || (int) $value > $maximum) {
                        $errors[$base.'.'.$setting] = 'Choose a whole number between 1 and '.$maximum.'.';
                    }
                }
                $normalized[array_key_last($normalized)] += [
                    'allowed_extensions' => array_values(array_unique($extensions)),
                    'max_size_kb' => (int) $size,
                    'max_files' => (int) $count,
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    /** Keep identifiers as strings, and store only keys present in the server's current definition. */
    public static function validate(array $definitions, array $values, string $prefix = 'fields', bool $allowFiles = false): array
    {
        $definitions = self::validateDefinitions($definitions, $prefix.'_configuration', $allowFiles);
        $data = [];
        $rules = [];
        $attributes = [];

        foreach ($definitions as $field) {
            $key = $field['key'];
            $value = $values[$key] ?? null;
            if ($field['type'] === 'file') {
                $data[$key] = $value ?? [];
                $rules[$key] = ['present', 'array', 'min:'.($field['required'] ? 1 : 0), 'max:'.$field['max_files']];
                $rules[$key.'.*'] = ['required', 'integer', 'min:1', 'distinct'];
                $attributes[$key] = $field['label'];
                continue;
            }
            $data[$key] = is_string($value) || is_int($value) ? trim((string) $value) : $value;
            $rules[$key] = [$field['required'] ? 'required' : 'nullable', 'string', 'max:'.$field['max_length']];
            $attributes[$key] = $field['label'];
            if ($field['type'] === 'email') {
                $rules[$key][] = 'email:rfc';
            }
            if ($field['type'] === 'select') {
                $rules[$key][] = Rule::in(array_column($field['options'], 'value'));
            }
        }

        $validator = Validator::make($data, $rules, [], $attributes);
        if ($validator->fails()) {
            $errors = [];
            foreach ($validator->errors()->messages() as $key => $messages) {
                $errors[$prefix === '' ? $key : $prefix.'.'.$key] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }

        return array_map(fn ($value) => $value === null ? '' : $value, $validator->validated());
    }

    public static function snapshotItems(array $definitions, array $values): array
    {
        $items = [];
        foreach ($definitions as $field) {
            $value = $values[$field['key'] ?? ''] ?? null;
            if (!is_scalar($value) || (string) $value === '') {
                continue;
            }
            if (($field['type'] ?? null) === 'select') {
                foreach ($field['options'] ?? [] as $option) {
                    if (is_array($option) && (string) ($option['value'] ?? '') === (string) $value) {
                        $value = $option['label'] ?? $value;
                        break;
                    }
                }
            }
            $items[] = ['label' => (string) ($field['label'] ?? $field['key']), 'value' => (string) $value];
        }

        return $items;
    }
}
