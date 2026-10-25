<x-app-layout>
    <x-slot name="header">
        <div><p class="casa-section-label">{{ __('Therapist schedules') }}</p><h1 class="mt-2 font-display text-3xl font-black text-casa-text">{{ $editorTitle }}</h1></div>
        <a href="{{ $indexUrl }}" class="casa-button-secondary">{{ __('Back to schedule') }}</a>
    </x-slot>
    <div class="schedules-editor-page">
        @include('admin.staff-schedules.partials.editor')
    </div>
</x-app-layout>
