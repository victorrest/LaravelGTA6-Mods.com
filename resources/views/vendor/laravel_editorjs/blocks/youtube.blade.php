@php
    $embedUrl = $data['embed'] ?? null;
    $caption = $data['caption'] ?? null;
    $width = max((int) ($data['width'] ?? 580), 1);
    $height = max((int) ($data['height'] ?? 320), 1);
    $padding = round(($height / $width) * 100, 4);
@endphp

@if ($embedUrl)
    <div class="editorjs-embed my-6">
        <div class="relative w-full overflow-hidden rounded-xl bg-black" style="padding-bottom: {{ $padding }}%;">
            <iframe
                src="{{ e($embedUrl) }}"
                class="absolute inset-0 h-full w-full border-0"
                loading="lazy"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
            ></iframe>
        </div>
        @if (!empty($caption))
            <p class="mt-2 text-sm text-gray-500">{{ $caption }}</p>
        @endif
    </div>
@endif
