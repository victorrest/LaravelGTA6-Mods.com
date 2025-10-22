@php
    $items = $data['items'] ?? [];
    $tag = ($data['style'] ?? 'unordered') === 'ordered' ? 'ol' : 'ul';
    $baseClasses = 'space-y-2 pl-6 text-gray-700';
    $listClasses = $tag === 'ol'
        ? $baseClasses . ' list-decimal'
        : $baseClasses . ' list-disc';
@endphp

@if (! empty($items))
    <<?php echo $tag; ?> class="{{ $listClasses }}">
        @foreach ($items as $item)
            <li class="leading-7">
                {!! $item !!}
            </li>
        @endforeach
    </<?php echo $tag; ?>>
@endif

