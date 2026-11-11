<script setup>
import { computed, inject, unref } from "vue";
import { formatCurrency } from "./currency";
const props = defineProps({
  amount: { type: [Number, String], default: 0 },
  currency: { type: String, default: "" },
});
const configuredCurrency = inject("pxCurrency", "USD");
const formatted = computed(() =>
  formatCurrency(
    props.amount,
    props.currency || unref(configuredCurrency) || "USD",
  ),
);
</script>
<template>
  <span class="px-currency">{{ formatted }}</span>
</template>

<style scoped>
.px-currency {
  white-space: nowrap;
  font-variant-numeric: tabular-nums;
}
</style>
