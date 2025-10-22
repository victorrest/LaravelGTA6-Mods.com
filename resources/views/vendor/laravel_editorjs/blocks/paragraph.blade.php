@php
    $text = $data['text'] ?? '';
@endphp

@if ($text !== '')
    <p class="leading-7 text-gray-700 whitespace-pre-wrap break-words">
        {!! $text !!}
    </p>
@endif
