@if ($errors->any())
    <div class="rounded-xl bg-rose-50 border border-rose-200 p-4 text-sm text-rose-700 space-y-2">
        <p class="font-semibold">Please fix the following:</p>
        <ul class="list-disc list-inside space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
