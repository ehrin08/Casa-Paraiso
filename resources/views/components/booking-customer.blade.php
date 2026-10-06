<div class="booking-field-row">
    <div class="booking-customer-search" x-on:click.outside="customerSearchOpen = false">
        <x-input-label for="customer_name" :value="__('Customer name')" />
        <input id="customer_name" name="customer_name" type="text" class="casa-input mt-2" required maxlength="255" autocomplete="off"
            x-model="customerName" x-on:input="customerEdited()" x-on:keydown="customerKeydown($event)"
            x-on:focus="if (!customerId && customerName.trim().length >= 2) customerSearchOpen = true"
            role="combobox" aria-autocomplete="list" aria-controls="booking-customer-results" x-bind:aria-expanded="customerSearchOpen"
            x-bind:aria-activedescendant="customerSearchOpen && customerActiveIndex >= 0 ? 'booking-customer-option-' + customerActiveIndex : null"
            placeholder="{{ __('Type a name, or search an existing profile') }}">
        <input type="hidden" name="customer_profile_id" x-bind:value="customerId">
        <div class="booking-customer-results" x-show="customerSearchOpen" x-cloak>
            <p class="booking-customer-search-message" x-show="customerSearchLoading" role="status">{{ __('Searching customer profiles…') }}</p>
            <div x-show="customerSearchError" class="booking-customer-search-message" role="status"><p x-text="customerSearchError"></p><button type="button" class="booking-text-button" x-on:click="searchCustomers()">{{ __('Retry') }}</button></div>
            <div id="booking-customer-results" role="listbox" aria-label="{{ __('Matching customer profiles') }}">
                <template x-for="(customer, index) in customerResults" x-bind:key="customer.id">
                    <button type="button" role="option" tabindex="-1" class="booking-customer-result" x-bind:id="'booking-customer-option-' + index"
                        x-bind:aria-selected="index === customerActiveIndex" x-bind:class="{ 'is-active': index === customerActiveIndex }"
                        x-on:mousedown.prevent x-on:click="selectCustomer(customer)">
                        <strong x-text="customer.name"></strong><span x-text="(customer.phone || 'No phone') + ' · ' + customer.customer_code + ' · ' + (customer.is_guest ? 'Guest' : 'Enrolled')"></span>
                    </button>
                </template>
            </div>
            <p class="booking-customer-search-message" x-show="!customerSearchLoading && !customerSearchError && !customerResults.length">{{ __('No matching profiles. Continue to book this name as a new guest.') }}</p>
        </div>
        <p class="booking-customer-identity" x-show="customerId" x-text="(customerIsGuest ? 'Guest' : 'Enrolled') + ' · ' + customerCode"></p>
        <p class="mt-2 text-sm text-casa-muted" x-show="!customerId">{{ __('Select a matching profile to reuse it, or continue with a new guest.') }}</p>
        <x-input-error class="mt-2" :messages="$errors->get('customer_name')" />
        <x-input-error class="mt-2" :messages="$errors->get('customer_profile_id')" />
    </div>
    <div>
        <x-input-label for="customer_phone" :value="__('Phone number (optional)')" />
        <input id="customer_phone" name="customer_phone" type="tel" class="casa-input mt-2" maxlength="50" autocomplete="off" x-model="customerPhone" x-bind:readonly="!!customerId" placeholder="{{ __('Enter a phone number') }}">
        <p class="mt-2 text-sm text-casa-muted" x-show="customerId">{{ __('Using the selected profile’s saved contact details.') }}</p>
        <x-input-error class="mt-2" :messages="$errors->get('customer_phone')" />
    </div>
</div>
