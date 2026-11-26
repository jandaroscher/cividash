<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Tests\Unit\Support;

use App\Filament\Support\RichEditorConfig;
use Tests\TestCase;

class RichEditorConfigTest extends TestCase
{
    public function test_editor_offers_lead_and_small_text_but_no_free_font_size(): void
    {
        // The rendered toolbar needs a Livewire schema container, so check the list every editor uses.
        $buttons = RichEditorConfig::STANDARD_TOOLBAR;

        $this->assertContains('lead', $buttons);
        $this->assertContains('small', $buttons);
        $this->assertNotContains('textColor', $buttons);
    }
}
