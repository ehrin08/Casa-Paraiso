<x-guest-layout>
    <x-slot name="eyebrow">Security &amp; privacy</x-slot>
    <x-slot name="title">Security &amp; Privacy Policy</x-slot>
    <x-slot name="subtitle">A plain-language overview of how Casa Paraiso handles information in this application.</x-slot>

    <div class="space-y-6 text-sm leading-7 text-casa-muted">
        <p class="rounded-2xl border border-casa-border bg-casa-sand px-4 py-3 text-casa-text">
            This starter policy describes the current Casa Paraiso web application. It is intended to make our practices easy to understand and does not replace legal advice.
        </p>

        <section aria-labelledby="information-heading">
            <h2 id="information-heading" class="font-editorial text-2xl font-semibold text-casa-text">Information we collect</h2>
            <p class="mt-2">Depending on how you use Casa Paraiso, we may handle:</p>
            <ul class="mt-2 list-disc space-y-1 ps-5">
                <li>account, contact, and profile details such as your name, email address, phone number, and password credentials;</li>
                <li>appointment details, treatment preferences, assigned therapist, status, and service notes;</li>
                <li>manually recorded transaction details such as amount, payment status, payment method, and transaction date; the application does not collect card numbers;</li>
                <li>ratings, comments, and the application’s simple sentiment and topic labels for feedback; and</li>
                <li>authentication and security identifiers, including session, password-reset, and Google sign-in information when you choose Google.</li>
            </ul>
        </section>

        <section aria-labelledby="use-heading">
            <h2 id="use-heading" class="font-editorial text-2xl font-semibold text-casa-text">How we use information</h2>
            <p class="mt-2">We use this information to authenticate accounts, schedule and manage visits, maintain service and payment records, respond to feedback, provide customer rewards, protect the application, and support the spa’s day-to-day decisions.</p>
        </section>

        <section aria-labelledby="access-heading">
            <h2 id="access-heading" class="font-editorial text-2xl font-semibold text-casa-text">Who can access it</h2>
            <p class="mt-2">Access is limited by workspace role. Customers can access their own account and visit information. Receptionists, therapists, administrators, and the protected super administrator receive only the operational access needed for their responsibilities.</p>
            <p class="mt-2">Casa Paraiso also relies on hosting and database providers to operate the application. If you select Google sign-in, Google processes the sign-in step under Google’s own privacy policy.</p>
        </section>

        <section aria-labelledby="security-heading">
            <h2 id="security-heading" class="font-editorial text-2xl font-semibold text-casa-text">Security and your choices</h2>
            <p class="mt-2">The application uses protected sessions, password hashing, verification, rate limits, HTTPS settings, role checks, and least-privilege database access. You can review or update available profile details, change your password, sign out, and request account deletion through the account controls provided by the application.</p>
        </section>

        <section aria-labelledby="contact-heading">
            <h2 id="contact-heading" class="font-editorial text-2xl font-semibold text-casa-text">Questions</h2>
            <p class="mt-2">For questions about your information or this policy, contact Casa Paraiso using the business details below.</p>
            <address class="mt-3 not-italic text-casa-text">
                @if ($applicationSettings->business_name)
                    <span class="block font-bold">{{ $applicationSettings->business_name }}</span>
                @endif
                @if ($applicationSettings->contact_email)
                    <a href="mailto:{{ $applicationSettings->contact_email }}" class="block font-semibold text-casa-primary underline decoration-casa-gold underline-offset-4 focus:outline-none focus:ring-2 focus:ring-casa-gold focus:ring-offset-2">{{ $applicationSettings->contact_email }}</a>
                @endif
                @if ($applicationSettings->contact_phone)
                    <a href="tel:{{ $applicationSettings->contact_phone }}" class="block font-semibold text-casa-primary underline decoration-casa-gold underline-offset-4 focus:outline-none focus:ring-2 focus:ring-casa-gold focus:ring-offset-2">{{ $applicationSettings->contact_phone }}</a>
                @endif
                @if ($applicationSettings->business_address)
                    <span class="mt-1 block">{{ $applicationSettings->business_address }}</span>
                @endif
            </address>
        </section>

        <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-xl border border-casa-border px-4 font-bold text-casa-primary transition hover:bg-casa-sand focus:outline-none focus:ring-2 focus:ring-casa-gold focus:ring-offset-2">Back to sign in</a>
    </div>
</x-guest-layout>
