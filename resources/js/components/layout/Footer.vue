<template>
  <footer class="mt-12">
    <!-- Sponsors (above the gray nav area, white background) -->
    <div
      v-if="footerStore.sponsors && footerStore.sponsors.length > 0"
      class="container text-center md:text-left"
    >
      <hr class="my-9 border border-gray-400">
      <div class="max-w-[287px] md:max-w-none mx-auto mb-9">
        <div class="mx-auto max-w-[195px] md:max-w-none grid md:grid-cols-4 gap-9 lg:gap-11">
          <template
            v-for="(sponsor, index) in footerStore.sponsors"
            :key="index"
          >
            <div
              class="flex items-center"
              :class="sponsorAlignment(index)"
            >
              <a
                v-if="sponsor.url"
                :href="sponsor.url"
                target="_blank"
                rel="noopener noreferrer"
                :title="sponsor.name || ''"
              >
                <img
                  :src="getSponsorImageUrl(sponsor.image)"
                  :alt="sponsor.name || (locale === 'en' ? 'Sponsor' : 'Förderer')"
                  width="194"
                  height="57"
                  loading="lazy"
                >
              </a>
              <img
                v-else
                :src="getSponsorImageUrl(sponsor.image)"
                :alt="sponsor.name || (locale === 'en' ? 'Sponsor' : 'Förderer')"
                width="194"
                height="57"
                loading="lazy"
              >
            </div>
          </template>
        </div>
      </div>
    </div>

    <!-- Gray bottom area: Nav Links + Social Icons -->
    <div
      class="pt-9 pb-8 md:pt-11 md:pb-10"
      :style="{ backgroundColor: brandingStore.footerBackgroundColor || '#E5E7EB' }"
    >
      <div class="container text-center md:text-left">
        <div class="max-w-[287px] md:max-w-none mx-auto">
          <div
            class="md:flex flex-wrap md:justify-center md:space-x-5 xl:space-x-0 xl:grid xl:grid-cols-6 space-y-5 md:space-y-0"
            style="color: var(--text-primary-color, #000000);"
          >
            <!-- Nav Items -->
            <div
              v-for="(item, index) in footerStore.footerNavigationItems"
              :key="index"
            >
              <component
                :is="shouldUseAnchor(item) ? 'a' : 'RouterLink'"
                v-bind="linkAttrs(item)"
                class="group transition-colors duration-200 footer-link"
              >
                <svg
                  v-if="isExternalLink(item)"
                  class="inline-block -top-px relative"
                  xmlns="http://www.w3.org/2000/svg"
                  width="20"
                  height="20"
                  viewBox="0 0 20 20"
                >
                  <path
                    class="footer-globe-stroke transition-colors duration-200"
                    d="M21,12a9,9,0,0,1-9,9m9-9a9,9,0,0,0-9-9m9,9H3m9,9a9,9,0,0,1-9-9m9,9c1.657,0,3-4.029,3-9s-1.343-9-3-9m0,18c-1.657,0-3-4.029-3-9s1.343-9,3-9M3,12a9,9,0,0,1,9-9"
                    transform="translate(-2 -2)"
                    fill="none"
                    stroke="#191919"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                  />
                </svg>
                <span :class="{ 'ml-2': isExternalLink(item) }">{{ item.label }}</span>
              </component>
            </div>

            <!-- Social Links (in the same grid row, right-aligned) -->
            <div
              v-if="footerStore.socialLinksEnabled && activeSocialLinks.length > 0"
              class="basis-full lg:col-span-2 flex justify-center xl:justify-end pt-7 md:pt-12 xl:pt-0"
            >
              <div class="flex flex-wrap space-x-5">
                <a
                  v-for="(social, index) in activeSocialLinks"
                  :key="index"
                  :href="social.url || social.link"
                  target="_blank"
                  rel="noopener noreferrer"
                  :title="social.title || social.platform || ''"
                  class="social-icon-link"
                >
                  <img
                    v-if="social.icon"
                    :src="resolveStorageUrl(social.icon)"
                    :alt="social.title || social.platform || ''"
                    class="h-8 w-auto"
                  >
                  <SocialIcon
                    v-else
                    :platform="social.platform"
                  />
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useFooterStore } from '../../stores/footer';
import { useBrandingStore } from '../../stores/branding';
import { useLocale } from '../../composables/useLocale';
import { getApiBaseUrl } from '../../utils/api';
import SocialIcon from './SocialIcon.vue';

const footerStore = useFooterStore();
const brandingStore = useBrandingStore();
const { currentLocale: locale } = useLocale();

const activeSocialLinks = computed(() => {
    return (footerStore.socialLinks || []).filter(
        (s) => (s.url || s.link) && s.is_active !== false
    );
});

function sponsorAlignment(index) {
    if (index === 0) return 'justify-start';
    if (index === (footerStore.sponsors?.length || 1) - 1) return 'justify-end';
    return 'justify-center';
}

function shouldUseAnchor(item) {
    return /^(https?:|mailto:|tel:)/i.test(item.url || '');
}

function isExternalLink(item) {
    return /^https?:/i.test(item.url || '');
}

function linkAttrs(item) {
    if (shouldUseAnchor(item)) {
        return {
            href: item.url,
            ...(isExternalLink(item)
                ? { target: '_blank', rel: 'noopener noreferrer' }
                : {}),
        };
    }
    return { to: item.url };
}

function resolveStorageUrl(path) {
    if (!path) return '';
    if (path.startsWith('http://') || path.startsWith('https://')) return path;

    const apiUrl = getApiBaseUrl();
    let normalizedPath = path.startsWith('/') ? path.slice(1) : path;

    if (normalizedPath.startsWith('storage/')) {
        return `${apiUrl}/${normalizedPath}`;
    }
    return `${apiUrl}/storage/${normalizedPath}`;
}

const sponsorImageUrlMap = computed(() => {
    const map = new Map();
    (footerStore.sponsors || []).forEach((sponsor) => {
        if (sponsor.image) {
            map.set(sponsor.image, resolveStorageUrl(sponsor.image));
        }
    });
    return map;
});

function getSponsorImageUrl(imagePath) {
    if (!imagePath) return '';
    return sponsorImageUrlMap.value.get(imagePath) || '';
}

watch(
    () => locale.value,
    (newLocale) => {
        footerStore.fetchConfig(newLocale);
    },
    { immediate: true }
);
</script>

<style scoped>
footer a.footer-link:hover {
    color: var(--nav-hover-color) !important;
}

/* Globe icon stroke follows nav hover color */
footer a.footer-link:hover .footer-globe-stroke {
    stroke: var(--nav-hover-color) !important;
}

/* Social icons: grayscale by default, color on hover */
.social-icon-link img {
    filter: grayscale(100%);
    transition: filter 0.2s ease;
}
.social-icon-link:hover img {
    filter: grayscale(0%);
}
</style>
