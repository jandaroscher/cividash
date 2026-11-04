<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Unit;

use App\Filament\Fabricator\Layouts\LandingpageLayout;
use App\Filament\Fabricator\Layouts\SubpageLayout;
use Tests\TestCase;

class FabricatorLayoutTest extends TestCase
{
    /**
     * Test that LandingpageLayout returns the correct name.
     */
    public function test_landingpage_layout_returns_correct_name(): void
    {
        $this->assertEquals('landingpage', LandingpageLayout::getName());
    }

    /**
     * Test that SubpageLayout returns the correct name.
     */
    public function test_subpage_layout_returns_correct_name(): void
    {
        $this->assertEquals('subpage', SubpageLayout::getName());
    }
}
