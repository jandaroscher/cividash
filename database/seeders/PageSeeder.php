<?php

// SPDX-FileCopyrightText: 2025-2026 JANDA+ROSCHER GmbH & Co. KG
//
// SPDX-License-Identifier: EUPL-1.2+

namespace Database\Seeders;

use App\Models\Page;
use Filament\Facades\Filament;
use Illuminate\Database\Seeder;

/**
 * Seeder for Fabricator Pages from reference site.
 *
 * Seeds public pages (Home, Kontakt, Download, Datenschutz, Impressum)
 * with neutral example content for the demo tenant.
 * Supports idempotent upserts and bilingual content (DE/EN).
 */
class PageSeeder extends Seeder
{
    /**
     * Perform idempotent create-or-update seeding of bilingual (de/en) public pages.
     *
     * Seeds pages defined by the seeder and returns a mapping from each page's German slug to its database ID.
     *
     * @return array<string,int> Map of DE slug to page database ID.
     */
    public function run(): array
    {
        $pageIdMap = [];

        // Define pages to seed based on reference site
        $pages = $this->getPagesDefinition();

        foreach ($pages as $pageData) {
            $slugDe = $pageData['slug']['de'];

            // Find existing page by DE slug, scoped to current Filament tenant
            // (global tenant scope is bypassed in console commands)
            $page = Page::whereTranslation('slug', 'de', $slugDe)
                ->when(Filament::getTenant(), fn ($q, $t) => $q->where('tenant_id', $t->id))
                ->first();

            if (! $page) {
                $page = new Page;
                $page->title = $pageData['title'];
                $page->slug = $pageData['slug'];
                $page->layout = $pageData['layout'];
                $page->setTranslation('blocks', 'de', $pageData['blocks']);
                // Set EN blocks if provided, otherwise use DE blocks
                if (isset($pageData['blocks_en'])) {
                    $page->setTranslation('blocks', 'en', $pageData['blocks_en']);
                } else {
                    $page->setTranslation('blocks', 'en', $pageData['blocks']);
                }
                if (isset($pageData['meta_description'])) {
                    $page->meta_description = $pageData['meta_description'];
                }
                $page->save();

                if ($this->command) {
                    $this->command->info("Page created: {$pageData['title']['de']} (ID: {$page->id})");
                }
            } else {
                // Update existing page if needed
                $needsUpdate = false;

                if ($page->getTranslation('title', 'de') !== $pageData['title']['de']) {
                    $page->setTranslation('title', 'de', $pageData['title']['de']);
                    $needsUpdate = true;
                }

                if (isset($pageData['title']['en']) && $page->getTranslation('title', 'en') !== $pageData['title']['en']) {
                    $page->setTranslation('title', 'en', $pageData['title']['en']);
                    $needsUpdate = true;
                }

                if ($page->getTranslation('slug', 'de') !== $pageData['slug']['de']) {
                    $page->setTranslation('slug', 'de', $pageData['slug']['de']);
                    $needsUpdate = true;
                }

                if (isset($pageData['slug']['en']) && $page->getTranslation('slug', 'en') !== $pageData['slug']['en']) {
                    $page->setTranslation('slug', 'en', $pageData['slug']['en']);
                    $needsUpdate = true;
                }

                if ($page->layout !== $pageData['layout']) {
                    $page->layout = $pageData['layout'];
                    $needsUpdate = true;
                }

                // Update blocks if structure changed (simple comparison)
                $currentBlocks = $page->getTranslation('blocks', 'de', false) ?? [];
                $currentBlocksEn = $page->getTranslation('blocks', 'en', false) ?? [];
                $blocksChanged = json_encode($currentBlocks) !== json_encode($pageData['blocks']);
                $blocksEnChanged = isset($pageData['blocks_en']) && json_encode($currentBlocksEn) !== json_encode($pageData['blocks_en']);

                if ($blocksChanged || $blocksEnChanged) {
                    $page->setTranslation('blocks', 'de', $pageData['blocks']);
                    // Set EN blocks if provided, otherwise use DE blocks
                    if (isset($pageData['blocks_en'])) {
                        $page->setTranslation('blocks', 'en', $pageData['blocks_en']);
                    } else {
                        $page->setTranslation('blocks', 'en', $pageData['blocks']);
                    }
                    $needsUpdate = true;
                }

                // Update meta_description if provided
                if (isset($pageData['meta_description'])) {
                    $currentMeta = $page->getTranslation('meta_description', 'de', false);
                    if ($currentMeta !== $pageData['meta_description']['de']) {
                        $page->meta_description = $pageData['meta_description'];
                        $needsUpdate = true;
                    }
                }

                if ($needsUpdate) {
                    $page->save();
                    if ($this->command) {
                        $this->command->info("Page updated: {$pageData['title']['de']} (ID: {$page->id})");
                    }
                }
            }

            $pageIdMap[$slugDe] = $page->id;
        }

        return $pageIdMap;
    }

