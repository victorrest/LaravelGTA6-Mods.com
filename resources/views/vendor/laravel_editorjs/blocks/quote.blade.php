@php
    $class = '';

    if ('center' === $data['alignment']) {
        $class = 'text-center';
    } elseif ('left' === $data['alignment']) {
        $class = 'text-left';
    } else {
        $class = 'text-right';
    }
@endphp

@php
    $text = $data['text'] ?? '';
    $caption = $data['caption'] ?? '';
@endphp

@if ($text !== '')
    <figure class="rounded-2xl bg-gray-50 px-6 py-5 text-gray-700 shadow-inner">
        <blockquote class="space-y-3">
            <p class="{{ $class }} text-lg font-medium leading-relaxed">
                {!! $text !!}
            </p>
        </blockquote>
        @if ($caption !== '')
            <figcaption class="mt-3 text-sm font-semibold text-gray-500 {{ $class }}">
                — {!! $caption !!}
            </figcaption>
        @endif
    </figure>
@endif
