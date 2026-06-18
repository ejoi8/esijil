@php
    /** @var \App\Models\CustomField $field */
    $name = "{$prefix}[{$field->key}]";
    $id = "{$prefix}_{$field->key}";
    $value = old("{$prefix}.{$field->key}");
    $type = $field->type->value;
    $inputType = match ($type) {
        'number' => 'number',
        'date' => 'date',
        'email' => 'email',
        default => 'text',
    };
@endphp
<div class="field">
    <label class="label" for="{{ $id }}">{{ $field->label }}</label>
    @if ($type === 'select')
        <div class="selectwrap">
            <select id="{{ $id }}" name="{{ $name }}" class="select" @required($field->required)>
                <option value="">—</option>
                @foreach (($field->options ?? []) as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </select>
            <svg width="16" height="16" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                <path d="M5 7.5L10 12.5L15 7.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
        </div>
    @elseif ($type === 'textarea')
        <textarea id="{{ $id }}" name="{{ $name }}" class="input" rows="3" @required($field->required)>{{ $value }}</textarea>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $inputType }}" class="input" value="{{ $value }}" @required($field->required)>
    @endif
    @if ($field->help_text)<p class="hint">{{ $field->help_text }}</p>@endif
    @error("{$prefix}.{$field->key}")<p class="err">{{ $message }}</p>@enderror
</div>
