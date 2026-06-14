<?php

namespace Tests\Feature;

use Tests\TestCase;

class DemoAccountsTest extends TestCase
{
    public function test_demo_accounts_remain_enabled_when_debug_mode_is_disabled(): void
    {
        config([
            'app.debug' => false,
            'demo.accounts_enabled' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('window.__APP_DEBUG__ = false', false)
            ->assertSee('window.__DEMO_ACCOUNTS_ENABLED__ = true', false);
    }
}
