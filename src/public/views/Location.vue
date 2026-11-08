<script setup>
import { useRouter } from "vue-router";
import { useBooking } from "../store";
const store = useBooking();
const router = useRouter();
async function pick(loc) {
  await store.pickLocation(loc);
  router.push("/service");
}
</script>
<template>
  <div class="pxb-card">
    <h3>Choose a location</h3>
    <button
      class="pxb-choice"
      v-for="l in store.locations"
      :key="l.id"
      @click="pick(l)"
    >
      <b>{{ l.name }}</b>
      <div>{{ l.address }}</div>
    </button>
  </div>
</template>
