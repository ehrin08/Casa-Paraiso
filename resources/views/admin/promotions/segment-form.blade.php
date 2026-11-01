<x-app-layout>
    <x-slot name="header">
        <div class="rewards-heading">
            <h1>{{ $segment->exists ? __('Edit :name', ['name' => $segment->name]) : __('Add RFM rule') }}</h1>
            <p>{{ __('Choose the score range a customer must fall in. Leave a range on "Any" to ignore that measure.') }}</p>
        </div>
        <a href="{{ route('admin.promotions.settings.edit') }}" class="casa-button-secondary">{{ __('Back to RFM rules') }}</a>
    </x-slot>

    @php
        $metrics = [
            'r' => ['label' => __('Recency'), 'help' => __('5 = visited most recently, 1 = longest since the last visit.')],
            'f' => ['label' => __('Frequency'), 'help' => __('5 = most paid visits, 1 = fewest.')],
            'm' => ['label' => __('Monetary'), 'help' => __('5 = spent the most, 1 = spent the least.')],
        ];
    @endphp

    <div class="rewards-workspace rewards-editor" data-rfm-segment-form>
        <form method="POST" action="{{ $segment->exists ? route('admin.promotions.segments.update', $segment) : route('admin.promotions.segments.store') }}">
            @csrf
            @if ($segment->exists)
                @method('PATCH')
            @endif
            <x-app-card>
                <div class="space-y-5">
                    <div>
                        <x-input-label for="name" :value="__('Segment name')" />
                        <x-text-input id="name" name="name" class="mt-2 block w-full" value="{{ old('name', $segment->name) }}" required maxlength="255" />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>
                    <div>
                        <x-input-label for="description" :value="__('Description')" />
                        <textarea id="description" name="description" class="casa-input mt-2 block min-h-20 w-full" maxlength="1000">{{ old('description', $segment->description) }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('description')" />
                    </div>

                    <fieldset>
                        <legend class="text-sm font-bold">{{ __('Score conditions') }}</legend>
                        <p class="mt-1 text-sm text-casa-muted">{{ __('A rule with every measure on "Any" matches all scored customers, so keep it last.') }}</p>
                        <div class="rewards-editor-groups">
                            @foreach ($metrics as $metric => $meta)
                                <section class="rewards-editor-group">
                                    <h3>{{ $meta['label'] }}</h3>
                                    <p>{{ $meta['help'] }}</p>
                                    <div class="mt-3 grid grid-cols-2 gap-3">
                                        @foreach (['min' => __('Lowest'), 'max' => __('Highest')] as $bound => $boundLabel)
                                            @php $field = "{$metric}_score_{$bound}"; @endphp
                                            <div class="rewards-filter-field">
                                                <x-input-label :for="$field" :value="$boundLabel" />
                                                <select id="{{ $field }}" name="{{ $field }}" class="casa-input">
                                                    <option value="">{{ __('Any') }}</option>
                                                    @foreach ([1, 2, 3, 4, 5] as $score)
                                                        <option value="{{ $score }}" @selected((string) old($field, $segment->{$field}) === (string) $score)>{{ $score }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endforeach
                                    </div>
                                    <x-input-error class="mt-2" :messages="[...$errors->get($metric.'_score_min'), ...$errors->get($metric.'_score_max')]" />
                                </section>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="rewards-filter-field">
                        <x-input-label for="addon_code" :value="__('Complimentary add-on reward')" />
                        <select id="addon_code" name="addon_code" class="casa-input">
                            <option value="">{{ __('No add-on (classify only)') }}</option>
                            @foreach ($addons as $addon)
                                <option value="{{ $addon->code }}" @selected(old('addon_code', $segment->addon_code) === $addon->code)>{{ $addon->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('addon_code')" />
                    </div>

                    <div class="rewards-filter-field">
                        <x-input-label for="recommended_membership_tier_id" :value="__('Recommend membership tier')" />
                        <select id="recommended_membership_tier_id" name="recommended_membership_tier_id" class="casa-input">
                            <option value="">{{ __('No recommendation') }}</option>
                            @foreach ($tiers as $tier)
                                <option value="{{ $tier->id }}" @selected((int) old('recommended_membership_tier_id', $segment->recommended_membership_tier_id) === $tier->id)>{{ $tier->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-casa-muted">{{ __('Customers in this segment without this card or better are listed on the Memberships page.') }}</p>
                        <x-input-error :messages="$errors->get('recommended_membership_tier_id')" />
                    </div>

                    <label class="rewards-active-control">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $segment->is_active)) class="rounded border-casa-border text-casa-primary focus:ring-casa-palm">
                        {{ __('Active') }}
                    </label>
                </div>
                <div class="rewards-editor-actions">
                    <a href="{{ route('admin.promotions.settings.edit') }}" class="casa-button-secondary">{{ __('Cancel') }}</a>
                    <button type="submit" class="casa-button-primary">{{ __('Save rule') }}</button>
                </div>
            </x-app-card>
        </form>
    </div>
</x-app-layout>
