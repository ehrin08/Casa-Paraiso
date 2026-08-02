<button {{ $attributes->merge(['type' => 'submit', 'class' => 'casa-button-primary']) }}>
    {{ $slot }}
</button>
