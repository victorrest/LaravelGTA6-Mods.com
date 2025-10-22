@props([
    'name',
    'id' => null,
    'value' => '',
    'placeholder' => '',
    'required' => false,
    'plainText' => '',
])

@php
    $fieldId = $id ?? $name;
    $initialValue = $value;

    if (is_array($initialValue)) {
        $initialValue = json_encode($initialValue, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    $initialValue = $initialValue ?: '';
    $initialPlain = $plainText ?? '';
@endphp

<div {{ $attributes->class(['space-y-3']) }}>
    <div id="{{ $fieldId }}__holder" class="editorjs-holder"></div>
    <input
        type="hidden"
        name="{{ $name }}"
        id="{{ $fieldId }}"
        value="{{ $initialValue }}"
        data-editorjs="true"
        data-holder="{{ $fieldId }}__holder"
        @if($placeholder) data-placeholder="{{ $placeholder }}" @endif
        @if($initialPlain !== '') data-initial-plain="{{ $initialPlain }}" @endif
        @if($required) required @endif
    >
    <p class="editorjs-error" data-editorjs-error="{{ $fieldId }}" hidden></p>
</div>
