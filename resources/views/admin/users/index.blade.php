<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="casa-section-label">Protected administration</p>
            <h1 class="mt-2 font-display text-3xl font-black text-casa-text">User access</h1>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-casa-muted">Pre-authorize team emails and assign their workspace before team members sign in.</p>
        </div>
    </x-slot>

    @if (session('eligibility_conflicts'))
        <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-5" role="alert">
            <p class="text-sm font-extrabold text-red-800">{{ __('Resolve these confirmed appointments before changing therapist access') }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (session('eligibility_conflicts') as $conflict)
                    <a href="{{ $conflict['url'] }}" class="rounded-full border border-red-200 bg-white px-3 py-2 text-xs font-extrabold text-red-800 hover:border-red-400">
                        {{ $conflict['number'] }} · {{ \Illuminate\Support\Carbon::parse($conflict['starts_at'])->format('M d, g:i A') }}
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <div class="grid gap-5 xl:grid-cols-[22rem_minmax(0,1fr)]">
        <x-app-card>
            <p class="casa-section-label">Add access</p>
            <h2 class="mt-2 font-display text-xl font-black text-casa-text">Pre-authorize a user</h2>
            <form method="post" action="{{ route('admin.users.store') }}" class="mt-5 space-y-4">
                @csrf
                <div><x-input-label for="new-name" value="Full name"/><x-text-input id="new-name" name="name" class="mt-2" required/><x-input-error class="mt-2" :messages="$errors->get('name')"/></div>
                <div><x-input-label for="new-email" value="Sign-in email"/><x-text-input id="new-email" name="email" type="email" class="mt-2" required/><x-input-error class="mt-2" :messages="$errors->get('email')"/></div>
                <div><x-input-label for="new-role" value="Workspace"/><select id="new-role" name="role" class="casa-input mt-2" required>@foreach($assignableRoles as $role)<option value="{{ $role }}">{{ str($role)->replace('_', ' ')->title() }}</option>@endforeach</select></div>
                <label class="flex items-center gap-3 text-sm font-semibold"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" checked class="rounded border-casa-border text-casa-primary focus:ring-casa-gold"> Active account</label>
                <x-primary-button class="w-full justify-center">Pre-authorize email</x-primary-button>
            </form>
        </x-app-card>

        <div class="space-y-3">
            <x-list-toolbar eyebrow="{{ __('Access list') }}" title="{{ __('Authorized users') }}" :count="$users->total()" />
            @foreach($users as $managedUser)
                <form method="post" action="{{ route('admin.users.update', $managedUser) }}" class="casa-card grid gap-4 p-4 lg:grid-cols-[minmax(10rem,1fr)_minmax(13rem,1fr)_10rem_8rem_auto] lg:items-end">
                    @csrf @method('put')
                    <div><x-input-label :for="'name-'.$managedUser->id" value="Name"/><x-text-input :id="'name-'.$managedUser->id" name="name" class="mt-1" :value="$managedUser->name" :disabled="$managedUser->isSuperAdmin()" required/></div>
                    <div>
                        <x-input-label :for="'email-'.$managedUser->id" value="Sign-in email"/>
                        <x-text-input :id="'email-'.$managedUser->id" name="email" type="email" class="mt-1" :value="$managedUser->email" :disabled="$managedUser->isSuperAdmin() || filled($managedUser->google_id)" required/>
                        @if (filled($managedUser->google_id) && ! $managedUser->isSuperAdmin())
                            <input type="hidden" name="email" value="{{ $managedUser->email }}">
                            <p class="mt-1 text-xs leading-5 text-casa-muted">{{ __('Linked emails are updated only through Google sign-in.') }}</p>
                        @endif
                    </div>
                    <div><x-input-label :for="'role-'.$managedUser->id" value="Role"/><select :id="'role-'.$managedUser->id" name="role" class="casa-input mt-1" @disabled($managedUser->isSuperAdmin())>@if($managedUser->isSuperAdmin())<option value="super_admin">Super admin</option>@else @foreach($assignableRoles as $role)<option value="{{ $role }}" @selected($managedUser->role === $role)>{{ str($role)->title() }}</option>@endforeach @endif</select></div>
                    <label class="flex min-h-11 items-center gap-2 text-sm font-semibold"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($managedUser->is_active) @disabled($managedUser->isSuperAdmin()) class="rounded border-casa-border text-casa-primary focus:ring-casa-gold"> Active</label>
                    <div class="flex gap-2">@if(!$managedUser->isSuperAdmin())<x-primary-button>Save</x-primary-button>@else<span class="rounded-full bg-casa-sand px-3 py-2 text-xs font-extrabold text-casa-cacao">Protected</span>@endif @if($managedUser->isStaff() && $managedUser->staffProfile)<a class="casa-button-secondary" href="{{ route('admin.staff.edit', $managedUser->staffProfile) }}">Therapist profile</a>@endif</div>
                </form>
            @endforeach
            {{ $users->links() }}
        </div>
    </div>
</x-app-layout>
