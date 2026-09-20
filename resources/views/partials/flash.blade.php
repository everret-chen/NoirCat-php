@if (session('status'))
    <div class="mb-5 rounded-md border border-frost-600/40 bg-frost-600/10 px-4 py-3 text-sm text-frost-300">
        {{ session('status') }}
    </div>
@endif

@if (session('error'))
    <div class="mb-5 rounded-md border border-red-900/60 bg-red-950/30 px-4 py-3 text-sm text-red-300">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any() && ! isset($hideErrorSummary))
    <div class="mb-5 rounded-md border border-red-900/60 bg-red-950/30 px-4 py-3 text-sm text-red-300">
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
