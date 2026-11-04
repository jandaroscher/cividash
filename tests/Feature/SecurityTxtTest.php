<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Feature;

use Tests\TestCase;

class SecurityTxtTest extends TestCase
{
    public function test_security_txt_is_served(): void
    {
        $response = $this->get('/.well-known/security.txt');

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Contact:', $response->getContent());
    }
}
