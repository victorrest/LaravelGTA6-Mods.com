@php($service = $data['service'] ?? null)
@php($embedUrl = $data['embed'] ?? null)
@php($caption = $data['caption'] ?? null)

@if ($service && $embedUrl)
    <div class="editorjs-embed">
        <div class="editorjs-embed__frame">
            <iframe
                src="{{ $embedUrl }}"
                title="{{ ucfirst($service) }} embed"
                loading="lazy"
                allowfullscreen
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            ></iframe>
        </div>
        @if ($caption)
            <p class="editorjs-embed__caption">{!! $caption !!}</p>
        @endif
    </div>
@endif
