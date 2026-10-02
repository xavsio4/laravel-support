<?php

namespace FifteenPeas\Support\Tests\Feature;

use FifteenPeas\Support\Tests\TestCase;
use FifteenPeas\Support\Widget;
use Illuminate\Support\Facades\Blade;

class WidgetTest extends TestCase
{
    public function test_the_script_is_served_and_cached_for_good_at_its_versioned_url(): void
    {
        $this->get('/support/api/widget.js?v='.Widget::version())
            ->assertOk()
            ->assertHeader('Content-Type', 'text/javascript; charset=utf-8')
            ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');

        $this->get('/support/api/widget.js')->assertHeader('Cache-Control', 'max-age=300, public');
    }

    public function test_the_directive_renders_the_tag_with_options(): void
    {
        $html = Blade::render("@supportWidget(['position' => 'left', 'locale' => null])");

        $this->assertStringContainsString('src="http://localhost/support/api/widget.js?v='.Widget::version().'"', $html);
        $this->assertStringContainsString('data-position="left"', $html);
        $this->assertStringContainsString('data-app-name="Acme"', $html);
        $this->assertStringContainsString('data-endpoint="/support/api"', $html);
        $this->assertStringNotContainsString('data-locale', $html);
    }
}
