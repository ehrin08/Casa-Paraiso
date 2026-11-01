@props(['route', 'query' => [], 'label' => __('Export'), 'buttonClass' => 'casa-button-secondary', 'subject' => null])

@php
    $formats = [
        'csv' => [__('CSV'), __('Plain data for any spreadsheet')],
        'xlsx' => [__('Excel'), __('Formatted .xlsx workbook')],
        'pdf' => [__('PDF'), __('Printable document to share')],
        'print' => [__('Print'), __('Opens a print-ready page')],
    ];
@endphp

<details {{ $attributes->class('casa-export-menu') }} data-export-menu x-data
    x-on:click.outside="$el.open = false"
    x-on:keydown.escape="if ($el.open) { $el.open = false; $el.querySelector('summary').focus() }">
    <summary class="{{ $buttonClass }}" aria-label="{{ $subject ? __('Export :subject', ['subject' => $subject]) : $label }}">
        <x-payment-icon name="download" />
        <span>{{ $label }}</span>
        <svg class="casa-export-chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" /></svg>
    </summary>
    <ul class="casa-export-options" role="list">
        @foreach ($formats as $format => [$name, $hint])
            <li>
                <a href="{{ route($route, [...$query, 'format' => $format]) }}" data-turbo="false" data-export-format="{{ $format }}"
                    @if ($format === 'print') target="_blank" rel="noopener" @endif
                    x-on:click="$root.open = false">
                    <strong>{{ $name }}</strong>
                    <span>{{ $hint }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</details>
