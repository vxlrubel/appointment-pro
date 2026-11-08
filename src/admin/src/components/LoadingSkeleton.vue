<script setup lang="ts">
withDefaults(
  defineProps<{
    variant?: 'dashboard' | 'table' | 'form' | 'calendar'
    rows?: number
    columns?: number
    label?: string
  }>(),
  {
    variant: 'table',
    rows: 5,
    columns: 4,
    label: 'Loading content',
  },
)
</script>

<template>
  <section
    class="px-loading-skeleton"
    role="status"
    aria-live="polite"
    aria-busy="true"
    :aria-label="label"
  >
    <span class="px-sr-only">{{ label }}. Please wait.</span>
    <div aria-hidden="true">
      <div v-if="variant === 'dashboard'" class="px-dynamic-grid">
        <div v-for="card in 4" :key="card" class="p-3 bg-white border px-skeleton-stat">
          <span class="px-skeleton px-skeleton-label"></span>
          <span class="px-skeleton px-skeleton-value"></span>
        </div>
      </div>

      <div v-if="variant === 'form'" class="px-card">
        <div class="px-form-row">
          <div v-for="field in 8" :key="field" class="px-field px-skeleton-field">
            <span class="px-skeleton px-skeleton-label"></span>
            <span class="px-skeleton px-skeleton-input"></span>
          </div>
        </div>
        <span class="px-skeleton px-skeleton-button"></span>
      </div>

      <div v-else-if="variant === 'calendar'" class="px-card px-skeleton-calendar">
        <div v-for="day in 7" :key="day" class="px-skeleton-day">
          <span class="px-skeleton px-skeleton-label"></span>
          <span v-for="event in 3" :key="event" class="px-skeleton px-skeleton-event"></span>
        </div>
      </div>

      <div v-else class="px-card px-skeleton-table" :class="{ 'mt-3': variant === 'dashboard' }">
        <span v-if="variant === 'dashboard'" class="px-skeleton px-skeleton-heading"></span>
        <div class="px-skeleton-table-grid" :style="{ '--px-skeleton-columns': columns }">
          <div class="px-skeleton-table-row px-skeleton-table-head">
            <span
              v-for="column in columns"
              :key="column"
              class="px-skeleton px-skeleton-label"
            ></span>
          </div>
          <div v-for="row in rows" :key="row" class="px-skeleton-table-row">
            <span
              v-for="column in columns"
              :key="column"
              class="px-skeleton px-skeleton-cell"
              :class="{ 'px-skeleton-cell-short': (row + column) % 3 === 0 }"
            ></span>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