    /**
     * Provide the list of page definitions used to seed the CMS from the reference site.
     *
     * Each entry is a page definition containing keys:
     * - `title`: ['de' => string, 'en' => string]
     * - `slug`: ['de' => string, 'en' => string]
     * - `layout`: string
     * - `blocks`: array of block definitions (DE)
     * - `blocks_en` (optional): array of block definitions (EN)
     * - `meta_description` (optional): ['de' => string, 'en' => string]
     *
     * @return array<int, array<string, mixed>> Indexed array of page definition arrays.
     */
    protected function getPagesDefinition(): array
    {
        return [
            // Home Page
            [
                'title' => [
                    'de' => 'Zukunftsbarometer Demo City',
                    'en' => 'Future Barometer Demo City',
                ],
                'slug' => [
                    'de' => '/',
                    'en' => '/',
                ],
                'layout' => 'landingpage',
                'blocks' => [
                    [
                        'type' => 'intro-text',
                        'data' => [
                            'heading' => 'Zukunftsbarometer Demo City',
                            'subheading' => 'Gemeinsam. Zukunft. Gestalten.',
                            'text' => '<p>Gemeinsam mit Ihnen wollen wir unsere Stadt fit für die Zukunft machen. Sie soll grüner, gerechter und produktiver und damit lebenswerter für alle werden.</p><p>Der Weg dorthin orientiert sich an den 17 Nachhaltigkeitszielen (Sustainable Development Goals) und wird gemeinsam mit den Bürgerinnen und Bürgern gestaltet.</p><p>Nachhaltige Entwicklung ist ein fortlaufender Prozess. Das Zukunftsbarometer begleitet diesen Prozess und macht Stärken und Schwächen sichtbar. Dadurch liefert es eine fundierte Basis für Entscheidungen auf dem Weg zu einer nachhaltigeren Zukunft.</p>',
                        ],
                    ],
                    [
                        'type' => 'card-grid',
                        'data' => [
                            'heading' => 'Welcher Bereich interessiert Sie?',
                            'tiles' => [],
                            'is_active' => true,
                        ],
                    ],
                ],
                'blocks_en' => [
                    [
                        'type' => 'intro-text',
                        'data' => [
                            'heading' => 'Future Barometer Demo City',
                            'subheading' => 'Together. Future. Shape.',
                            'text' => '<p>Together with you, we want to make our city fit for the future. It should become greener, fairer, and more productive, and thus more livable for everyone.</p><p>The path there follows the 17 Sustainable Development Goals (SDGs) and is shaped together with the citizens.</p><p>Sustainable development is an ongoing process. The Future Barometer accompanies this process and makes strengths and weaknesses visible. This provides a solid basis for decisions on the path to a more sustainable future.</p>',
                        ],
                    ],
                    [
                        'type' => 'card-grid',
                        'data' => [
                            'heading' => 'Which area are you interested in?',
                            'tiles' => [],
                            'is_active' => true,
                        ],
                    ],
                ],
                'meta_description' => [
                    'de' => 'Das Nachhaltigkeitsmonitoring für die Demo City.',
                    'en' => 'Sustainability monitoring for Demo City.',
                ],
            ],
            // Kontakt/Contact Page
            [
                'title' => [
                    'de' => 'Kontakt',
                    'en' => 'Contact',
                ],
                'slug' => [
                    'de' => 'kontakt',
                    'en' => 'contact',
                ],
                'layout' => 'subpage',
                'blocks' => [
                    [
                        'type' => 'section',
                        'data' => [
                            'title' => 'Koordinator für kommunale Entwicklungspolitik',
                        ],
                    ],
                    [
                        'type' => 'intro-text',
                        'data' => [
                            'heading' => 'Ansprechperson Kommunale Entwicklungspolitik',
                            'text' => '<p>Referat für Verwaltung<br/>Beispielstraße 1<br/>Zimmer: 2<br/>12345 Musterstadt</p><p>Telefon: <a href="tel:+491234567890">+49 123 4567890</a><br/>Fax: +49 123 4567891<br/>E-Mail: <a href="mailto:impressum@example.org">impressum@example.org</a></p>',
                        ],
                    ],
                ],
                'blocks_en' => [
                    [
                        'type' => 'section',
                        'data' => [
                            'title' => 'Coordinator for Municipal Development Policy',
                        ],
                    ],
                    [
                        'type' => 'intro-text',
                        'data' => [
                            'heading' => 'Ansprechperson Kommunale Entwicklungspolitik',
                            'text' => '<p>Administration Department<br/>Sample Street 1<br/>Room: 2<br/>12345 Sample City</p><p>Phone: <a href="tel:+491234567890">+49 123 4567890</a><br/>Fax: +49 123 4567891<br/>Email: <a href="mailto:impressum@example.org">impressum@example.org</a></p>',
                        ],
                    ],
                ],
                'meta_description' => [
                    'de' => 'Kontaktdaten und Ansprechpartner des Zukunftsbarometers Demo City.',
                    'en' => 'Contact details and key contacts for Zukunftsbarometer Demo City.',
                ],
            ],
            // Download Page
            [
                'title' => [
                    'de' => 'Download',
                    'en' => 'Download',
                ],
                'slug' => [
                    'de' => 'download',
                    'en' => 'download',
                ],
                'layout' => 'subpage',
                'blocks' => [
                    [
                        'type' => 'section',
                        'data' => [
                            'title' => 'Downloads',
                        ],
                    ],
                    [
                        'type' => 'intro-text',
                        'data' => [
                            'heading' => 'Nachhaltigkeitsbericht 2024 - Voluntary Local Review',
                            'text' => '<p>Der Nachhaltigkeitsbericht dokumentiert, wie die Stadt die 17 Ziele für nachhaltige Entwicklung (SDGs) lokal umsetzt, und dient zugleich der Berichterstattung an die Vereinten Nationen.</p><p>Neben Hintergrundinformationen zu den SDGs stellt der Bericht kommunale Projekte und Maßnahmen vor. Indikatoren und Diagramme veranschaulichen zentrale Entwicklungstrends und ermöglichen Zeitvergleiche.</p>',
                        ],
                    ],
                    [
                        'type' => 'intro-text',
                        'data' => [
                            'heading' => 'Daten und Informationen zur Methodik',
                            'text' => '<p>Alle Daten und Informationen zur Methodik sind hier als Download verfügbar.</p>',
                        ],
                    ],
                ],
                'blocks_en' => [
                    [
                        'type' => 'section',
                        'data' => [
                            'title' => 'Downloads',
                        ],
                    ],
                    [
                        'type' => 'intro-text',
                        'data' => [
                            'heading' => 'Sustainability Report 2024 - Voluntary Local Review',
                            'text' => '<p>The sustainability report documents how the city implements the 17 Sustainable Development Goals (SDGs) locally and also serves as reporting to the United Nations.</p><p>In addition to background information on the SDGs, the report presents municipal projects and measures. Indicators and diagrams illustrate key development trends and enable time comparisons.</p>',
                        ],
                    ],
                    [
                        'type' => 'intro-text',
                        'data' => [
                            'heading' => 'Data and Information on Methodology',
                            'text' => '<p>All data and information on the methodology are available here as downloads.</p>',
                        ],
                    ],
                ],
                'meta_description' => [
                    'de' => 'Laden Sie Daten, Berichte und Materialien vom Zukunftsbarometer Demo City herunter.',
                    'en' => 'Download data, reports, and materials from Zukunftsbarometer Demo City.',
                ],
            ],
            // Datenschutz/Privacy Page
            [
                'title' => [
                    'de' => 'Datenschutz',
                    'en' => 'Privacy',
                ],
                'slug' => [
                    'de' => 'datenschutz',
                    'en' => 'privacy',
                ],
                'layout' => 'subpage',
                'blocks' => $this->getPrivacyBlocks(),
                'blocks_en' => $this->getPrivacyBlocksEn(),
                'meta_description' => [
                    'de' => 'Informationen zum Datenschutz beim Zukunftsbarometer Demo City.',
                    'en' => 'Privacy policy information for Zukunftsbarometer Demo City.',
                ],
            ],
            // Impressum/Imprint Page
            [
                'title' => [
                    'de' => 'Impressum',
                    'en' => 'Imprint',
                ],
                'slug' => [
                    'de' => 'impressum',
                    'en' => 'imprint',
                ],
                'layout' => 'subpage',
                'blocks' => $this->getImprintBlocks(),
                'blocks_en' => $this->getImprintBlocksEn(),
                'meta_description' => [
                    'de' => 'Rechtliche Angaben und Impressum des Zukunftsbarometers Demo City.',
                    'en' => 'Legal notice and imprint of Zukunftsbarometer Demo City.',
                ],
            ],
        ];
    }

