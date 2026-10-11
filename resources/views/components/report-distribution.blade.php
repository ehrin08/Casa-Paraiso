@props(['id', 'title', 'distribution', 'caption'])

<section {{ $attributes->class(['reports-panel reports-distribution']) }} aria-labelledby="{{ $id }}-heading">
    <h2 id="{{ $id }}-heading">{{ __($title) }}</h2>
    <div class="reports-distribution-body">
        <div class="reports-donut">
            <svg viewBox="0 0 120 120" aria-hidden="true">
                <circle class="reports-donut-track" cx="60" cy="60" r="44" fill="none" stroke-width="18" />
                <g transform="rotate(-90 60 60)">
                    @foreach ($distribution['rows'] as $row)
                        @if ($row['count'] > 0)<circle class="reports-color--{{ $row['key'] }}" cx="60" cy="60" r="44" fill="none" stroke-width="18" pathLength="100" stroke-dasharray="{{ $row['percentage'] }} {{ 100 - $row['percentage'] }}" stroke-dashoffset="{{ -$row['offset'] }}" />@endif
                    @endforeach
                </g>
            </svg>
            <div class="reports-donut-total"><strong>{{ number_format($distribution['total']) }}</strong><span>{{ __($caption) }}</span></div>
        </div>
        <ul class="reports-legend" aria-label="{{ __(':title counts and percentages', ['title' => __($title)]) }}">
            @foreach ($distribution['rows'] as $row)<li><span class="reports-legend-dot reports-color--{{ $row['key'] }}" aria-hidden="true"></span><span>{{ ucfirst(str_replace('_', ' ', $row['key'])) }}</span><strong>{{ number_format($row['count']) }}</strong><span>{{ number_format($row['percentage'], 1) }}%</span></li>@endforeach
        </ul>
    </div>
    @if ($distribution['total'] === 0)<p class="reports-chart-empty">{{ __('No :records in this period.', ['records' => strtolower(__($caption))]) }}</p>@endif
</section>
