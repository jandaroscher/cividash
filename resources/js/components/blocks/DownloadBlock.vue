<template>
  <section class="py-12 md:py-16">
    <div class="container">
      <h2
        v-if="block.props.heading"
        class="text-theme-h2 font-bold mb-6"
      >
        {{ block.props.heading }}
      </h2>
      <div
        v-if="block.props.text"
        class="prose mb-6"
        v-html="block.props.text"
      />
      <div class="space-y-4">
        <div
          v-for="(item, index) in activeItems"
          :key="index"
        >
          <a
            :href="getFileUrl(item.file)"
            download
            class="inline-flex w-fit min-h-11 flex-row flex-wrap items-center gap-2 py-2 text-xl text-black group hyphens-auto"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
            >
              <g transform="translate(1 0)">
                <path
                  d="M20.275,74v3.77H1.752V74H0v5.523H22.027V74Z"
                  transform="translate(0 -55.523)"
                  :fill="accentColor"
                />
                <path
                  d="M19.614,0V15.249l-6.4-6.4-1.239,1.239L20.49,18.6l8.517-8.517L27.768,8.847l-6.4,6.4V0Z"
                  transform="translate(-8.976)"
                  :fill="accentColor"
                />
              </g>
              <rect
                width="24"
                height="24"
                fill="none"
              />
            </svg>
            <span class="text-black transition-colors duration-200 group-hover:text-accent break-words">
              {{ item.title || getFilenameWithoutExtension(item.file) }}
            </span>
            <span class="text-gray-400 transition-colors duration-200 group-hover:text-accent">
              {{ getFileExtension(item.file) }}<template v-if="fileSizes[getFileUrl(item.file)]"> | {{ fileSizes[getFileUrl(item.file)] }}</template>
            </span>
          </a>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive } from 'vue';
import { useBrandingStore } from '../../stores/branding';

const props = defineProps({
    block: {
        type: Object,
        required: true,
    },
});

const brandingStore = useBrandingStore();

const accentColor = computed(() => {
    return brandingStore.accentColor || 'var(--accent-color, #E30613)';
});

const activeItems = computed(() => {
    const items = props.block.props?.items || [];
    return items.filter((item) => item.is_active !== false);
});

const fileSizes = reactive({});

onMounted(async () => {
    for (const item of activeItems.value) {
        const url = getFileUrl(item.file);
        if (!url) continue;
        try {
            const response = await fetch(url, { method: 'HEAD' });
            const size = response.headers.get('content-length');
            if (size) {
                fileSizes[url] = formatFileSize(Number(size));
            }
        } catch {
            // File size not available
        }
    }
});

function getFileUrl(file) {
    if (!file) return '';
    if (file.startsWith('http://') || file.startsWith('https://') || file.startsWith('/')) {
        return file;
    }
    return `/storage/${file}`;
}

function getFilenameWithoutExtension(file) {
    if (!file) return '';
    const parts = file.split('/');
    const filename = parts[parts.length - 1];
    const dotIndex = filename.lastIndexOf('.');
    return dotIndex > 0 ? filename.substring(0, dotIndex) : filename;
}

function getFileExtension(file) {
    if (!file) return '';
    const parts = file.split('.');
    return parts.length > 1 ? parts[parts.length - 1].toUpperCase() : '';
}

function formatFileSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
</script>
