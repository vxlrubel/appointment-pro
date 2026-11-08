<script setup>
defineProps({
  variant: { type: String, default: "cards" },
  label: { type: String, default: "Loading booking options" },
  embedded: { type: Boolean, default: false },
});
</script>
<template>
  <div
    class="pxb-loading"
    :class="{ 'pxb-card': !embedded }"
    role="status"
    aria-live="polite"
    aria-busy="true"
    :aria-label="label"
  >
    <span class="pxb-sr-only">{{ label }}. Please wait.</span>
    <div aria-hidden="true">
      <span class="pxb-skeleton pxb-skeleton-title"></span>
      <div
        v-if="variant === 'services'"
        class="pxb-filters pxb-skeleton-filters"
      >
        <span
          v-for="filter in 2"
          :key="filter"
          class="pxb-skeleton pxb-skeleton-input"
        ></span>
      </div>
      <div v-if="variant === 'slots'" class="pxb-grid">
        <span
          v-for="slot in 12"
          :key="slot"
          class="pxb-skeleton pxb-skeleton-slot"
        ></span>
      </div>
      <div v-else-if="variant === 'form'" class="pxb-form">
        <span
          v-for="field in 3"
          :key="field"
          class="pxb-skeleton pxb-skeleton-input"
        ></span>
      </div>
      <div v-else v-for="card in 4" :key="card" class="pxb-skeleton-choice">
        <span class="pxb-skeleton pxb-skeleton-line"></span>
        <span class="pxb-skeleton pxb-skeleton-short"></span>
      </div>
    </div>
  </div>
</template>
