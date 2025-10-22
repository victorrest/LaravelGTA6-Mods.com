@props([
    'name',
    'value' => '',
    'inputId' => null,
    'holderId' => null,
    'placeholder' => 'Write something amazing…',
    'minHeight' => 280,
])

@php
    $inputId = $inputId ?? str($name)->snake('-') . '-input';
    $holderId = $holderId ?? str($name)->snake('-') . '-editor';
    $initialValue = old($name, $value ?? '');

    if (is_array($initialValue)) {
        $initialValue = json_encode($initialValue);
    }
@endphp

<div {{ $attributes->merge(['class' => 'space-y-2']) }}>
    <div
        class="border border-gray-200 rounded-xl bg-white shadow-sm transition focus-within:ring-2 focus-within:ring-pink-500"
        data-editorjs
        data-holder="{{ $holderId }}"
        data-input="#{{ $inputId }}"
        data-placeholder="{{ $placeholder }}"
        data-min-height="{{ $minHeight }}"
    >
        <div id="{{ $holderId }}" class="editorjs-holder px-4 py-3"></div>
    </div>
    <input type="hidden" id="{{ $inputId }}" name="{{ $name }}" value='{{ $initialValue }}'>
</div>
