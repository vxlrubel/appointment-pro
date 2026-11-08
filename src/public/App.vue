<template>
  <div class="px-appointment-booking">
    <div class="pxb-steps">
      <span v-for="s in visibleSteps" :key="s" :class="{ on: step === s }">{{
        s
      }}</span>
    </div>
    <p v-if="store.error" class="pxb-err" role="alert">{{ store.error }}</p>
    <BookingSkeleton
      v-if="store.pending.boot || store.pending.staff"
      :variant="step === 'service' ? 'services' : 'cards'"
      :label="
        store.pending.boot ? 'Loading services' : 'Loading booking options'
      "
    />
    <template v-else>
      <button
        v-if="store.error && !store.services.length"
        class="pxb-btn ghost"
        @click="store.boot(store.preset)"
      >
        Try again
      </button>
      <router-view />
    </template>
  </div>
</template>
<script setup>
import BookingSkeleton from "./components/BookingSkeleton.vue";
import { computed } from "vue";
import { useRoute } from "vue-router";
import { useBooking } from "./store";
const route = useRoute();
const store = useBooking();
const step = computed(() => route.name || "service");
const visibleSteps = computed(() => {
  const s = [
    "service",
    "staff",
    "time",
    "extras",
    "details",
    "payment",
    "done",
  ];
  return s.filter((name) => {
    if (name === "extras" && !store.extras.length) return false;
    return true;
  });
});
</script>
