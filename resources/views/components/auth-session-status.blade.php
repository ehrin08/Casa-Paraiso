@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-2xl border border-casa-green/25 bg-casa-green/10 px-4 py-3 text-sm font-semibold text-casa-green']) }} role="status">
        {{ $status }}
    </div>
@endif
