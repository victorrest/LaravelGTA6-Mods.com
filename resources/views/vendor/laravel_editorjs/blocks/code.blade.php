@php
    $code = $data['code'] ?? '';
@endphp

@if ($code !== '')
    <pre class="overflow-x-auto rounded-2xl bg-slate-900 p-5 text-sm text-slate-100 shadow-lg">
        <code class="font-mono leading-6">{{ htmlspecialchars($code) }}</code>
    </pre>
@endif
