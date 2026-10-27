@props(['member', 'tier' => \App\Models\User::ORG_TIER_TEAM])

@inject('userPhotos', 'App\Services\UserPhotoService')
@php $photoUrl = $userPhotos->url($member->photo_path); @endphp

<article {{ $attributes->class(['landing-org-node', 'landing-org-node--'.$tier]) }} data-org-member>
    <div class="landing-org-portrait">
        @if ($photoUrl)
            <img src="{{ $photoUrl }}" alt="{{ __('Portrait of :name', ['name' => $member->name]) }}" width="480" height="480" loading="lazy" decoding="async">
        @else
            <span aria-hidden="true">{{ $member->initials() }}</span>
        @endif
    </div>
    <div class="landing-org-copy">
        <h3>{{ $member->name }}</h3>
        <p class="landing-org-title">{{ $member->publicTitle() }}</p>
        @if (filled($member->public_bio))
            <p class="landing-org-description">{{ $member->public_bio }}</p>
        @endif
    </div>
</article>
