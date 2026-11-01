<x-app-layout>
    <x-slot name="header">
        <div class="rewards-heading">
            <h1>{{ $segment->exists ? __('Edit :name', ['name' => $segment->name]) : __('Add customer group') }}</h1>
            <p>{{ __('Describe which customers belong to this group. Leave a question on "Any" if it does not matter.') }}</p>
        </div>
        <a href="{{ route('admin.promotions.settings.edit') }}" class="casa-button-secondary">{{ __('Back to customer groups') }}</a>
    </x-slot>

    @inject('rfmWords', 'App\Services\RfmVocabulary')
    @php
        $metrics = [
            'r' => __('How long ago was their last visit?'),
            'f' => __('How often do they visit?'),
            'm' => __('How much have they spent in total?'),
        ];
        $previewActive = (bool) old('is_active', $segment->is_active);
    @endphp

    <div class="rewards-workspace rewards-editor" data-rfm-segment-form
        x-data="rfmSegmentPreview(@js($previewScores), @js($previewRulesAbove), @js($preview))">
        @if ($templates)
            <x-app-card class="mb-5">
                <div data-rfm-templates>
                    <h2 class="text-sm font-bold">{{ __('Start from a template') }}</h2>
                    <p class="mt-1 text-sm text-casa-muted">{{ __('A template fills in the form below. Nothing is saved until you choose Save group.') }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($templates as $key => $template)
                            <a href="{{ route('admin.promotions.segments.create', ['template' => $key]) }}" class="casa-button-secondary" @if (request('template') === $key) aria-current="true" @endif>{{ $template['name'] }}</a>
                        @endforeach
                        <a href="{{ route('admin.promotions.segments.create') }}" class="casa-button-secondary">{{ __('Start blank') }}</a>
                    </div>
                </div>
            </x-app-card>
        @endif
        <form method="POST" action="{{ $segment->exists ? route('admin.promotions.segments.update', $segment) : route('admin.promotions.segments.store') }}">
            @csrf
            @if ($segment->exists)
                @method('PATCH')
            @endif
            <x-app-card>
                <div class="space-y-5">
                    <div>
                        <x-input-label for="name" :value="__('Group name')" />
                        <x-text-input id="name" name="name" class="mt-2 block w-full" value="{{ old('name', $segment->name) }}" required maxlength="255" />
                        <x-input-error class="mt-2" :messages="$errors->get('name')" />
                    </div>
                    <div>
                        <x-input-label for="description" :value="__('Description')" />
                        <textarea id="description" name="description" class="casa-input mt-2 block min-h-20 w-full" maxlength="1000">{{ old('description', $segment->description) }}</textarea>
                        <x-input-error class="mt-2" :messages="$errors->get('description')" />
                    </div>

                    <fieldset>
                        <legend class="text-sm font-bold">{{ __('Who belongs in this group') }}</legend>
                        <p class="mt-1 text-sm text-casa-muted">{{ __('The amounts in brackets are what each choice means with today\'s customers. A group with every question on "Any" includes everyone, so keep it last.') }}</p>
                        <div class="rewards-editor-groups">
                            @foreach ($metrics as $metric => $question)
                                <section class="rewards-editor-group">
                                    <h3>{{ $rfmWords->metric($metric) }}</h3>
                                    <p>{{ $question }}</p>
                                    <div class="mt-3 grid grid-cols-2 gap-3">
                                        @foreach (['min' => __('From'), 'max' => __('To')] as $bound => $boundLabel)
                                            @php $field = "{$metric}_score_{$bound}"; @endphp
                                            <div class="rewards-filter-field">
                                                <x-input-label :for="$field" :value="$boundLabel" />
                                                <select id="{{ $field }}" name="{{ $field }}" class="casa-input">
                                                    <option value="">{{ __('Any') }}</option>
                                                    @foreach ($rfmWords->levels($metric) as $score => $levelName)
                                                        @php $hint = $rfmWords->valueHint($metric, $score, $cutoffs); @endphp
                                                        <option value="{{ $score }}" @selected((string) old($field, $segment->{$field}) === (string) $score)>{{ $hint ? "{$levelName} ({$hint})" : $levelName }}</option>
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

                    <div class="rewards-preview" aria-live="polite" data-rfm-preview>
                        <x-reward-icon name="info" />
                        <div>
                            <p>{{ __('Customers who fit right now:') }} <strong x-text="fits" data-rfm-preview-fits>{{ number_format($preview['fits']) }}</strong></p>
                            <p x-show="active" @unless ($previewActive) style="display: none" @endunless>
                                {{ __('Would join this group:') }} <strong x-text="lands" data-rfm-preview-lands>{{ number_format($preview['lands']) }}</strong>
                                <span x-show="fits > lands" @if ($preview['fits'] === $preview['lands']) style="display: none" @endif>{{ __('The others are already in a group higher in the list.') }}</span>
                            </p>
                            <p x-show="! active" @if ($previewActive) style="display: none" @endif>{{ __('Would join this group: none, because it is switched off.') }}</p>
                        </div>
                    </div>

                    <div class="rewards-filter-field">
                        <x-input-label for="addon_code" :value="__('Free add-on reward')" />
                        <select id="addon_code" name="addon_code" class="casa-input">
                            <option value="">{{ __('No reward (just group them)') }}</option>
                            @foreach ($addons as $addon)
                                <option value="{{ $addon->code }}" @selected(old('addon_code', $segment->addon_code) === $addon->code)>{{ $addon->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('addon_code')" />
                    </div>

                    <div class="rewards-filter-field">
                        <x-input-label for="recommended_membership_tier_id" :value="__('Recommend a membership card')" />
                        <select id="recommended_membership_tier_id" name="recommended_membership_tier_id" class="casa-input">
                            <option value="">{{ __('No recommendation') }}</option>
                            @foreach ($tiers as $tier)
                                <option value="{{ $tier->id }}" @selected((int) old('recommended_membership_tier_id', $segment->recommended_membership_tier_id) === $tier->id)>{{ $tier->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-casa-muted">{{ __('Customers in this group without this card or better are listed on the Memberships page.') }}</p>
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
                    <button type="submit" class="casa-button-primary">{{ __('Save group') }}</button>
                </div>
            </x-app-card>
        </form>
    </div>
</x-app-layout>
