@php
    $level = (int) ($data['level'] ?? 1);
    $tag = "h{$level}";

    $class = match($level) {
        1 => 'px-0 pt-2 pb-1 mb-3 text-4xl font-extrabold text-gray-900 break-words',
        2 => 'px-0 pt-2 pb-1 mb-3 text-2xl font-bold text-gray-900 break-words',
        3 => 'px-0 pt-2 pb-1 mb-2 text-xl font-semibold text-gray-900 break-words',
        4 => 'px-0 pt-2 pb-1 mb-2 font-semibold text-gray-900 break-words',
        5 => 'px-0 pt-2 pb-1 leading-5 text-gray-700 break-words',
        6 => 'px-0 pt-2 pb-1 leading-5 text-gray-700 break-words',
        default => ''
    };
@endphp

<{{ $tag }} class="{{ $class }}">{{ $data['text'] ?? '' }}</{{ $tag }}>
