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
 * Whether an image prop resolves to something renderable. The blocks accept an
 * image either as a plain path string or as an array (of which the first entry is
 * used), mirroring the normalisation in TextImageBlock.vue / IntroTextBlock.vue.
 *
 * @param {string|Array|undefined|null} image
 * @returns {boolean}
 */
function hasImage(image) {
    return Array.isArray(image) ? !!image[0] : !!image;
}

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
            // Mirror SliderBlock.vue's `slides` computed: it drops inactive items
            // (is_active === false) and any item without a title/description/image,
            // then renders `<section v-if="slides.length > 0">`. Checking only
            // items.length here would re-expose the empty-section bug (jump mark to an
            // empty section) for a slider whose items are all inactive or all blank.
            return Array.isArray(props.items) && props.items.some(
                item => item?.is_active !== false
                    && (item?.title || item?.description || hasImage(item?.image)),
            );
        case 'faq':
            return Array.isArray(props.items) && props.items.some(item => !!item?.answer);
        case 'download':
            return Array.isArray(props.items) && props.items.some(item => item?.is_active !== false);
        case 'text-image':
            // TextImageBlock.vue renders its image independently of heading/text,
            // so an image-only block still has visible content.
            return !!(props.text || props.heading || hasImage(props.image));
        case 'intro-text':
            // IntroTextBlock.vue renders heading, subheading, text, image and
            // image_secondary independently of one another.
            return !!(
                props.text
                || props.heading
                || props.subheading
                || hasImage(props.image)
                || hasImage(props.image_secondary)
            );
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
