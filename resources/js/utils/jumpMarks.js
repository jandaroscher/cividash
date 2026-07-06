/**
 * Shared logic for deriving jump-mark sections from a tile's `background_blocks`.
 *
 * Both the tile-detail overlay header (which renders the jump-mark links) and the
 * overlay content (which renders the blocks and their section anchors) must agree on
 * which blocks get a section id and which don't - otherwise a jump-mark link can end
 * up pointing at an id that is never rendered ("springt ins Leere").
 *
 * Importing this single source of truth in both places guarantees they stay in sync.
 */

/**
 * Determines whether a background block actually renders visible content.
 * Mirrors the "empty" conditions of each block component so a jump mark is only
 * exposed when there is something to scroll to.
 *
 * @param {object} block
 * @returns {boolean}
 */
export function blockHasVisibleContent(block) {
    const props = block?.props ?? {};

    switch (block?.type) {
        case 'slider':
            return Array.isArray(props.items) && props.items.length > 0;
        case 'faq':
            return Array.isArray(props.items) && props.items.some(item => !!item?.answer);
        case 'download':
            return Array.isArray(props.items) && props.items.some(item => item?.is_active !== false);
        case 'text-image':
        case 'intro-text':
            return !!(props.text || props.heading);
        default:
            // Unknown/other block types: assume they render something rather than
            // hiding a valid jump mark for a type we don't explicitly know about.
            return true;
    }
}

/**
 * Builds the list of jump-mark sections for a tile's background blocks.
 *
 * A block only becomes a "section" (i.e. gets a stable section id that can be
 * jumped to) when it both declares a `jump_mark_label` AND actually has visible
 * content to scroll to.
 *
 * @param {Array<object>} backgroundBlocks
 * @returns {Array<{ index: number, sectionId: string, label: string|null, hasContent: boolean, block: object }>}
 */
export function buildJumpMarkSections(backgroundBlocks) {
    if (!Array.isArray(backgroundBlocks)) return [];

    return backgroundBlocks.map((block, index) => ({
        index,
        block,
        sectionId: `section-${index}`,
        label: block?.props?.jump_mark_label || null,
        hasContent: blockHasVisibleContent(block),
    }));
}

/**
 * Returns only the sections that should be shown as a jump-mark link, i.e. those
 * with a label AND actual content to jump to.
 *
 * @param {Array<object>} backgroundBlocks
 * @returns {Array<{ index: number, sectionId: string, label: string, hasContent: true, block: object }>}
 */
export function buildVisibleJumpMarks(backgroundBlocks) {
    return buildJumpMarkSections(backgroundBlocks).filter(section => section.label && section.hasContent);
}
