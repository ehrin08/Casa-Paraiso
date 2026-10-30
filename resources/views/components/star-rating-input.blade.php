@props([
    'name' => 'rating',
    'value' => null,
    'label' => null,
    'required' => true,
    'describedBy' => null,
])

@php $selected = filled($value) ? (int) $value : null; @endphp

<fieldset {{ $attributes->merge(['class' => 'casa-star-rating']) }} @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif data-star-rating>
    <legend class="casa-label">{{ $label ?? __('Rating') }}</legend>
    <div class="casa-star-rating-options">
        @for ($star = 1; $star <= 5; $star++)
            <input type="radio" id="{{ $name }}-{{ $star }}" name="{{ $name }}" value="{{ $star }}" class="sr-only" @checked($selected === $star) @required($required)>
            <label for="{{ $name }}-{{ $star }}">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 2.8 2.77 5.61 6.2.9-4.49 4.37 1.06 6.18L12 16.95l-5.54 2.91 1.06-6.18-4.49-4.37 6.2-.9L12 2.8Z"/></svg>
                <span class="sr-only">{{ trans_choice(':count star|:count stars', $star) }}</span>
            </label>
        @endfor
    </div>
    <p class="casa-star-rating-hint">{{ __('Select 1 to 5 stars.') }}</p>
</fieldset>
