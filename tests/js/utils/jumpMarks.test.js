import { describe, it, expect } from 'vitest';
import {
    blockHasVisibleContent,
    buildJumpMarkSections,
    buildVisibleJumpMarks,
} from '@/utils/jumpMarks';

describe('jumpMarks utils', () => {
    describe('blockHasVisibleContent', () => {
        it('returns false for a slider block with no items', () => {
            expect(blockHasVisibleContent({ type: 'slider', props: { items: [] } })).toBe(false);
        });

        it('returns true for a slider block with items', () => {
            expect(blockHasVisibleContent({ type: 'slider', props: { items: [{ title: 'A' }] } })).toBe(true);
        });

        it('returns false for a faq block whose items have no answer', () => {
            expect(blockHasVisibleContent({
                type: 'faq',
                props: { items: [{ question: 'Q?' }, { question: 'Q2?', answer: '' }] },
            })).toBe(false);
        });

        it('returns true for a faq block with at least one answered item', () => {
            expect(blockHasVisibleContent({
                type: 'faq',
                props: { items: [{ question: 'Q?' }, { question: 'Q2?', answer: '<p>A</p>' }] },
            })).toBe(true);
        });

        it('returns false for a text-image block with no text/heading', () => {
            expect(blockHasVisibleContent({ type: 'text-image', props: {} })).toBe(false);
        });

        it('returns true for a text-image block with a heading only', () => {
            expect(blockHasVisibleContent({ type: 'text-image', props: { heading: 'Title' } })).toBe(true);
        });

        it('returns false for a slider whose items are all inactive', () => {
            expect(blockHasVisibleContent({
                type: 'slider',
                props: { items: [{ title: 'A', is_active: false }, { title: 'B', is_active: false }] },
            })).toBe(false);
        });

        it('returns false for a slider whose items have no title/description/image', () => {
            expect(blockHasVisibleContent({
                type: 'slider',
                props: { items: [{ link_url: 'https://example.com' }] },
            })).toBe(false);
        });

        it('returns true for a slider with an active image-only item', () => {
            expect(blockHasVisibleContent({
                type: 'slider',
                props: { items: [{ image: 'seeds/slider/x.jpg' }] },
            })).toBe(true);
        });

        it('returns true for a text-image block with an image only', () => {
            expect(blockHasVisibleContent({ type: 'text-image', props: { image: 'seeds/x.jpg' } })).toBe(true);
            expect(blockHasVisibleContent({ type: 'text-image', props: { image: ['seeds/x.jpg'] } })).toBe(true);
        });

        it('returns true for an intro-text block with only a subheading or image', () => {
            expect(blockHasVisibleContent({ type: 'intro-text', props: { subheading: 'Sub' } })).toBe(true);
            expect(blockHasVisibleContent({ type: 'intro-text', props: { image_secondary: 'seeds/x.jpg' } })).toBe(true);
        });

        it('returns false for an intro-text block with no renderable content', () => {
            expect(blockHasVisibleContent({ type: 'intro-text', props: {} })).toBe(false);
        });

        it('defaults to true for unknown block types', () => {
            expect(blockHasVisibleContent({ type: 'hero', props: {} })).toBe(true);
        });
    });

    describe('buildJumpMarkSections', () => {
        it('returns an empty array for non-array input', () => {
            expect(buildJumpMarkSections(null)).toEqual([]);
            expect(buildJumpMarkSections(undefined)).toEqual([]);
        });

        it('builds a stable index-based section id per block', () => {
            const blocks = [
                { type: 'text-image', props: { jump_mark_label: 'Background', text: 'x' } },
                { type: 'slider', props: { jump_mark_label: 'Our Commitment', items: [] } },
            ];
            const sections = buildJumpMarkSections(blocks);
            expect(sections).toHaveLength(2);
            expect(sections[0]).toMatchObject({ index: 0, sectionId: 'section-0', label: 'Background', hasContent: true });
            expect(sections[1]).toMatchObject({ index: 1, sectionId: 'section-1', label: 'Our Commitment', hasContent: false });
        });
    });

    describe('buildVisibleJumpMarks', () => {
        it('excludes blocks without a jump_mark_label', () => {
            const blocks = [{ type: 'text-image', props: { text: 'x' } }];
            expect(buildVisibleJumpMarks(blocks)).toHaveLength(0);
        });

        it('excludes labeled blocks with no visible content (regression)', () => {
            const blocks = [
                { type: 'text-image', props: { jump_mark_label: 'Hintergrund', text: 'x' } },
                { type: 'slider', props: { jump_mark_label: 'Unser Engagement', items: [] } },
            ];
            const marks = buildVisibleJumpMarks(blocks);
            expect(marks).toHaveLength(1);
            expect(marks[0].label).toBe('Hintergrund');
        });

        it('includes labeled blocks that do have visible content', () => {
            const blocks = [
                { type: 'slider', props: { jump_mark_label: 'Unser Engagement', items: [{ title: 'A' }] } },
            ];
            const marks = buildVisibleJumpMarks(blocks);
            expect(marks).toHaveLength(1);
            expect(marks[0]).toMatchObject({ sectionId: 'section-0', label: 'Unser Engagement' });
        });
    });
});
