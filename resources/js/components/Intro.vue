<template>
    <div class="container">
        <h1 class="text-4xl lg:text-5xl text-black font-bold mb-6 hyphens-auto">
            {{ introHeader }}
        </h1>

        <div class="lg:grid lg:grid-cols-3 lg:gap-10 mb-10 lg:mb-14">
            <div class="col-span-2">
                <h2 class="text-3xl text-black font-bold mb-6 lg:mb-12 hyphens-auto">
                    {{ introSubheader }}
                </h2>
                
                <img
                    v-if="sdgLogoUrl && currentLocale === 'de'"
                    alt="Ziele für nachhaltige Entwicklung"
                    class="lg:hidden mb-6 block"
                    :src="sdgLogoUrl"
                    width="299"
                    height="90"
                    loading="lazy"
                />
                <img
                    v-if="sdgLogoEnUrl && currentLocale === 'en'"
                    alt="Sustainable Development Goals"
                    class="lg:hidden mb-6 block"
                    :src="sdgLogoEnUrl"
                    width="299"
                    height="90"
                    loading="lazy"
                />

                <p
                    v-for="(text, index) in introTexts"
                    :key="index"
                    class="text-xl text-black mb-5"
                >
                    {{ text }}
                </p>
            </div>
            <div class="col-span-1">
                <img
                    v-if="sdgLogoUrl && currentLocale === 'de'"
                    alt="Ziele für nachhaltige Entwicklung"
                    class="hidden lg:block mb-10"
                    :src="sdgLogoUrl"
                    width="364"
                    height="109"
                    loading="lazy"
                />
                <img
                    v-if="sdgLogoEnUrl && currentLocale === 'en'"
                    alt="Sustainable Development Goals"
                    class="hidden lg:block mb-10"
                    :src="sdgLogoEnUrl"
                    width="364"
                    height="109"
                    loading="lazy"
                />
                <img
                    v-if="introImageUrl"
                    alt=""
                    class="hidden lg:block"
                    :src="introImageUrl"
                    width="329"
                    height="362"
                    loading="lazy"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useBrandingStore } from '../stores/branding';

const branding = useBrandingStore();

const currentLocale = computed(() =>
    navigator.language.startsWith('en') ? 'en' : 'de'
);

// TODO: These should come from branding store or content API
const introHeader = computed(() => {
    // Placeholder - should be configurable
    return currentLocale.value === 'de'
        ? 'Nachhaltigkeitsdashboard'
        : 'Sustainability Dashboard';
});

const introSubheader = computed(() => {
    // Placeholder - should be configurable
    return currentLocale.value === 'de'
        ? 'Über das Dashboard'
        : 'About the Dashboard';
});

const introTexts = computed(() => {
    // Placeholder - should be configurable via API
    return currentLocale.value === 'de'
        ? [
              'Dieses Dashboard zeigt wichtige Nachhaltigkeitsindikatoren.',
              'Die Daten werden regelmäßig aktualisiert.',
              'Erkunden Sie die verschiedenen Kategorien und Indikatoren.',
          ]
        : [
              'This dashboard shows important sustainability indicators.',
              'The data is updated regularly.',
              'Explore the different categories and indicators.',
          ];
});

const sdgLogoUrl = computed(() => {
    // TODO: Get from branding store or config
    return null;
});

const sdgLogoEnUrl = computed(() => {
    // TODO: Get from branding store or config
    return null;
});

const introImageUrl = computed(() => {
    // TODO: Get from branding store or config
    return null;
});
</script>

