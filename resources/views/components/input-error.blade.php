@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'casa-error space-y-1']) }} role="alert">
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
