<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $export->title }} · {{ config('casa.business_name') }}</title>
    <style>
        @page { margin: 28px 30px 36px; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #fffcf7; color: #232620; font-family: 'DejaVu Sans', system-ui, sans-serif; font-size: 9px; line-height: 1.4; }
        .export-page { padding: {{ $print ? '24px' : '0' }}; }
        .export-actions { display: flex; gap: 8px; justify-content: flex-end; margin-bottom: 16px; }
        .export-actions button { border: 1px solid #4f6a4e; border-radius: 8px; padding: 7px 14px; background: #4f6a4e; color: #fffcf7; font: inherit; font-size: 12px; font-weight: 600; cursor: pointer; }
        .export-actions .export-close { background: #fffcf7; color: #4f6a4e; }
        .export-header { border-bottom: 2px solid #b38745; padding-bottom: 8px; margin-bottom: 10px; }
        .export-brand { margin: 0; color: #7a3e14; font-size: 8px; font-weight: bold; letter-spacing: 1.2px; text-transform: uppercase; }
        .export-title { margin: 2px 0 0; font-size: 16px; }
        .export-context { margin: 4px 0 0; color: #67675f; }
        .export-notice { margin: 0 0 8px; padding: 6px 8px; border: 1px solid #d4af73; border-radius: 4px; background: #f3ebdd; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #f3ebdd; color: #334736; font-weight: bold; text-align: left; }
        th, td { padding: 4px 5px; border-bottom: 1px solid #dcd2c2; vertical-align: top; }
        .is-numeric { text-align: right; white-space: nowrap; }
        .export-empty { padding: 16px; color: #67675f; text-align: center; }
        @media screen { body { font-size: 12px; } .export-title { font-size: 22px; } .export-brand { font-size: 11px; } }
        @media print { .export-actions { display: none; } .export-page { padding: 0; } body { font-size: 9px; } }
    </style>
</head>
<body>
<main class="export-page">
    @if ($print)
        <div class="export-actions">
            <button type="button" class="export-close" onclick="window.close()">{{ __('Close') }}</button>
            <button type="button" onclick="window.print()">{{ __('Print') }}</button>
        </div>
    @endif
    <header class="export-header">
        <p class="export-brand">{{ config('casa.business_name') }}</p>
        <h1 class="export-title">{{ $export->title }}</h1>
        @foreach ($export->context as $line)
            <p class="export-context">{{ $line }}</p>
        @endforeach
        <p class="export-context">{{ __('Generated :time · Asia/Manila · :count records', ['time' => $generatedAt->format('M j, Y g:i A'), 'count' => number_format($total)]) }}</p>
    </header>
    @if ($total > $limit)
        <p class="export-notice">{{ __('Showing the first :limit of :total records. Use CSV or Excel for the full list.', ['limit' => number_format($limit), 'total' => number_format($total)]) }}</p>
    @endif
    <table>
        <thead>
            <tr>
                @foreach ($export->columns as $label => $type)
                    <th @class(['is-numeric' => in_array($type, ['number', 'money'], true)])>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $index => $value)
                        <td @class(['is-numeric' => in_array($types[$index], ['number', 'money'], true)])>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="export-empty" colspan="{{ count($export->columns) }}">{{ __('No records match these filters.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</main>
@if ($print)
    <script>window.addEventListener('load', () => window.print());</script>
@endif
</body>
</html>