    /**
     * Provide the block definitions for the German privacy (Datenschutz) page.
     *
     * Each array item represents a block with keys such as `'type'` and `'data'`.
     *
     * @return array<int, array<string, mixed>> Array of block definitions for the German privacy page.
     */
    protected function getPrivacyBlocks(): array
    {
        return [
            [
                'type' => 'section',
                'data' => [
                    'title' => 'Datenschutzerklärung',
                ],
            ],
            [
                'type' => 'intro-text',
                'data' => [
                    'heading' => 'Allgemeine Informationen',
                    'text' => '<p>Die Musterstadt beachtet selbstverständlich die datenschutzrechtlichen Bestimmungen der EU-DSGVO und das Telemediengesetz. Verantwortlich für die Einhaltung der datenschutzrechtlichen Bestimmungen laut Datenschutz-Grundverordnung ist die Bürgermeisterin/der Bürgermeister.</p><p>Die Sicherheit Ihrer Daten ist uns wichtig, deshalb werden alle unsere Internetseiten mit einer Transportverschlüsselung (https) angeboten, damit die Daten nach dem aktuellen Stand der Technik sicher übertragen werden.</p>',
                ],
            ],
            [
                'type' => 'intro-text',
                'data' => [
                    'heading' => 'Zuständiger behördlicher Datenschutzbeauftragter',
                    'text' => '<p>Datenschutzbeauftragte(r)<br/>Beispielstraße 1<br/>12345 Musterstadt<br/>E-Mail: <a href="mailto:datenschutz@example.org">datenschutz@example.org</a><br/>Telefon: <a href="tel:+491234567890">+49 123 4567890</a><br/>Fax: +49 123 4567891</p>',
                ],
            ],
            [
                'type' => 'intro-text',
                'data' => [
                    'heading' => 'Bayerisches Datenschutzgesetz',
                    'text' => '<p>Wir unterliegen als öffentliche Stelle den Bestimmungen der EU-Datenschutzgrundverordnung (EU-DSGVO) sowie des Bayerischen Datenschutzgesetzes (BayDSG). Zum Schutz Ihrer Rechte haben wir technische und organisatorische Maßnahmen getroffen und auch sichergestellt, dass die Vorschriften über den Datenschutz von externen Dienstleistern beachtet werden, die an diesem Angebot mitwirken. Ihre Daten dürfen nur in dem Umfange verarbeitet werden, wie spezielle Gesetze dies zulassen oder Ihre Einwilligung vorliegt.</p>',
                ],
            ],
        ];
    }

