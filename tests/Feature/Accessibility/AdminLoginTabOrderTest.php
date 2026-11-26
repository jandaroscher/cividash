<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature\Accessibility;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTabOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_fields_follow_the_document_tab_order(): void
    {
        $html = $this->get('/admin/login')->assertOk()->getContent();

        $this->assertStringContainsString('id="form.email"', $html);
        $this->assertDoesNotMatchRegularExpression('/tabindex="[1-9]/', $html);
    }
}
