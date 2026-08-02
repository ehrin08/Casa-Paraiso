<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ModalInfrastructureTest extends TestCase
{
    public function test_modal_component_uses_the_shared_root_layer(): void
    {
        $html = Blade::render('<x-modal name="example-modal" focusable><button type="button">Example</button></x-modal>');

        $this->assertStringContainsString('x-teleport="body"', $html);
        $this->assertStringContainsString('data-modal-name="example-modal"', $html);
        $this->assertStringContainsString('x-data="casaModal({', $html);
        $this->assertStringContainsString('x-on:keydown.tab="handleTab($event)"', $html);
        $this->assertStringContainsString('x-cloak', $html);
        $this->assertStringContainsString('z-[100] isolate', $html);
        $this->assertStringContainsString('fixed inset-0 z-0 transform transition-all', $html);
        $this->assertStringContainsString('casa-card relative z-10', $html);
        $this->assertStringContainsString('backdrop-blur-sm', $html);
    }

    public function test_modal_asset_registers_central_state_and_scroll_locking(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($script);
        $this->assertStringContainsString("const modalStoreName = 'casaModal'", $script);
        $this->assertStringContainsString('Alpine.store(modalStoreName', $script);
        $this->assertStringContainsString('const modalStore = () => Alpine.store(modalStoreName);', $script);
        $this->assertStringNotContainsString('const modalStore = Alpine.store(', $script);
        $this->assertStringContainsString('window.casaModal', $script);
        $this->assertStringContainsString('syncBodyScrollLock', $script);
        $this->assertStringContainsString("window.addEventListener('open-modal'", $script);
        $this->assertStringContainsString("window.addEventListener('close-modal'", $script);
    }
}