    /**
     * Get imprint blocks content.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getImprintBlocks(): array
    {
        return [
            [
                'type' => 'section',
                'data' => [
                    'title' => 'Impressum',
                ],
            ],
            [
                'type' => 'intro-text',
                'data' => [
                    'heading' => 'Für allgemeine Fragen zur Stadtverwaltung wenden Sie sich bitte an:',
                    'text' => '<p>E-Mail: <a href="mailto:impressum@example.org">impressum@example.org</a><br/>Telefon: +49 123 4567890<br/>Fax: +49 123 4567891</p><p><strong>Musterstadt</strong></p><p>Postfach 00 00 00<br/>12345 Musterstadt</p>',
                ],
            ],
            [
                'type' => 'intro-text',
                'data' => [
                    'heading' => 'Herausgeber (gemäß § 5 TMG; § 55 II RStV):',
                    'text' => '<p><strong>Musterstadt</strong></p><p>Beispielstraße 1<br/>12345 Musterstadt</p><p>E-Mail: <a href="mailto:presse@example.org">presse@example.org</a><br/>Internet: <a href="https://www.example.org">www.example.org</a></p><p>Die Musterstadt ist eine Gebietskörperschaft des Öffentlichen Rechts.<br/>Sie wird vertreten durch die Bürgermeisterin/den Bürgermeister.</p><p>USt-Identifikationsnummer gemäß<br/>§ 27 a UStG: DE 000000000</p>',
                ],
            ],
            [
                'type' => 'intro-text',
                'data' => [
                    'heading' => 'Inhaltlich verantwortlich (nach § 55 II RStV):',
                    'text' => '<p><strong>Musterstadt</strong><br/><strong>Referat für Verwaltung - Koordination für kommunale Entwicklungspolitik</strong></p><p>Ansprechperson Kommunale Entwicklungspolitik</p><p>Beispielstraße 1<br/>12345 Musterstadt</p><p>Telefon: +49 123 4567890<br/>E-Mail: <a href="mailto:impressum@example.org">impressum@example.org</a></p>',
                ],
            ],
            [
                'type' => 'intro-text',
                'data' => [
                    'heading' => 'Konzept, Web-Design, Programmierung:',
                    'text' => '<p>Musteragentur GmbH</p><p>Beispielstraße 1<br/>12345 Musterstadt</p><p>Telefon: +49 123 4567890<br/>E-Mail: <a href="mailto:info@example.org">info@example.org</a></p><p><a href="https://www.example.org/">https://www.example.org/</a></p>',
                ],
            ],
        ];
    }

    /**
     * Provides the English content blocks for the Privacy page.
     *
     * @return array<int, array<string, mixed>> An ordered list of block definitions for the Privacy page in English.
     */
    protected function getPrivacyBlocksEn(): array
    {
        return [
            [
                'type' => 'section',
                'data' => [
                    'title' => 'Privacy Policy',
                ],
            ],
            [
                'type' => 'intro-text',
                'data' => [
                    'heading' => 'General Information',
                    'text' => '<p>The Sample City complies with the data protection regulations of the EU GDPR and the Telemedia Act. Responsible for compliance with data protection regulations according to the General Data Protection Regulation is the Mayor.</p><p>The security of your data is important to us, which is why all our websites are offered with transport encryption (https) so that data is transmitted securely according to the current state of the art.</p>',
                ],
            ],
            [
                'type' => 'intro-text',
                'data' => [
                    'heading' => 'Responsible Official Data Protection Officer',
                    'text' => '<p>Data Protection Officer<br/>Sample Street 1<br/>12345 Sample City<br/>Email: <a href="mailto:datenschutz@example.org">datenschutz@example.org</a><br/>Phone: <a href="tel:+491234567890">+49 123 4567890</a><br/>Fax: +49 123 4567891</p>',
                ],
            ],
            [
                'type' => 'intro-text',
                'data' => [
                    'heading' => 'Bavarian Data Protection Act',
                    'text' => '<p>As a public body, we are subject to the provisions of the EU General Data Protection Regulation (EU GDPR) and the Bavarian Data Protection Act (BayDSG). To protect your rights, we have taken technical and organizational measures and also ensured that data protection regulations are observed by external service providers who participate in this offering. Your data may only be processed to the extent that specific laws permit or your consent is given.</p>',
                ],
            ],
        ];
    }

