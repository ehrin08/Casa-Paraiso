@props(['user'])

@inject('userPhotos', 'App\Services\UserPhotoService')
@php $photoUrl = $userPhotos->url($user->photo_path); @endphp

{{-- Callers keep their own avatar size and shape classes; a photo fills that frame. --}}
<span {{ $attributes->class(['casa-user-avatar', 'has-photo' => $photoUrl]) }} aria-hidden="true">
    @if ($photoUrl)
        <img src="{{ $photoUrl }}" alt="" width="96" height="96" loading="lazy" decoding="async">
    @else
        {{ $user->initials() }}
    @endif
</span>
