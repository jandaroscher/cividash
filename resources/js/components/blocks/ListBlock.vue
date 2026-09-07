<template>
  <section class="py-12 md:py-16">
    <div class="container">
      <ul
        v-if="block.props.list_type === 'bullet'"
        class="list-disc list-inside space-y-2"
      >
        <li
          v-for="(item, index) in block.props.items"
          :key="index"
        >
          {{ item.text }}
        </li>
      </ul>
      <ol
        v-else
        class="list-decimal list-inside space-y-2"
      >
        <li
          v-for="(item, index) in block.props.items"
          :key="index"
        >
          {{ item.text }}
        </li>
      </ol>
    </div>
  </section>
</template>

<script setup>
defineProps({
    block: {
        type: Object,
        required: true,
    },
});
</script>



