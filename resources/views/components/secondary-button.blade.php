<button {{ $attributes->merge(['type' => 'button', 'class' => 'casa-button-secondary disabled:opacity-50']) }}>
    {{ $slot }}
</button>