    /**
     * Get imprint blocks content (English).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getImprintBlocksEn(): array
    {
        return [
            [
                'type' => 'section',
                'data' => [
                    'title' => 'Imprint',
                ],
            ],
            [
                'type' => 'intro-text',
                'data' => [
                    'heading' => 'For general questions about the city administration, please contact:',
                    'text' => '<p>Email: <a href="mailto:impressum@example.org">impressum@example.org</a><br/>Phone: +49 123 4567890<br/>Fax: +49 123 4567891</p><p><strong>Sample City</strong></p><p>P.O. Box 00 00 00<br/>12345 Sample City</p>',
                ],
            ],
            [
                'type' => 'intro-text',
                'data' => [
                    'heading' => 'Publisher (according to § 5 TMG; § 55 II RStV):',
                    'text' => '<p><strong>Sample City</strong></p><p>Sample Street 1<br/>12345 Sample City</p><p>Email: <a href="mailto:presse@example.org">presse@example.org</a><br/>Internet: <a href="https://www.example.org">www.example.org</a></p><p>The Sample City is a public law corporation.<br/>It is represented by the Mayor.</p><p>VAT identification number according to<br/>§ 27 a UStG: DE 000000000</p>',
                ],
            ],
            [
                'type' => 'intro-text',
                'data' => [
                    'heading' => 'Content responsible (according to § 55 II RStV):',
                    'text' => '<p><strong>Sample City</strong><br/><strong>Administration Department - Coordination for Municipal Development Policy</strong></p><p>Coordinator for Municipal Development Policy</p><p>Sample Street 1<br/>12345 Sample City</p><p>Phone: +49 123 4567890<br/>Email: <a href="mailto:impressum@example.org">impressum@example.org</a></p>',
                ],
            ],
            [
                'type' => 'intro-text',
                'data' => [
                    'heading' => 'Concept, Web Design, Programming:',
                    'text' => '<p>Sample Agency Ltd</p><p>Sample Street 1<br/>12345 Sample City</p><p>Phone: +49 123 4567890<br/>Email: <a href="mailto:info@example.org">info@example.org</a></p><p><a href="https://www.example.org/">https://www.example.org/</a></p>',
                ],
            ],
        ];
    }
}
