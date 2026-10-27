@props(['member', 'tier' => \App\Models\User::ORG_TIER_TEAM])

@inject('userPhotos', 'App\Services\UserPhotoService')
@php
    $photoUrl = $userPhotos->url($member->photo_path);
    $modalName = 'team-member-'.$member->getKey();
    $title = $member->publicTitle();
@endphp

{{-- The chart shows portraits only; the name, title and bio open in a dialog. --}}
<button type="button" {{ $attributes->class(['landing-org-node', 'landing-org-node--'.$tier]) }} aria-label="{{ __('About :name, :title', ['name' => $member->name, 'title' => $title]) }}" aria-haspopup="dialog" x-data x-on:click="$dispatch('open-modal', @js($modalName))" data-org-member>
    <span class="landing-org-portrait">
        @if ($photoUrl)
            <img src="{{ $photoUrl }}" alt="{{ __('Portrait of :name', ['name' => $member->name]) }}" width="480" height="480" loading="lazy" decoding="async">
        @else
            <span aria-hidden="true">{{ $member->initials() }}</span>
        @endif
    </span>
</button>

<x-modal :name="$modalName" focusable centered max-width="md" :labelled-by="$modalName.'-name'" panel-class="landing-org-dialog-panel">
    <div class="landing-org-dialog" data-org-dialog>
        <button type="button" class="landing-org-dialog-close" aria-label="{{ __('Close dialog') }}" x-on:click="$dispatch('close-modal', @js($modalName))">
            <x-landing-icon name="close" />
        </button>
        <div class="landing-org-portrait landing-org-dialog-portrait">
            @if ($photoUrl)
                <img src="{{ $photoUrl }}" alt="" width="480" height="480" loading="lazy" decoding="async">
            @else
                <span aria-hidden="true">{{ $member->initials() }}</span>
            @endif
        </div>
        <h3 id="{{ $modalName }}-name">{{ $member->name }}</h3>
        <p class="landing-org-title">{{ $title }}</p>
        @if (filled($member->public_bio))
            <p class="landing-org-description">{{ $member->public_bio }}</p>
        @endif
    </div>
</x-modal>
