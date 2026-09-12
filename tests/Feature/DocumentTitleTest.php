<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class DocumentTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_layout_uses_the_explicit_page_title_before_the_header_title(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $html = Blade::render(<<<'BLADE'
            <x-app-layout page-title="Appointment detail">
                <x-slot name="header"><h1>Appointments</h1></x-slot>
            </x-app-layout>
        BLADE);

        $this->assertStringContainsString('<title>Appointment detail · Casa Paraiso</title>', $html);
    }

    public function test_app_layout_uses_its_header_heading_when_no_page_title_is_supplied(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $html = Blade::render(<<<'BLADE'
            <x-app-layout>
                <x-slot name="header"><h1>Bookings &amp; therapist coverage</h1></x-slot>
            </x-app-layout>
        BLADE);

        $this->assertStringContainsString('<title>Bookings &amp; therapist coverage · Casa Paraiso</title>', $html);
    }

    public function test_guest_layout_uses_its_heading_slot_for_the_document_title(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-guest-layout>
                <x-slot name="title">Sign in</x-slot>
            </x-guest-layout>
        BLADE);

        $this->assertStringContainsString('<title>Sign in · Casa Paraiso</title>', $html);
    }
}
