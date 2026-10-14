<template>
  <div
    ref="root"
    class="tile-embed-page p-1"
  >
    <template v-if="tile">
      <component
        :is="tileComponent"
        :tile="tile"
        :is-iframe="true"
      />
      <p class="mt-2 text-sm">
        {{ locale === 'en' ? 'Source:' : 'Quelle:' }}
        <a
          :href="tileUrl"
          target="_blank"
          rel="noopener"
          class="font-bold underline"
        >{{ headerStore.siteName || hostname }}</a>
      </p>
    </template>
    <p
      v-else-if="notFound"
      role="alert"
    >
      {{ locale === 'en' ? 'Tile not found.' : 'Kachel nicht gefunden.' }}
    </p>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useHead } from '@unhead/vue';
import { useTilesStore } from '../../stores/tiles';
import { useTenantStore } from '../../stores/tenant';
import { useHeaderStore } from '../../stores/header';
import { resolveTileComponent } from '../../lib/componentRegistry';

/**
 * Bare tile for third-party embedding (public/embed.js or a plain iframe).
 * Reports its height to the parent page so the loader can size the iframe.
 */
const route = useRoute();
const tilesStore = useTilesStore();
const tenantStore = useTenantStore();
const headerStore = useHeaderStore();
const hostname = window.location.hostname;

const root = ref(null);
const tile = ref(null);
const notFound = ref(false);

const locale = computed(() => (route.meta?.locale === 'en' ? 'en' : 'de'));
// Embeds credit the dashboard and link back to the full tile page.
const tileUrl = computed(() => `${locale.value === 'en' ? '/en' : ''}/tiles/${encodeURIComponent(route.params.slug)}`);
const tileComponent = computed(() => resolveTileComponent(tenantStore.themeSlug));

// The dashboard's own tile page is the canonical URL, keep embeds out of search results.
useHead({ meta: [{ name: 'robots', content: 'noindex' }] });

async function loadTile() {
    notFound.value = false;
    tile.value = await tilesStore.fetchBySlug(route.params.slug, locale.value, true);
    notFound.value = !tile.value;
}

function postHeight() {
    if (!root.value || window.parent === window) return;
    // The height is not sensitive and the embedding origin is unknown, hence '*'.
    window.parent.postMessage(
        { type: 'cividash:resize', height: Math.ceil(root.value.getBoundingClientRect().height) },
        '*',
    );
}

let observer = null;

onMounted(() => {
    loadTile();
    if (typeof ResizeObserver !== 'undefined') {
        observer = new ResizeObserver(postHeight);
        observer.observe(root.value);
    }
});

onBeforeUnmount(() => observer?.disconnect());

watch(() => [route.params.slug, locale.value], loadTile);
</script>
