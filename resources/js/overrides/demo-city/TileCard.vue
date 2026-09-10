<template>
  <!-- Demo City tenant override of TileCard.vue via the component-override
       registry. Composes the default TileCard via its named
       slots instead of forking the script logic:
       - #header: blue title bar with white heading + inverted tenant-name tag
         (was: plain heading + #badge slot).
       - #value: trend label moved up next to the value (was: rendered near
         the footer), followed by the default big-indicator markup.
       - #trend: emptied out, since #value now renders the trend instead.
       Everything else (indicators, years, lottie/image loading, subheader,
       hint, footer) renders unchanged through the base component. -->
  <TileCard
    :tile="tile"
    :is-iframe="isIframe"
    class="demo-city-card"
  >
    <template #header="{ header }">
      <div class="demo-city-title-bar">
        <span class="demo-city-title-text hyphens-auto">{{ header }}</span>
        <span
          v-if="tenantStore.name"
          class="demo-city-badge shrink-0"
        >{{ tenantStore.name }}</span>
      </div>
    </template>

    <template #value="{ indicators, years, currentYear, trendLabel, lottieUrl, imageUrl }">
      <!-- Trend moved next to the value/subheader (was rendered near the
           footer in the default TileCard) - "Wert/Trend umsortiert". -->
      <p
        v-if="years.length > 1 && currentYear !== years[0]"
        class="text-theme-base text-gray-400 font-semibold flex flex-row items-center gap-2 px-4 mb-2"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          width="16"
          height="17"
          viewBox="0 0 24 25"
          aria-hidden="true"
          class="text-gray-600 flex-shrink-0 relative -top-px"
        >
          <g transform="translate(0 1)">
            <path
              d="M12,0A12,12,0,1,1,0,12,12,12,0,0,1,12,0Z"
              fill="none"
            />
            <g transform="translate(0 15.48) rotate(-45)">
              <path
                d="M0,0H18.789"
                transform="translate(0 4.311)"
                fill="none"
                stroke="currentColor"
                stroke-linecap="round"
                stroke-width="3"
              />
              <path
                d="M0,0,4.359,4.359,0,8.719"
                transform="translate(14.705)"
                fill="none"
                stroke="currentColor"
                stroke-linecap="round"
                stroke-width="3"
              />
            </g>
          </g>
        </svg>
        <span>{{ trendLabel }}</span>
      </p>

      <template
        v-for="indicator in indicators"
        :key="indicator.id"
      >
        <IndicatorBig
          v-if="(indicator.type === 'big' || indicator.indicator_type === 'big' || indicator.indikatortyp === 'groß') && !lottieUrl && !imageUrl"
          :indicator="indicator"
          :current-year="currentYear"
        />
      </template>
    </template>

    <!-- Suppress the default trend block at the bottom - #value above
         already renders it next to the value. A template with genuinely
         empty content is optimized away by the Vue compiler (no slot
         entry gets generated at all), so the base falls back to its own
         default trend markup; {{ null }} forces a slot to actually be
         registered while rendering nothing. -->
    <template #trend>
      {{ null }}
    </template>
  </TileCard>
</template>

<script setup>
import TileCard from '../../components/TileCard.vue';
import IndicatorBig from '../../components/cards/indicators/IndicatorBig.vue';
import { useTenantStore } from '../../stores/tenant';

defineProps({
    tile: {
        type: Object,
        required: true,
    },
    isIframe: {
        type: Boolean,
        default: false,
    },
});

const tenantStore = useTenantStore();
</script>

<style scoped>
/* Demo City structural look: square corners + a red-ish accent border,
   applied via :deep since the border lives on the base TileCard's own root element. */
:deep(.shadow-card) {
    border-width: var(--card-border-width, 3px);
    border-style: solid;
    border-color: var(--card-border-color, #1465A4);
    overflow: hidden;
}

/* Demo City look: full-width blue title bar with white bold heading. */
.demo-city-title-bar {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 0.75rem;
    background-color: var(--accent-color, #1465A4);
    padding: 1rem;
}
.demo-city-title-text {
    color: #FFFFFF;
    font-weight: 800;
    font-size: 1.35rem;
    line-height: 1.12;
    letter-spacing: -0.01em;
}
/* Inverted tag inside the blue bar: white block, blue caps, kantig. */
.demo-city-badge {
    background-color: #FFFFFF;
    color: var(--accent-color, #1465A4);
    font-size: 0.7rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0.2rem 0.5rem;
    border-radius: 0;
    align-self: flex-start;
}
</style>
