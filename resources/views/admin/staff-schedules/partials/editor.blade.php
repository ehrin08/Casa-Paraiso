<section class="schedules-editor" data-schedule-editor-content>
    <header class="schedules-editor-header">
        <div><p class="casa-section-label">{{ $start->format('M j') }} – {{ $start->copy()->addDays(6)->format('M j, Y') }} · Asia/Manila</p><h2 id="schedule-editor-title">{{ $editorTitle }}</h2></div>
        <button type="button" class="casa-icon-button" data-schedule-dialog-close aria-label="{{ __('Close schedule editor') }}">×</button>
    </header>
    <div class="schedules-editor-body">
        <x-input-error :messages="$errors->all()" />
        @if ($editor === 'shift')
            @include('admin.staff-schedules.partials.shift-form')
        @elseif ($editor === 'bulk')
            @include('admin.staff-schedules.partials.bulk-form')
        @else
            @include('admin.staff-schedules.partials.review')
        @endif
    </div>
</section>
