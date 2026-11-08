<template>
  <div class="px-appointment-booking">
    <div class="pxb-steps">
      <span v-for="s in visibleSteps" :key="s" :class="{ on: step === s }">{{
        s
      }}</span>
    </div>
    <p v-if="store.error" class="pxb-err" role="alert">{{ store.error }}</p>
    <router-view />
  </div>
</template>
<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";
import { useBooking } from "./store";
const route = useRoute();
const store = useBooking();
const step = computed(() => route.name || "location");
const visibleSteps = computed(() => {
  const s = [
    "location",
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
