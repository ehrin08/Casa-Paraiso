@props(['feedUrl', 'copyUrl', 'shiftUrlPattern', 'publishUrlPattern', 'initialWeek', 'staffProfileId' => null])

<section x-data="weeklyRoster({ feedUrl: @js($feedUrl), copyUrl: @js($copyUrl), shiftUrlPattern: @js($shiftUrlPattern), publishUrlPattern: @js($publishUrlPattern), initialWeek: @js($initialWeek), staffProfileId: @js($staffProfileId) })" x-init="load()" class="casa-card overflow-hidden" aria-label="{{ __('Weekly therapist roster') }}">
    <div class="flex flex-col gap-4 border-b border-casa-border p-4 sm:p-5 xl:flex-row xl:items-end xl:justify-between">
        <div>
            <p class="casa-section-label">{{ __('Therapist roster') }}</p>
            <h2 class="mt-1 font-display text-2xl font-black text-casa-text">{{ __('Weekly staff schedule') }}</h2>
            <p class="mt-2 text-sm text-casa-muted">{{ __('Prepare shifts safely, then publish the whole team roster when it is ready for booking.') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="casa-icon-button" x-on:click="moveWeek(-7)" aria-label="{{ __('Previous week') }}">‹</button>
            <p class="min-w-44 text-center text-sm font-black text-casa-text" x-text="weekLabel"></p>
            <button type="button" class="casa-icon-button" x-on:click="moveWeek(7)" aria-label="{{ __('Next week') }}">›</button>
            <button type="button" class="casa-button-secondary" x-on:click="copyPrevious()">{{ __('Copy previous week') }}</button>
            <button type="button" class="casa-button-primary" x-on:click="publish()" x-bind:disabled="loading || !hasDraft">{{ __('Publish roster') }}</button>
        </div>
    </div>
    <div class="flex flex-wrap gap-2 px-4 py-3 text-sm font-bold text-casa-muted sm:px-5">
        <span class="casa-filter-chip inline-flex items-center gap-1.5" x-bind:class="publishedAt ? 'text-casa-palm' : 'text-casa-cacao'">
            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                <path x-show="publishedAt" stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                <path x-show="!publishedAt" stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
            </svg>
            <span x-text="publishedAt ? '{{ __('Published') }}' : '{{ __('Not yet published') }}'"></span>
        </span>
        <span class="casa-filter-chip inline-flex items-center gap-1.5 text-casa-cacao" x-show="hasDraft">
            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
            <span>{{ __('Draft changes are not bookable') }}</span>
        </span>
        <span x-show="loading" role="status">{{ __('Loading…') }}</span>
    </div>
    <p class="mx-4 mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 sm:mx-5" x-show="error" x-text="error" role="alert"></p>
    <div class="hidden overflow-x-auto lg:block" tabindex="0" role="region" aria-label="{{ __('Scrollable weekly therapist roster') }}">
        <table class="min-w-[74rem] w-full border-collapse text-left">
            <thead class="bg-casa-sand/55"><tr><th class="sticky start-0 z-10 min-w-52 border-b border-e border-casa-border bg-casa-sand/55 p-3 text-sm font-black text-casa-text">{{ __('Therapist') }}</th><template x-for="day in days" :key="day.date"><th class="min-w-36 border-b border-e border-casa-border p-3 text-center text-sm font-black text-casa-text" x-text="day.label"></th></template></tr></thead>
            <tbody><template x-for="staff in resources" :key="staff.id"><tr><th scope="row" class="sticky start-0 z-10 border-b border-e border-casa-border bg-casa-paper p-3 align-top"><span class="block font-black text-casa-text" x-text="staff.name"></span><span class="mt-1 block text-xs font-semibold text-casa-muted" x-text="staff.subtitle"></span></th><template x-for="day in days" :key="day.date"><td class="border-b border-e border-casa-border p-2 align-top"><div class="space-y-2"><template x-for="shift in shiftsFor(staff.id, day.date)" :key="shift.id"><button type="button" class="block w-full rounded-lg bg-casa-palm/10 px-2 py-2 text-left text-xs font-black text-casa-palm hover:bg-casa-palm/20" x-on:click="removeShift(shift)" x-text="shiftLabel(shift)" :aria-label="`Remove ${shiftLabel(shift)} for ${staff.name}`"></button></template><button type="button" class="min-h-11 w-full rounded-lg border border-dashed border-casa-border px-2 text-xs font-black text-casa-cacao hover:border-casa-palm hover:text-casa-palm" x-on:click="openShift(staff, day)">{{ __('Add shift') }}</button></div></td></template></tr></template></tbody>
        </table>
    </div>
    <div class="space-y-3 p-4 lg:hidden"><template x-for="staff in resources" :key="staff.id"><details class="rounded-xl border border-casa-border bg-casa-paper p-3"><summary class="cursor-pointer font-black text-casa-text" x-text="staff.name"></summary><div class="mt-3 grid gap-2"><template x-for="day in days" :key="day.date"><div class="rounded-lg bg-casa-bg p-3"><p class="text-xs font-black uppercase text-casa-muted" x-text="day.label"></p><template x-for="shift in shiftsFor(staff.id, day.date)" :key="shift.id"><button type="button" class="mt-2 block w-full rounded bg-casa-palm/10 px-2 py-2 text-left text-xs font-black text-casa-palm" x-on:click="removeShift(shift)" x-text="shiftLabel(shift)"></button></template><button type="button" class="mt-2 min-h-11 text-sm font-black text-casa-cacao" x-on:click="openShift(staff, day)">{{ __('Add shift') }}</button></div></template></div></details></template></div>
    <x-modal name="weekly-roster-shift" maxWidth="md" focusable><form class="p-6" x-on:submit.prevent="saveShift()"><p class="casa-section-label">{{ __('Draft shift') }}</p><h3 class="mt-1 font-display text-2xl font-black text-casa-text" x-text="selection?.staff?.name"></h3><p class="mt-1 text-sm text-casa-muted" x-text="selection?.day?.label"></p><div class="mt-5 grid gap-4 sm:grid-cols-2"><label><span class="casa-label">{{ __('Start') }}</span><select class="casa-input mt-1.5" x-model="startTime"><template x-for="time in times" :key="time"><option :value="time" x-text="timeLabel(time)"></option></template></select></label><label><span class="casa-label">{{ __('End') }}</span><select class="casa-input mt-1.5" x-model="endTime"><template x-for="time in endTimes" :key="time"><option :value="time" x-text="timeLabel(time)"></option></template></select></label></div><p class="mt-3 text-sm text-red-700" x-show="modalError" x-text="modalError"></p><div class="mt-6 flex gap-3"><button class="casa-button-primary flex-1">{{ __('Save draft shift') }}</button><button type="button" class="casa-button-secondary" x-on:click="$dispatch('close-modal', 'weekly-roster-shift')">{{ __('Cancel') }}</button></div></form></x-modal>
</section>
