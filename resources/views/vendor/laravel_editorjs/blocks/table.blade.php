@php
    $rows = $data['content'] ?? [];
    $withHeadings = (bool) ($data['withHeadings'] ?? false);
@endphp

@if (! empty($rows))
    <div class="overflow-x-auto rounded-xl border border-gray-200">
        <table class="min-w-full divide-y divide-gray-200 text-left text-sm text-gray-700">
            <tbody class="divide-y divide-gray-100">
                @foreach ($rows as $row)
                    @php
                        $isHeadingRow = $loop->first && $withHeadings;
                        $cellTag = $isHeadingRow ? 'th' : 'td';
                        $cellClasses = $isHeadingRow
                            ? 'bg-gray-50 px-4 py-3 text-sm font-semibold uppercase tracking-wide text-gray-600'
                            : 'px-4 py-3 align-top';
                    @endphp
                    <tr>
                        @foreach ($row as $cell)
                            <<?php echo $cellTag; ?> class="{{ $cellClasses }}">
                                {!! $cell !!}
                            </<?php echo $cellTag; ?>>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
